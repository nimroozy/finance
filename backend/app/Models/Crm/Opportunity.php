<?php

namespace App\Models\Crm;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class Opportunity extends Model
{
    use SoftDeletes;

    protected $table = 'crm_opportunities';

    protected $fillable = [
        'lead_id',
        'branch_id',
        'title',
        'stage',
        'probability',
        'value',
        'expected_close_date',
        'salesperson_id',
        'lost_reason',
        'win_reason',
    ];

    protected function casts(): array
    {
        return [
            'probability' => 'integer',
            'value' => 'decimal:2',
            'expected_close_date' => 'date',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'opportunity_id');
    }
}
