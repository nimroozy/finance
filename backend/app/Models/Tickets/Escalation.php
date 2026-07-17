<?php

namespace App\Models\Tickets;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Escalation extends Model
{
    protected $fillable = [
        'ticket_id',
        'escalation_rule_id',
        'level',
        'status',
        'from_user_id',
        'to_user_id',
        'to_department_code',
        'reason',
        'notes',
        'escalated_at',
        'acknowledged_at',
        'resolved_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'escalated_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(EscalationRule::class, 'escalation_rule_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
