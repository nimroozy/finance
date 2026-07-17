<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Escalation extends Model
{
    protected $fillable = [
        'branch_id', 'ticket_id', 'rule_code', 'level', 'status', 'reason',
        'notified_user_id', 'escalated_at', 'acknowledged_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'escalated_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
