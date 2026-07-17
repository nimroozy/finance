<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Crm\Lead;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tickets\Installation;
use App\Models\Tickets\Task;
use App\Models\Tickets\Ticket;
use App\Models\User;
use App\Models\Inventory\StockTransaction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-archive labeled demo / stage-test records without touching payments or stock_transactions.
 */
class Stage102CleanupDemoCommand extends Command
{
    protected $signature = 'stage102:cleanup-demo
                            {--dry-run : Report candidates only (default when --apply is absent)}
                            {--apply : Soft-delete / archive matching demo records}';

    protected $description = 'Find and soft-archive STAGE*TEST / DO NOT USE demo records (never deletes payments or stock_transactions)';

    /** @var list<string> */
    private array $patterns = [
        'STAGE%TEST%',
        '%DO NOT USE%',
        '%STAGE7%TEST%',
        '%STAGE8%TEST%',
        '%STAGE9%TEST%',
        '%STAGE10%TEST%',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        // --dry-run is the default when --apply is not set
        $dryRun = ! $apply || (bool) $this->option('dry-run');
        if ($apply) {
            $dryRun = false;
        }

        $candidates = [];
        $candidates = array_merge($candidates, $this->scanCustomers());
        $candidates = array_merge($candidates, $this->scanLeads());
        $candidates = array_merge($candidates, $this->scanBranches());
        $candidates = array_merge($candidates, $this->scanUsers());
        $candidates = array_merge($candidates, $this->scanTickets());
        $candidates = array_merge($candidates, $this->scanTasks());
        $candidates = array_merge($candidates, $this->scanInstallations());
        $candidates = array_merge($candidates, $this->scanInvoices());

        $report = [
            'generated_at' => now()->toIso8601String(),
            'mode' => $dryRun ? 'dry-run' : 'apply',
            'pattern_notes' => 'Matches names containing STAGE%TEST%, DO NOT USE, or STAGE7/8/9/10 TEST labels',
            'candidate_count' => count($candidates),
            'candidates' => $candidates,
            'protected' => [
                'payments' => 'NEVER deleted or soft-deleted by this command',
                'stock_transactions' => 'NEVER deleted or soft-deleted by this command',
            ],
            'archived' => [],
        ];

        if (! $dryRun) {
            $archived = [];
            DB::transaction(function () use ($candidates, &$archived) {
                foreach ($candidates as $row) {
                    $archived[] = $this->archiveCandidate($row);
                }
            });
            $report['archived'] = $archived;
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scanCustomers(): array
    {
        $q = Customer::query()->withoutGlobalScopes();
        $this->applyNamePatterns($q, ['contact_name', 'company_name', 'customer_number', 'classification_notes']);

        return $q->get()->map(function (Customer $c) {
            return [
                'table' => 'customers',
                'id' => $c->id,
                'label' => $c->contact_name ?: $c->company_name ?: $c->customer_number,
                'matched_fields' => $this->matchedFields($c, ['contact_name', 'company_name', 'customer_number', 'classification_notes']),
                'foreign_keys' => [
                    'payments_count' => Payment::query()->where('customer_id', $c->id)->count(),
                    'stock_transactions_count' => $this->stockTxCountForCustomer($c->id),
                    'invoices_count' => Invoice::query()->where('customer_id', $c->id)->count(),
                    'leads_converted_count' => Lead::query()->withoutGlobalScopes()->where('converted_customer_id', $c->id)->count(),
                ],
                'soft_deletes' => true,
                'will_touch_payments' => false,
                'will_touch_stock_transactions' => false,
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scanLeads(): array
    {
        $q = Lead::query()->withoutGlobalScopes();
        $this->applyNamePatterns($q, ['contact_person', 'company', 'lead_number', 'notes']);

        return $q->get()->map(function (Lead $lead) {
            return [
                'table' => 'crm_leads',
                'id' => $lead->id,
                'label' => $lead->contact_person ?: $lead->company ?: $lead->lead_number,
                'matched_fields' => $this->matchedFields($lead, ['contact_person', 'company', 'lead_number', 'notes']),
                'foreign_keys' => [
                    'converted_customer_id' => $lead->converted_customer_id,
                    'installation_id' => $lead->installation_id,
                ],
                'soft_deletes' => true,
                'will_touch_payments' => false,
                'will_touch_stock_transactions' => false,
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scanBranches(): array
    {
        $q = Branch::query();
        $this->applyNamePatterns($q, ['name_en', 'name_fa', 'code']);

        return $q->get()->map(function (Branch $b) {
            return [
                'table' => 'branches',
                'id' => $b->id,
                'label' => $b->name_en ?: $b->code,
                'matched_fields' => $this->matchedFields($b, ['name_en', 'name_fa', 'code']),
                'foreign_keys' => [
                    'customers_count' => Customer::query()->withoutGlobalScopes()->where('branch_id', $b->id)->count(),
                    'payments_count' => Payment::query()->where('branch_id', $b->id)->count(),
                ],
                'soft_deletes' => true,
                'will_touch_payments' => false,
                'will_touch_stock_transactions' => false,
                'note' => 'Branch archive is reported; apply soft-deletes the branch row only — payments remain',
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scanUsers(): array
    {
        $q = User::query();
        $this->applyNamePatterns($q, ['name', 'email']);

        return $q->get()->map(function (User $u) {
            return [
                'table' => 'users',
                'id' => $u->id,
                'label' => $u->name ?: $u->email,
                'matched_fields' => $this->matchedFields($u, ['name', 'email']),
                'foreign_keys' => [],
                'soft_deletes' => Schema::hasColumn('users', 'deleted_at'),
                'will_touch_payments' => false,
                'will_touch_stock_transactions' => false,
                'archive_strategy' => Schema::hasColumn('users', 'deleted_at') ? 'soft_delete' : 'deactivate_status',
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scanTickets(): array
    {
        if (! Schema::hasTable('tickets')) {
            return [];
        }
        $q = Ticket::query();
        $cols = array_values(array_filter(['subject', 'description', 'ticket_number'], fn ($c) => Schema::hasColumn('tickets', $c)));
        if ($cols === []) {
            return [];
        }
        $this->applyNamePatterns($q, $cols);

        return $q->get()->map(function (Ticket $t) use ($cols) {
            return [
                'table' => 'tickets',
                'id' => $t->id,
                'label' => $t->ticket_number ?? (string) $t->id,
                'matched_fields' => $this->matchedFields($t, $cols),
                'foreign_keys' => [],
                'soft_deletes' => true,
                'will_touch_payments' => false,
                'will_touch_stock_transactions' => false,
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scanTasks(): array
    {
        if (! Schema::hasTable('tasks')) {
            return [];
        }
        $q = Task::query();
        $cols = array_values(array_filter(['title', 'description', 'task_number'], fn ($c) => Schema::hasColumn('tasks', $c)));
        if ($cols === []) {
            return [];
        }
        $this->applyNamePatterns($q, $cols);

        return $q->get()->map(function (Task $t) use ($cols) {
            return [
                'table' => 'tasks',
                'id' => $t->id,
                'label' => $t->task_number ?? $t->title ?? (string) $t->id,
                'matched_fields' => $this->matchedFields($t, $cols),
                'foreign_keys' => [],
                'soft_deletes' => true,
                'will_touch_payments' => false,
                'will_touch_stock_transactions' => false,
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scanInstallations(): array
    {
        if (! Schema::hasTable('installations')) {
            return [];
        }
        $q = Installation::query();
        $cols = array_values(array_filter(['prospect_name', 'contact_name', 'notes', 'installation_number'], fn ($c) => Schema::hasColumn('installations', $c)));
        if ($cols === []) {
            return [];
        }
        $this->applyNamePatterns($q, $cols);

        return $q->get()->map(function (Installation $i) use ($cols) {
            return [
                'table' => 'installations',
                'id' => $i->id,
                'label' => $i->prospect_name ?? $i->contact_name ?? (string) $i->id,
                'matched_fields' => $this->matchedFields($i, $cols),
                'foreign_keys' => ['customer_id' => $i->customer_id ?? null],
                'soft_deletes' => true,
                'will_touch_payments' => false,
                'will_touch_stock_transactions' => false,
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scanInvoices(): array
    {
        $q = Invoice::query();
        $this->applyNamePatterns($q, ['invoice_number']);

        return $q->get()->map(function (Invoice $inv) {
            return [
                'table' => 'invoices',
                'id' => $inv->id,
                'label' => $inv->invoice_number,
                'matched_fields' => $this->matchedFields($inv, ['invoice_number']),
                'foreign_keys' => [
                    'customer_id' => $inv->customer_id,
                    'payments_count' => Schema::hasColumn('payments', 'invoice_id') ? Payment::query()->where('invoice_id', $inv->id)->count() : 0,
                ],
                'soft_deletes' => true,
                'will_touch_payments' => false,
                'will_touch_stock_transactions' => false,
            ];
        })->all();
    }

    /**
     * @param  list<string>  $columns
     */
    private function applyNamePatterns(Builder $query, array $columns): void
    {
        $query->where(function (Builder $outer) use ($columns) {
            foreach ($this->patterns as $pattern) {
                $outer->orWhere(function (Builder $inner) use ($columns, $pattern) {
                    foreach ($columns as $i => $col) {
                        if ($i === 0) {
                            $inner->where($col, 'like', $pattern);
                        } else {
                            $inner->orWhere($col, 'like', $pattern);
                        }
                    }
                });
            }
        });
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function matchedFields(Model $model, array $columns): array
    {
        $hit = [];
        foreach ($columns as $col) {
            $val = (string) ($model->getAttribute($col) ?? '');
            if ($val === '') {
                continue;
            }
            foreach ($this->patterns as $pattern) {
                $parts = explode('%', $pattern);
                $rx = '/'.implode('.*', array_map(static fn ($p) => preg_quote($p, '/'), $parts)).'/i';
                if (preg_match($rx, $val)) {
                    $hit[] = $col;
                    break;
                }
            }
        }

        return array_values(array_unique($hit));
    }

    private function stockTxCountForCustomer(int $customerId): int
    {
        if (! Schema::hasTable('stock_transactions') && ! Schema::hasTable('inventory_stock_transactions')) {
            return 0;
        }

        try {
            return StockTransaction::query()->withoutGlobalScopes()->where('customer_id', $customerId)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function archiveCandidate(array $row): array
    {
        $table = $row['table'];
        $id = (int) $row['id'];
        $meta = [
            'stage102_demo_archive' => true,
            'archived_at' => now()->toIso8601String(),
            'reason' => 'stage102:cleanup-demo',
            'matched_fields' => $row['matched_fields'] ?? [],
        ];

        return match ($table) {
            'customers' => $this->archiveCustomer($id, $meta),
            'crm_leads' => $this->archiveLead($id, $meta),
            'branches' => $this->archiveBranch($id, $meta),
            'users' => $this->archiveUser($id, $meta),
            'tickets' => $this->archiveSoft($table, Ticket::class, $id, $meta),
            'tasks' => $this->archiveSoft($table, Task::class, $id, $meta),
            'installations' => $this->archiveSoft($table, Installation::class, $id, $meta),
            'invoices' => $this->archiveSoft($table, Invoice::class, $id, $meta),
            default => ['table' => $table, 'id' => $id, 'status' => 'skipped_unknown'],
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function archiveCustomer(int $id, array $meta): array
    {
        $c = Customer::withoutGlobalScopes()->find($id);
        if (! $c) {
            return ['table' => 'customers', 'id' => $id, 'status' => 'missing'];
        }

        $tags = is_array($c->reporting_tags) ? $c->reporting_tags : [];
        $tags['stage102_demo_archive'] = $meta;
        $c->reporting_tags = $tags;
        $c->is_test_customer = true;
        $c->status = Customer::STATUS_ARCHIVED;
        $c->classification_notes = trim(($c->classification_notes ? $c->classification_notes.'\n' : '').'[stage102:cleanup-demo] archived');
        $c->save();
        $c->delete(); // soft delete

        return ['table' => 'customers', 'id' => $id, 'status' => 'archived_soft_deleted'];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function archiveLead(int $id, array $meta): array
    {
        $lead = Lead::withoutGlobalScopes()->find($id);
        if (! $lead) {
            return ['table' => 'crm_leads', 'id' => $id, 'status' => 'missing'];
        }
        $lead->notes = trim(($lead->notes ? $lead->notes.'\n' : '').'[stage102:cleanup-demo] '.json_encode($meta));
        $lead->save();
        $lead->delete();

        return ['table' => 'crm_leads', 'id' => $id, 'status' => 'archived_soft_deleted'];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function archiveBranch(int $id, array $meta): array
    {
        $b = Branch::query()->find($id);
        if (! $b) {
            return ['table' => 'branches', 'id' => $id, 'status' => 'missing'];
        }
        // Prefer soft-delete only; do not cascade to payments.
        $b->delete();

        return ['table' => 'branches', 'id' => $id, 'status' => 'archived_soft_deleted', 'meta' => $meta];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function archiveUser(int $id, array $meta): array
    {
        $u = User::query()->find($id);
        if (! $u) {
            return ['table' => 'users', 'id' => $id, 'status' => 'missing'];
        }
        if (Schema::hasColumn('users', 'deleted_at') && method_exists($u, 'delete')) {
            // SoftDeletes if present; otherwise fall through
            $traits = class_uses_recursive($u);
            if (isset($traits[\Illuminate\Database\Eloquent\SoftDeletes::class])) {
                $u->delete();

                return ['table' => 'users', 'id' => $id, 'status' => 'archived_soft_deleted', 'meta' => $meta];
            }
        }
        $u->status = 'inactive';
        $u->save();

        return ['table' => 'users', 'id' => $id, 'status' => 'deactivated', 'meta' => $meta];
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function archiveSoft(string $table, string $class, int $id, array $meta): array
    {
        /** @var Model|null $model */
        $model = $class::query()->find($id);
        if (! $model) {
            return ['table' => $table, 'id' => $id, 'status' => 'missing'];
        }
        if ($model->isFillable('notes') || array_key_exists('notes', $model->getAttributes())) {
            $model->setAttribute('notes', trim(($model->getAttribute('notes') ? $model->getAttribute('notes').'\n' : '').'[stage102:cleanup-demo]'));
            $model->save();
        }
        $model->delete();

        return ['table' => $table, 'id' => $id, 'status' => 'archived_soft_deleted', 'meta' => $meta];
    }
}
