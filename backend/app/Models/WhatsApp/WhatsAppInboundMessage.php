<?php

namespace App\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppInboundMessage extends Model
{
    protected $table = 'whatsapp_inbound_messages';

    protected $guarded = [];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'received_at' => 'datetime', 'read_at' => 'datetime'];
    }
}
