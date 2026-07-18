<?php

namespace App\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppNotificationRule extends Model
{
    protected $table = 'whatsapp_notification_rules';

    protected $guarded = [];

    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'in_app_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
            'min_amount' => 'decimal:2',
        ];
    }
}
