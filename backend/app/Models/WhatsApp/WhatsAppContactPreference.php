<?php

namespace App\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppContactPreference extends Model
{
    protected $table = 'whatsapp_contact_preferences';

    protected $guarded = [];

    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'whatsapp_enabled' => 'boolean',
            'messaging_allowed' => 'boolean',
            'receipts_enabled' => 'boolean',
            'reminders_enabled' => 'boolean',
            'promotional_enabled' => 'boolean',
            'consented_at' => 'datetime',
            'opted_out_at' => 'datetime',
        ];
    }
}
