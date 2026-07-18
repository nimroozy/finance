<?php

namespace App\Models\Tickets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaBreachEvent extends Model
{
    protected $fillable = [
        'ticket_id',
        'sla_policy_id',
        'breach_type',
        'priority',
        'due_at',
        'breached_at',
        'overdue_seconds',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'breached_at' => 'datetime',
            'overdue_seconds' => 'integer',
            'meta' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class, 'sla_policy_id');
    }
}
