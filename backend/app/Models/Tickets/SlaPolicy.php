<?php

namespace App\Models\Tickets;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaPolicy extends Model
{
    protected $fillable = [
        'name',
        'branch_id',
        'ticket_type_code',
        'priority',
        'customer_tier',
        'business_hours',
        'response_minutes',
        'resolution_minutes',
        'escalation_intervals',
        'pause_statuses',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'business_hours' => 'array',
            'escalation_intervals' => 'array',
            'pause_statuses' => 'array',
            'response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'sla_policy_id');
    }
}
