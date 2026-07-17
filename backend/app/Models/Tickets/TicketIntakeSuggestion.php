<?php

namespace App\Models\Tickets;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\WhatsApp\WhatsAppConversation;
use App\Models\WhatsApp\WhatsAppInboundMessage;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToBranchScope::class])]
class TicketIntakeSuggestion extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_TICKET_CREATED = 'ticket_created';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_APPENDED = 'appended';

    protected $fillable = [
        'whatsapp_inbound_message_id',
        'conversation_id',
        'branch_id',
        'customer_id',
        'status',
        'ticket_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function inboundMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInboundMessage::class, 'whatsapp_inbound_message_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
