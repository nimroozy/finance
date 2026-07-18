<?php

namespace App\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessageRecipient extends Model
{
    protected $table = 'whatsapp_message_recipients';

    protected $guarded = [];

    protected $hidden = [];

    protected function casts(): array
    {
        return [];
    }
}
