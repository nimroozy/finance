<?php

namespace App\Services\Tickets;

use App\Models\Branch;
use App\Models\TicketSequence;
use Illuminate\Support\Facades\DB;

class TicketNumberService
{
    /**
     * Format: {branch.code}-TKT-{YEAR}-{000001} with row lock.
     */
    public function nextNumber(int $branchId, ?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        return DB::transaction(function () use ($branchId, $year) {
            $sequence = TicketSequence::query()
                ->where('branch_id', $branchId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = TicketSequence::create([
                    'branch_id' => $branchId,
                    'year' => $year,
                    'last_sequence' => 0,
                ]);

                $sequence = TicketSequence::query()
                    ->whereKey($sequence->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence->last_sequence = (int) $sequence->last_sequence + 1;
            $sequence->save();

            /** @var Branch $branch */
            $branch = Branch::withoutGlobalScopes()->findOrFail($branchId);
            $pad = (int) config('ticketing.ticket_number.sequence_pad', 6);
            $infix = (string) config('ticketing.ticket_number.infix', 'TKT');

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
