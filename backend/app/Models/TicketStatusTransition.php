<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketStatusTransition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ticket_id', 'from_status', 'to_status', 'changed_by', 'reason', 'meta', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
