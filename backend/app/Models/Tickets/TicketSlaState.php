<?php

namespace App\Models\Tickets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSlaState extends Model
{
    protected $fillable = [
        'ticket_id',
        'response_state',
        'resolution_state',
        'response_remaining_seconds',
        'resolution_remaining_seconds',
        'paused_at',
        'pause_total_seconds',
        'breached_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'response_remaining_seconds' => 'integer',
            'resolution_remaining_seconds' => 'integer',
            'paused_at' => 'datetime',
            'pause_total_seconds' => 'integer',
            'breached_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
