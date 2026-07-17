<?php

namespace App\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppConnection extends Model
{
    protected $table = 'whatsapp_connections';

    protected $guarded = [];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return ['is_paused' => 'boolean', 'last_tested_at' => 'datetime', 'last_successful_api_call' => 'datetime', 'last_webhook_received' => 'datetime', 'access_token' => 'encrypted'];
    }
}
