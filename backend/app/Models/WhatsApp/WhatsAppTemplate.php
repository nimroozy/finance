<?php

namespace App\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $guarded = [];

    protected $hidden = [];

    protected function casts(): array
    {
        return [];
    }
}
