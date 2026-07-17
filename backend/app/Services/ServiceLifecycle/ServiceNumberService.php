<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Branch;
use App\Models\Services\ServiceSequence;
use Illuminate\Support\Facades\DB;

class ServiceNumberService
{
    /**
     * Format: {branch.code}-SVC-{YEAR}-{000001} with row lock.
     */
    public function nextNumber(int $branchId, ?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        return DB::transaction(function () use ($branchId, $year) {
            $sequence = ServiceSequence::query()
                ->where('branch_id', $branchId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = ServiceSequence::create([
                    'branch_id' => $branchId,
                    'year' => $year,
                    'last_sequence' => 0,
                ]);

                $sequence = ServiceSequence::query()
                    ->whereKey($sequence->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence->last_sequence = (int) $sequence->last_sequence + 1;
            $sequence->save();

            /** @var Branch $branch */
            $branch = Branch::withoutGlobalScopes()->findOrFail($branchId);
            $pad = (int) config('service_lifecycle.service_number.sequence_pad', 6);
            $infix = (string) config('service_lifecycle.service_number.infix', 'SVC');

            return sprintf(
                '%s-%s-%d-%s',
                $branch->code,
                $infix,
                $year,
                str_pad((string) $sequence->last_sequence, $pad, '0', STR_PAD_LEFT),
            );
        });
    }

    public function nextContractNumber(int $branchId, ?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');
        $branch = Branch::withoutGlobalScopes()->findOrFail($branchId);
        $pad = (int) config('service_lifecycle.contract_number.sequence_pad', 6);
        $infix = (string) config('service_lifecycle.contract_number.infix', 'CTR');

        // Reuse service sequence table with a high offset year key space for contracts via separate lock row
        // using year*10 pattern is fragile; instead derive from service sequence + CTR infix uniqueness.
        return DB::transaction(function () use ($branchId, $year, $branch, $pad, $infix) {
            $sequence = ServiceSequence::query()
                ->where('branch_id', $branchId)
                ->where('year', $year + 10000) // contract namespace
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = ServiceSequence::create([
                    'branch_id' => $branchId,
                    'year' => $year + 10000,
                    'last_sequence' => 0,
                ]);
                $sequence = ServiceSequence::query()->whereKey($sequence->id)->lockForUpdate()->firstOrFail();
            }

            $sequence->last_sequence = (int) $sequence->last_sequence + 1;
            $sequence->save();

            return sprintf(
                '%s-%s-%d-%s',
                $branch->code,
                $infix,
                $year,
                str_pad((string) $sequence->last_sequence, $pad, '0', STR_PAD_LEFT),
            );
        });
    }
}
