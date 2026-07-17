<?php

namespace App\Models\Crm;

use App\Models\Branch;
use App\Models\Org\Team;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToBranchScope::class])]
class SalesTarget extends Model
{
    protected $table = 'crm_sales_targets';

    protected $fillable = [
        'branch_id',
        'team_id',
        'user_id',
        'period_year',
        'period_month',
        'leads_target',
        'qualified_target',
        'won_target',
        'revenue_target',
        'installation_fee_target',
        'mrr_target',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'leads_target' => 'integer',
            'qualified_target' => 'integer',
            'won_target' => 'integer',
            'revenue_target' => 'decimal:2',
            'installation_fee_target' => 'decimal:2',
            'mrr_target' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
