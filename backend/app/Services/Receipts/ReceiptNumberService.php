<?php

namespace App\Services\Receipts;

use App\Models\Branch;
use App\Models\ReceiptSequence;
use Illuminate\Support\Facades\DB;

class ReceiptNumberService
{
    /**
     * Format: {BRANCH.receipt_prefix OR code}-{YEAR}-{seq:6} with row lock.
     */
    public function nextNumber(int $branchId, ?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        return DB::transaction(function () use ($branchId, $year) {
            $sequence = ReceiptSequence::query()
                ->where('branch_id', $branchId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = ReceiptSequence::create([
                    'branch_id' => $branchId,
                    'year' => $year,
                    'last_sequence' => 0,
                ]);

                $sequence = ReceiptSequence::query()
                    ->whereKey($sequence->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence->last_sequence = (int) $sequence->last_sequence + 1;
            $sequence->save();

            /** @var Branch $branch */
            $branch = Branch::withoutGlobalScopes()->findOrFail($branchId);
            $prefix = $branch->receipt_prefix ?: $branch->code;
            $pad = (int) config('payments.receipt.sequence_pad', 6);

            return sprintf('%s-%d-%s', $prefix, $year, str_pad((string) $sequence->last_sequence, $pad, '0', STR_PAD_LEFT));
        });
    }
}
