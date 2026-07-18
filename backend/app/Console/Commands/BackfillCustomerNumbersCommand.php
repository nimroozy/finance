<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerNumberBackfillHistory;
use App\Services\AuditLogger;
use App\Services\Mapping\CustomerBranchResolutionService;
use App\Services\Mapping\CustomerNumberExtractionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackfillCustomerNumbersCommand extends Command
{
    protected $signature = 'customers:backfill-customer-numbers
                            {--apply : Persist extractions customer numbers (dry-run by default)}
                            {--branch= : Limit to branch_id}
                            {--prefix= : Limit by contact_name/customer_number prefix}
                            {--customer= : Limit to a single customer id}
                            {--only-missing : Only customers with empty customer_number}
                            {--force-overwrite : Allow overwriting non-empty verified numbers}
                            {--chunk=200 : Chunk size}';

    protected $description = 'Backfill customer numbers from configured contact-name prefixes';

    public function handle(
        CustomerNumberExtractionService $extractor,
        CustomerBranchResolutionService $resolver,
        AuditLogger $audit,
    ): int {
        $apply = (bool) $this->option('apply');
        $onlyMissing = (bool) $this->option('only-missing');
        $force = (bool) $this->option('force-overwrite');
        $runId = 'number-backfill-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6));
        $chunk = max(50, (int) $this->option('chunk'));

        $query = Customer::withoutGlobalScopes()->orderBy('id');
        if ($this->option('branch')) {
            $query->where('branch_id', (int) $this->option('branch'));
        }
        if ($this->option('customer')) {
            $query->whereKey((int) $this->option('customer'));
        }
        if ($onlyMissing) {
            $query->where(function ($q) {
                $q->whereNull('customer_number')->orWhere('customer_number', '');
            });
        }
        if ($this->option('prefix')) {
            $prefix = strtoupper(trim((string) $this->option('prefix')));
            $query->where(function ($q) use ($prefix) {
                $q->where('customer_number', 'like', $prefix.'%')
                    ->orWhere('contact_name', 'like', $prefix.'%');
            });
        }

        $stats = [
            'scanned' => 0,
            'extracted' => 0,
            'already_present' => 0,
            'conflict' => 0,
            'invalid_format' => 0,
            'unknown_prefix' => 0,
            'no_number' => 0,
            'protected' => 0,
            'skipped' => 0,
            'applied' => 0,
        ];
        $rows = [];

        $this->info(($apply ? 'APPLY' : 'DRY-RUN')." run_id={$runId}");

        try {
            $query->chunkById($chunk, function ($customers) use ($extractor, $resolver, $audit, $apply, $force, $runId, &$stats, &$rows) {
                foreach ($customers as $customer) {
                    $stats['scanned']++;
                    $before = $customer->customer_number;
                    $extraction = $extractor->extract($customer);
                    $class = $extraction['result_class'];

                    if (filled($before) && ! $force) {
                        $canonicalBefore = $extraction['valid'] && $extraction['source'] === CustomerNumberExtractionService::SOURCE_ZOHO
                            ? $extraction['extracted_customer_number']
                            : null;
                        if ($canonicalBefore && $canonicalBefore === $this->normalizeExisting($before, $extractor)) {
                            $class = 'already_present';
                        } elseif (filled($before) && ! $extraction['valid']) {
                            $class = $class ?: 'skipped';
                        } elseif (filled($before) && $extraction['valid'] && $extraction['extracted_customer_number'] !== $before && ! $force) {
                            $class = 'skipped';
                        } else {
                            $class = 'already_present';
                        }
                    }

                    if ($resolver->hasProtectedHistory($customer) && $class === 'extracted' && filled($before) && $force) {
                        $class = 'protected';
                    }

                    if (! isset($stats[$class])) {
                        $stats[$class] = 0;
                    }
                    $stats[$class]++;

                    $applied = false;
                    if ($apply && $class === 'extracted' && $extraction['valid']) {
                        $customer->forceFill([
                            'customer_number' => $extraction['extracted_customer_number'],
                            'extracted_customer_number' => $extraction['extracted_customer_number'],
                            'source_contact_name' => $customer->source_contact_name ?: $customer->contact_name,
                            'clean_display_name' => $extraction['clean_display_name'],
                            'customer_number_source' => $extraction['source'],
                            'customer_number_confidence' => $extraction['confidence'],
                            'customer_number_extracted_at' => now(),
                        ])->save();
                        $applied = true;
                        $stats['applied']++;
                        $audit->log('customer.number_backfilled', $customer, [
                            'customer_number' => $before,
                        ], [
                            'customer_number' => $extraction['extracted_customer_number'],
                            'source' => $extraction['source'],
                        ], $customer->branch_id);
                    }

                    CustomerNumberBackfillHistory::query()->create([
                        'customer_id' => $customer->id,
                        'before_customer_number' => $before,
                        'after_customer_number' => $applied ? $extraction['extracted_customer_number'] : $before,
                        'extracted_customer_number' => $extraction['extracted_customer_number'],
                        'clean_display_name' => $extraction['clean_display_name'],
                        'source' => $extraction['source'],
                        'confidence' => $extraction['confidence'],
                        'result_class' => $class,
                        'dry_run' => ! $apply,
                        'applied' => $applied,
                        'evidence' => $extraction,
                        'explanation' => $extraction['explanation'],
                        'run_id' => $runId,
                    ]);

                    $rows[] = [
                        'customer_id' => $customer->id,
                        'before' => $before,
                        'after' => $extraction['extracted_customer_number'],
                        'class' => $class,
                        'applied' => $applied,
                    ];
                }
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $path = 'reports/customer-number-backfill-'.$runId.'.json';
        Storage::disk('local')->put($path, json_encode([
            'run_id' => $runId,
            'apply' => $apply,
            'stats' => $stats,
            'rows' => $rows,
        ], JSON_PRETTY_PRINT));

        $this->table(array_keys($stats), [array_values($stats)]);
        $this->info('Report: storage/app/'.$path);

        return self::SUCCESS;
    }

    protected function normalizeExisting(string $before, CustomerNumberExtractionService $extractor): ?string
    {
        $parsed = $extractor->extract(['customer_number' => $before, 'contact_name' => null]);

        return $parsed['valid'] ? $parsed['extracted_customer_number'] : null;
    }
}
