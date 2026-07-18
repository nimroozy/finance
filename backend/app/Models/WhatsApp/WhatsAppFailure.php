<?php

namespace App\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppFailure extends Model
{
    protected $table = 'whatsapp_failures';

    protected $guarded = [];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['is_permanent' => 'boolean', 'context' => 'array', 'resolved_at' => 'datetime'];
    }
}
