<?php

namespace App\Services\Payments;

use App\Models\Branch;
use App\Models\Payment;
use App\Models\PaymentReconciliationRecord;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PaymentReconciliationService
{
    public function runDaily(?CarbonInterface $date = null, ?int $branchId = null): PaymentReconciliationRecord
    {
        $day = ($date ?? now())->toDateString();

        $query = Payment::withoutGlobalScopes()
            ->whereDate('confirmed_at', $day)
            ->whereNull('deleted_at');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $payments = $query->get();

        $localConfirmed = $payments->filter(fn (Payment $p) => $p->isConfirmed() || $p->isReversed());
        $synced = $payments->filter(fn (Payment $p) => in_array($p->zoho_sync_status, [Payment::ZOHO_SYNCED, Payment::ZOHO_DRY_RUN], true));
        $failed = $payments->filter(fn (Payment $p) => $p->zoho_sync_status === Payment::ZOHO_FAILED);
        $reversed = $payments->filter(fn (Payment $p) => $p->isReversed());

        $localAmount = Money::sum($localConfirmed->pluck('amount'));
        $syncedAmount = Money::sum($synced->pluck('amount'));
        $variance = Money::sub($localAmount, $syncedAmount);

        return PaymentReconciliationRecord::query()->updateOrCreate(
            [
                'reconciliation_date' => $day,
                'branch_id' => $branchId,
            ],
            [
                'status' => 'completed',
                'local_confirmed_count' => $localConfirmed->count(),
                'zoho_synced_count' => $synced->count(),
                'sync_failed_count' => $failed->count(),
                'reversed_count' => $reversed->count(),
                'local_confirmed_amount' => $localAmount,
                'zoho_synced_amount' => $syncedAmount,
                'variance_amount' => $variance,
                'details' => [
                    'failed_payment_ids' => $failed->pluck('id')->values()->all(),
                    'reversed_payment_ids' => $reversed->pluck('id')->values()->all(),
                ],
            ]
        );
    }

    /**
     * Light foundation: one record per branch plus a global (null branch) summary.
     *
     * @return list<PaymentReconciliationRecord>
     */
    public function runDailyAllBranches(?CarbonInterface $date = null): array
    {
        $records = [];
        $records[] = $this->runDaily($date, null);

        $branchIds = Branch::withoutGlobalScopes()->where('is_active', true)->pluck('id');
        foreach ($branchIds as $branchId) {
            $records[] = $this->runDaily($date, (int) $branchId);
        }

        return $records;
    }

    public function paymentsSummary(?int $branchId = null, ?string $from = null, ?string $to = null): array
    {
        $q = Payment::query()->whereNull('deleted_at')->whereNotNull('confirmed_at');

        if ($branchId) {
            $q->where('branch_id', $branchId);
        }
        if ($from) {
            $q->whereDate('confirmed_at', '>=', $from);
        }
        if ($to) {
            $q->whereDate('confirmed_at', '<=', $to);
        }

        $rows = $q->select([
            'status',
            'zoho_sync_status',
            DB::raw('COUNT(*) as cnt'),
            DB::raw('SUM(amount) as total_amount'),
        ])
            ->groupBy('status', 'zoho_sync_status')
            ->get();

        return [
            'groups' => $rows,
            'total_count' => (int) $rows->sum('cnt'),
            'total_amount' => Money::normalize($rows->sum('total_amount') ?? '0'),
        ];
    }
}
