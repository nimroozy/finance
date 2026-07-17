<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketIntakeSuggestion extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_AUTO_CREATED = 'auto_created';

    protected $fillable = [
        'branch_id', 'inbound_message_id', 'meta_message_id', 'conversation_id',
        'customer_id', 'ticket_id', 'status', 'suggested_type', 'suggested_category',
        'suggested_priority', 'subject', 'body', 'payload',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
