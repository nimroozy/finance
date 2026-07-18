<?php

namespace App\Services\Tasks;

use App\Models\Branch;
use App\Models\TaskSequence;
use Illuminate\Support\Facades\DB;

class TaskNumberService
{
    /**
     * Format: {branch.code}-TSK-{YEAR}-{000001} with row lock.
     */
    public function nextNumber(int $branchId, ?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        return DB::transaction(function () use ($branchId, $year) {
            $sequence = TaskSequence::query()
                ->where('branch_id', $branchId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = TaskSequence::create([
                    'branch_id' => $branchId,
                    'year' => $year,
                    'last_sequence' => 0,
                ]);

                $sequence = TaskSequence::query()
                    ->whereKey($sequence->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence->last_sequence = (int) $sequence->last_sequence + 1;
            $sequence->save();

            /** @var Branch $branch */
            $branch = Branch::withoutGlobalScopes()->findOrFail($branchId);
            $pad = (int) config('ticketing.task_number.sequence_pad', 6);
            $infix = (string) config('ticketing.task_number.infix', 'TSK');

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
