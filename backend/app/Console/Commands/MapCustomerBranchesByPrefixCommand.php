<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Mapping\CustomerBranchResolutionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MapCustomerBranchesByPrefixCommand extends Command
{
    protected $signature = 'customers:map-branches-by-prefix
                            {--apply : Persist unambiguous mappings (dry-run by default)}
                            {--branch= : Limit to current branch_id}
                            {--prefix= : Limit to customers whose number starts with this prefix}
                            {--customer= : Limit to a single local customer id}
                            {--chunk=200 : Chunk size}';

    protected $description = 'Map customers to branches using configured customer-number prefixes';

    public function handle(CustomerBranchResolutionService $resolver, \App\Services\Mapping\CustomerNumberPrefixMatcher $matcher): int
    {
        $apply = (bool) $this->option('apply');
        $runId = 'prefix-map-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6));
        $chunk = max(50, (int) $this->option('chunk'));

        $query = Customer::withoutGlobalScopes()->orderBy('id');
        if ($this->option('branch')) {
            $query->where('branch_id', (int) $this->option('branch'));
        }
        if ($this->option('customer')) {
            $query->whereKey((int) $this->option('customer'));
        }
        if ($this->option('prefix')) {
            $prefix = strtoupper(trim((string) $this->option('prefix')));
            $query->where(function ($q) use ($prefix) {
                $q->where('customer_number', 'like', $prefix.'%')
                    ->orWhere('customer_number', 'like', $prefix.'-%')
                    ->orWhere('customer_number', 'like', $prefix.'_%')
                    ->orWhere('customer_number', 'like', $prefix.'/%')
                    ->orWhere('customer_number', 'like', $prefix.' %')
                    ->orWhere('contact_name', 'like', $prefix.'%')
                    ->orWhere('contact_name', 'like', $prefix.'-%')
                    ->orWhere('contact_name', 'like', $prefix.'_%')
                    ->orWhere('contact_name', 'like', $prefix.'/%')
                    ->orWhere('contact_name', 'like', $prefix.' %');
            });
        }

        $stats = [
            'scanned' => 0,
            'prefix_matched' => 0,
            'prefix_unknown' => 0,
            'already_correct' => 0,
            'would_change' => 0,
            'remapped' => 0,
            'conflicts' => 0,
            'protected' => 0,
            'missing_number' => 0,
            'admin_override' => 0,
        ];
        $rows = [];

        $this->info(($apply ? 'APPLY' : 'DRY-RUN')." run_id={$runId}");

        $query->chunkById($chunk, function ($customers) use ($resolver, $matcher, $apply, $runId, &$stats, &$rows) {
            foreach ($customers as $customer) {
                $stats['scanned']++;
                $effectiveNumber = $matcher->effectiveCustomerNumber($customer->customer_number, $customer->contact_name);
                if (! filled($effectiveNumber)) {
                    $stats['missing_number']++;
                }

                $resolution = $resolver->resolve($customer);
                if ($customer->administrator_branch_override) {
                    $stats['admin_override']++;
                }
                if (($resolution['prefix_detected'] ?? null) && ($resolution['resolution_source'] ?? '') === CustomerBranchResolutionService::SOURCE_CUSTOMER_PREFIX) {
                    $stats['prefix_matched']++;
                }
                if (($resolution['conflict_type'] ?? null) === 'unknown_prefix') {
                    $stats['prefix_unknown']++;
                }
                if ($resolution['conflict_type'] ?? null) {
                    $stats['conflicts']++;
                }
                if ($resolution['protected_history'] ?? false) {
                    $stats['protected']++;
                }
                if (! ($resolution['would_change'] ?? false) && ($resolution['resolved_branch_id'] ?? null) && (int) $customer->branch_id === (int) $resolution['resolved_branch_id']) {
                    $stats['already_correct']++;
                }
                if ($resolution['would_change'] ?? false) {
                    $stats['would_change']++;
                }

                $before = $customer->branch_id;
                $resolver->apply($customer, $resolution, null, dryRun: ! $apply, runId: $runId);
                $fresh = $customer->fresh();
                if ($apply && (int) $before !== (int) $fresh->branch_id) {
                    $stats['remapped']++;
                }

                $rows[] = [
                    'customer_id' => $customer->id,
                    'customer_number' => $customer->customer_number,
                    'effective_number' => $effectiveNumber,
                    'from_branch_id' => $before,
                    'to_branch_id' => $resolution['resolved_branch_id'],
                    'source' => $resolution['resolution_source'],
                    'conflict' => $resolution['conflict_type'],
                    'protected' => $resolution['protected_history'],
                    'would_change' => $resolution['would_change'],
                ];
            }
        });

        $path = 'reports/customer-prefix-map-'.$runId.'.json';
        Storage::disk('local')->put($path, json_encode([
            'run_id' => $runId,
            'apply' => $apply,
            'stats' => $stats,
            'rows' => $rows,
        ], JSON_PRETTY_PRINT));

        $this->table(array_keys($stats), [array_values($stats)]);
        $this->info('Report: storage/app/'.$path);

        if ($stats['conflicts'] > 0 && $apply) {
            $this->warn('Conflicts were recorded and not auto-applied.');
        }

        return self::SUCCESS;
    }
}
