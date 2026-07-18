<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Crm\Lead;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tickets\Installation;
use App\Models\Tickets\Task;
use App\Models\Tickets\Ticket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Yaml\Yaml;

/**
 * Apply a reviewed demo-cleanup manifest (delete|archive|disable_rename|preserve).
 * Never deletes payments or stock_transactions.
 */
class Stage103CleanupDemoCommand extends Command
{
    protected $signature = 'stage103:cleanup-demo
                            {--manifest= : Path to reviewed JSON/YAML manifest}
                            {--dry-run : Report planned actions only (default when --apply is absent)}
                            {--apply : Apply manifest actions and write result report}';

    protected $description = 'Apply reviewed Stage 10.3 demo cleanup manifest (never deletes payments or stock_transactions)';

    private const ARCHIVED_PREFIX = '[ARCHIVED TEST] ';

    private const ALLOWED_ACTIONS = ['delete', 'archive', 'disable_rename', 'preserve'];

    public function handle(): int
    {
        $manifestPath = (string) ($this->option('manifest') ?: '');
        if ($manifestPath === '') {
            $this->error('--manifest=path is required');

            return self::FAILURE;
        }

        $resolved = $this->resolveManifestPath($manifestPath);
        if ($resolved === null || ! is_file($resolved)) {
            $this->error("Manifest not found: {$manifestPath}");

            return self::FAILURE;
        }

        try {
            $manifest = $this->loadManifest($resolved);
        } catch (\Throwable $e) {
            $this->error('Failed to parse manifest: '.$e->getMessage());

            return self::FAILURE;
        }

        $items = $manifest['items'] ?? null;
        if (! is_array($items) || $items === []) {
            $this->error('Manifest has no items[]');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply;

        $planned = [];
        foreach ($items as $idx => $item) {
            if (! is_array($item)) {
                $this->error("Invalid item at index {$idx}");

                return self::FAILURE;
            }
            $action = (string) ($item['action'] ?? '');
            if (! in_array($action, self::ALLOWED_ACTIONS, true)) {
                $this->error("Invalid action '{$action}' at index {$idx}");

                return self::FAILURE;
            }
            $table = (string) ($item['table'] ?? '');
            $id = (int) ($item['id'] ?? 0);
            if ($table === '' || $id < 1) {
                $this->error("Missing table/id at index {$idx}");

                return self::FAILURE;
            }

            // Hard guard: never allow payment/stock tables in manifest apply.
            if (in_array($table, ['payments', 'stock_transactions', 'inventory_stock_transactions'], true)) {
                $this->error("Refusing to act on protected table: {$table}");

                return self::FAILURE;
            }

            $planned[] = [
                'table' => $table,
                'id' => $id,
                'action' => $action,
                'classification' => $item['classification'] ?? null,
                'label' => $item['label'] ?? null,
                'reason' => $item['reason'] ?? null,
            ];
        }

        $results = [];
        $paymentCountBefore = Payment::query()->count();
        $stockCountBefore = $this->stockTransactionCount();

        if ($apply) {
            DB::transaction(function () use ($planned, &$results) {
                foreach ($planned as $row) {
                    $results[] = $this->applyItem($row);
                }
            });
        } else {
            foreach ($planned as $row) {
                $results[] = array_merge($row, ['status' => 'planned_'.$row['action']]);
            }
        }

        $paymentCountAfter = Payment::query()->count();
        $stockCountAfter = $this->stockTransactionCount();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'mode' => $dryRun ? 'dry-run' : 'apply',
            'manifest' => $resolved,
            'item_count' => count($planned),
            'results' => $results,
            'counts' => [
                'payments_before' => $paymentCountBefore,
                'payments_after' => $paymentCountAfter,
                'stock_transactions_before' => $stockCountBefore,
                'stock_transactions_after' => $stockCountAfter,
                'payments_unchanged' => $paymentCountBefore === $paymentCountAfter,
                'stock_unchanged' => $stockCountBefore === $stockCountAfter,
            ],
            'protected' => [
                'payments' => 'NEVER deleted by this command',
                'stock_transactions' => 'NEVER deleted by this command',
            ],
            'action_summary' => $this->summarizeActions($results, $dryRun),
        ];

        if ($apply) {
            $mdPath = $this->writeMarkdownReport($report, $resolved);
            $report['result_report'] = $mdPath;
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($paymentCountBefore !== $paymentCountAfter || $stockCountBefore !== $stockCountAfter) {
            $this->error('Integrity failure: payment or stock_transaction counts changed');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveManifestPath(string $path): ?string
    {
        if (is_file($path)) {
            return realpath($path) ?: $path;
        }

        $fromBase = base_path($path);
        if (is_file($fromBase)) {
            return realpath($fromBase) ?: $fromBase;
        }

        // Manifests live in repo docs/ when cwd is backend/
        $fromRepo = base_path('../'.$path);
        if (is_file($fromRepo)) {
            return realpath($fromRepo) ?: $fromRepo;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadManifest(string $path): array
    {
        $raw = File::get($path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($ext, ['yaml', 'yml'], true)) {
            $parsed = Yaml::parse($raw);
            if (! is_array($parsed)) {
                throw new \RuntimeException('YAML root must be an object');
            }

            return $parsed;
        }

        $parsed = json_decode($raw, true);
        if (! is_array($parsed)) {
            throw new \RuntimeException('JSON root must be an object');
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyItem(array $row): array
    {
        $action = $row['action'];
        $table = $row['table'];
        $id = (int) $row['id'];

        if ($action === 'preserve') {
            return array_merge($row, ['status' => 'preserved']);
        }

        return match ($table) {
            'customers' => $this->applyCustomer($id, $action, $row),
            'crm_leads' => $this->applyLead($id, $action, $row),
            'branches' => $this->applyBranch($id, $action, $row),
            'users' => $this->applyUser($id, $action, $row),
            'tickets' => $this->applySoftModel($table, Ticket::class, $id, $action, $row),
            'tasks' => $this->applySoftModel($table, Task::class, $id, $action, $row),
            'installations' => $this->applySoftModel($table, Installation::class, $id, $action, $row),
            default => array_merge($row, ['status' => 'skipped_unknown_table']),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyCustomer(int $id, string $action, array $row): array
    {
        $c = Customer::withoutGlobalScopes()->find($id);
        if (! $c) {
            return array_merge($row, ['status' => 'missing']);
        }

        $payments = Payment::query()->where('customer_id', $id)->count();
        if ($action === 'delete' && $payments > 0) {
            // Safety: never delete payment-linked customers — upgrade to archive.
            $action = 'archive';
            $row['action_overridden'] = 'archive_due_to_payments';
        }

        $this->prefixCustomerNames($c);

        $tags = is_array($c->reporting_tags) ? $c->reporting_tags : [];
        $tags['stage103_demo_cleanup'] = [
            'action' => $action,
            'at' => now()->toIso8601String(),
            'classification' => $row['classification'] ?? null,
        ];
        $c->reporting_tags = $tags;
        $c->is_test_customer = true;
        $c->status = Customer::STATUS_ARCHIVED;
        $c->classification_notes = trim(($c->classification_notes ? $c->classification_notes."\n" : '').'[stage103:cleanup-demo] '.$action);
        $c->save();

        if (in_array($action, ['delete', 'archive'], true)) {
            if (method_exists($c, 'trashed') && ! $c->trashed()) {
                $c->delete();
            }
        }

        return array_merge($row, [
            'status' => $action === 'delete' ? 'soft_deleted' : 'archived',
            'payments_count' => $payments,
        ]);
    }

    private function prefixCustomerNames(Customer $c): void
    {
        foreach (['contact_name', 'company_name'] as $field) {
            $val = (string) ($c->getAttribute($field) ?? '');
            if ($val !== '' && ! str_starts_with($val, self::ARCHIVED_PREFIX)) {
                $c->setAttribute($field, self::ARCHIVED_PREFIX.$val);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyLead(int $id, string $action, array $row): array
    {
        $lead = Lead::withoutGlobalScopes()->find($id);
        if (! $lead) {
            return array_merge($row, ['status' => 'missing']);
        }

        if ($action === 'preserve') {
            return array_merge($row, ['status' => 'preserved']);
        }

        foreach (['contact_person', 'company'] as $field) {
            $val = (string) ($lead->getAttribute($field) ?? '');
            if ($val !== '' && ! str_starts_with($val, self::ARCHIVED_PREFIX)) {
                $lead->setAttribute($field, self::ARCHIVED_PREFIX.$val);
            }
        }
        $lead->notes = trim(($lead->notes ? $lead->notes."\n" : '').'[stage103:cleanup-demo] '.$action);
        $lead->save();

        if (in_array($action, ['delete', 'archive'], true)) {
            $lead->delete();
        }

        return array_merge($row, ['status' => $action === 'delete' ? 'soft_deleted' : 'archived']);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyBranch(int $id, string $action, array $row): array
    {
        $b = Branch::withoutGlobalScopes()->find($id)
            ?? Branch::withoutGlobalScopes()->withTrashed()->find($id);
        if (! $b) {
            return array_merge($row, ['status' => 'missing']);
        }

        if ($action === 'preserve') {
            return array_merge($row, ['status' => 'preserved']);
        }

        // Always rename + deactivate for disable_rename / archive / delete on branches.
        foreach (['name_en', 'name_fa', 'code'] as $field) {
            $val = (string) ($b->getAttribute($field) ?? '');
            if ($val !== '' && ! str_starts_with($val, self::ARCHIVED_PREFIX) && ! str_starts_with($val, 'ARCHIVED-')) {
                if ($field === 'code') {
                    $b->setAttribute($field, 'X'.substr(preg_replace('/[^A-Za-z0-9]/', '', $val) ?: 'TST', 0, 8));
                } else {
                    $b->setAttribute($field, self::ARCHIVED_PREFIX.$val);
                }
            }
        }
        $b->is_active = false;
        $b->save();

        if (in_array($action, ['delete', 'archive'], true)) {
            $b->delete();
        }

        return array_merge($row, [
            'status' => $action === 'disable_rename' ? 'disabled_renamed' : ($action === 'delete' ? 'soft_deleted' : 'archived'),
            'payments_on_branch' => Payment::query()->where('branch_id', $id)->count(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyUser(int $id, string $action, array $row): array
    {
        $u = User::query()->find($id);
        if (! $u) {
            return array_merge($row, ['status' => 'missing']);
        }

        if ($action === 'preserve') {
            return array_merge($row, ['status' => 'preserved']);
        }

        $name = (string) $u->name;
        if ($name !== '' && ! str_starts_with($name, self::ARCHIVED_PREFIX)) {
            $u->name = self::ARCHIVED_PREFIX.$name;
        }
        $email = (string) $u->email;
        if ($email !== '' && ! str_starts_with($email, 'archived.')) {
            $u->email = 'archived.'.str_replace('@', '.at.', $email).'.invalid';
        }
        $u->status = User::STATUS_DISABLED;
        $u->save();

        return array_merge($row, ['status' => 'disabled_renamed']);
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applySoftModel(string $table, string $class, int $id, string $action, array $row): array
    {
        /** @var Model|null $model */
        $model = $class::query()->find($id);
        if (! $model) {
            return array_merge($row, ['status' => 'missing']);
        }

        if ($action === 'preserve') {
            return array_merge($row, ['status' => 'preserved']);
        }

        if ($action === 'disable_rename') {
            foreach (['title', 'subject', 'prospect_name', 'contact_name', 'name'] as $field) {
                if (! array_key_exists($field, $model->getAttributes()) && ! $model->isFillable($field)) {
                    continue;
                }
                $val = (string) ($model->getAttribute($field) ?? '');
                if ($val !== '' && ! str_starts_with($val, self::ARCHIVED_PREFIX)) {
                    $model->setAttribute($field, self::ARCHIVED_PREFIX.$val);
                }
            }
            if (array_key_exists('notes', $model->getAttributes()) || $model->isFillable('notes')) {
                $model->setAttribute('notes', trim(($model->getAttribute('notes') ? $model->getAttribute('notes')."\n" : '').'[stage103:cleanup-demo] disable_rename'));
            }
            $model->save();

            return array_merge($row, ['status' => 'disabled_renamed']);
        }

        if (array_key_exists('notes', $model->getAttributes()) || $model->isFillable('notes')) {
            $model->setAttribute('notes', trim(($model->getAttribute('notes') ? $model->getAttribute('notes')."\n" : '').'[stage103:cleanup-demo] '.$action));
            $model->save();
        }
        $model->delete();

        return array_merge($row, ['status' => $action === 'delete' ? 'soft_deleted' : 'archived']);
    }

    private function stockTransactionCount(): int
    {
        foreach (['stock_transactions', 'inventory_stock_transactions'] as $table) {
            if (Schema::hasTable($table)) {
                return (int) DB::table($table)->count();
            }
        }

        return 0;
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return array<string, int>
     */
    private function summarizeActions(array $results, bool $dryRun): array
    {
        $summary = [];
        foreach ($results as $r) {
            $key = $dryRun ? ('planned_'.($r['action'] ?? 'unknown')) : (string) ($r['status'] ?? 'unknown');
            $summary[$key] = ($summary[$key] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function writeMarkdownReport(array $report, string $manifestPath): string
    {
        // Environment-specific output only. This must never target
        // docs/STAGE_10_3_CLEANUP_RESULT.md: that file is the historical
        // Stage 10.3 production cleanup record, and acceptance/demo runs of
        // this command previously overwrote it with per-run output (see
        // docs/STAGE_10_3_CLEANUP_RESULT.md history and Stage 10.5 repair).
        $path = storage_path('app/stage103-cleanup-result.md');

        $lines = [];
        $lines[] = '# Stage 10.3 — Demo cleanup result';
        $lines[] = '';
        $lines[] = '**Generated:** '.$report['generated_at'];
        $lines[] = '**Mode:** '.$report['mode'];
        $lines[] = '**Manifest:** `'.$manifestPath.'`';
        $lines[] = '';
        $lines[] = '## Integrity';
        $lines[] = '';
        $lines[] = '| Metric | Before | After | Unchanged |';
        $lines[] = '|--------|-------:|------:|:---------:|';
        $counts = $report['counts'];
        $lines[] = '| payments | '.$counts['payments_before'].' | '.$counts['payments_after'].' | '.($counts['payments_unchanged'] ? 'yes' : 'NO').' |';
        $lines[] = '| stock_transactions | '.$counts['stock_transactions_before'].' | '.$counts['stock_transactions_after'].' | '.($counts['stock_unchanged'] ? 'yes' : 'NO').' |';
        $lines[] = '';
        $lines[] = '## Action summary';
        $lines[] = '';
        foreach ($report['action_summary'] as $k => $n) {
            $lines[] = '- `'.$k.'`: '.$n;
        }
        $lines[] = '';
        $lines[] = '## Per-item results';
        $lines[] = '';
        $lines[] = '| Table | ID | Action | Status | Label |';
        $lines[] = '|-------|---:|--------|--------|-------|';
        foreach ($report['results'] as $r) {
            $lines[] = '| '.$r['table'].' | '.$r['id'].' | '.$r['action'].' | '.($r['status'] ?? '').' | '.str_replace('|', '/', (string) ($r['label'] ?? '')).' |';
        }
        $lines[] = '';
        $lines[] = 'Payments and stock_transactions were not deleted.';
        $lines[] = '';

        File::put($path, implode("\n", $lines));

        return $path;
    }
}
