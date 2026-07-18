<?php

namespace App\Models\WhatsApp;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    protected $table = 'whatsapp_conversations';

    protected $guarded = [];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function inboundMessages(): HasMany
    {
        return $this->hasMany(WhatsAppInboundMessage::class, 'conversation_id');
    }
}
