<?php

namespace App\Services\Crm;

use App\Models\Branch;
use App\Models\Crm\QuoteSequence;
use Illuminate\Support\Facades\DB;

class QuoteNumberService
{
    /**
     * Format: {branch.code}-QTE-{YEAR}-{000001}
     */
    public function nextNumber(int $branchId, ?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        return DB::transaction(function () use ($branchId, $year) {
            $sequence = QuoteSequence::query()
                ->where('branch_id', $branchId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = QuoteSequence::create([
                    'branch_id' => $branchId,
                    'year' => $year,
                    'last_sequence' => 0,
                ]);

                $sequence = QuoteSequence::query()
                    ->whereKey($sequence->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence->last_sequence = (int) $sequence->last_sequence + 1;
            $sequence->save();

            /** @var Branch $branch */
            $branch = Branch::withoutGlobalScopes()->findOrFail($branchId);
            $pad = (int) config('crm.quote_number.sequence_pad', 6);
            $infix = (string) config('crm.quote_number.infix', 'QTE');

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
