<?php

namespace App\Services\Crm;

use App\Models\Crm\SalesTarget;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalesTargetService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(array $data, ?User $actor = null): SalesTarget
    {
        if (empty($data['branch_id']) || empty($data['period_year']) || empty($data['period_month'])) {
            throw new InvalidArgumentException('Sales target requires branch_id, period_year, period_month.');
        }

        return DB::transaction(function () use ($data) {
            $target = SalesTarget::query()->updateOrCreate(
                [
                    'branch_id' => (int) $data['branch_id'],
                    'team_id' => $data['team_id'] ?? null,
                    'user_id' => $data['user_id'] ?? null,
                    'period_year' => (int) $data['period_year'],
                    'period_month' => (int) $data['period_month'],
                ],
                [
                    'leads_target' => $data['leads_target'] ?? null,
                    'qualified_target' => $data['qualified_target'] ?? null,
                    'won_target' => $data['won_target'] ?? null,
                    'revenue_target' => $data['revenue_target'] ?? null,
                    'installation_fee_target' => $data['installation_fee_target'] ?? null,
                    'mrr_target' => $data['mrr_target'] ?? null,
                ],
            );

            $this->audit->log('crm.sales_target.upserted', $target, null, $target->toArray(), $target->branch_id);

            return $target->fresh();
        });
    }
}
