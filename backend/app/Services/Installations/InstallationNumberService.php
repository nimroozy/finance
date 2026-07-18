<?php

namespace App\Services\Installations;

use App\Models\Branch;
use App\Models\InstallationSequence;
use Illuminate\Support\Facades\DB;

class InstallationNumberService
{
    /**
     * Format: {branch.code}-INS-{YEAR}-{000001} with row lock.
     */
    public function nextNumber(int $branchId, ?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        return DB::transaction(function () use ($branchId, $year) {
            $sequence = InstallationSequence::query()
                ->where('branch_id', $branchId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = InstallationSequence::create([
                    'branch_id' => $branchId,
                    'year' => $year,
                    'last_sequence' => 0,
                ]);

                $sequence = InstallationSequence::query()
                    ->whereKey($sequence->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence->last_sequence = (int) $sequence->last_sequence + 1;
            $sequence->save();

            /** @var Branch $branch */
            $branch = Branch::withoutGlobalScopes()->findOrFail($branchId);
            $pad = (int) config('ticketing.installation_number.sequence_pad', 6);
            $infix = (string) config('ticketing.installation_number.infix', 'INS');

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
