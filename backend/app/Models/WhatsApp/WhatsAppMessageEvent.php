<?php

namespace App\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessageEvent extends Model
{
    protected $table = 'whatsapp_message_events';

    protected $guarded = [];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'datetime'];
    }
}
