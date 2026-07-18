<?php

namespace App\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppOptOut extends Model
{
    protected $table = 'whatsapp_opt_outs';

    protected $guarded = [];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['opted_out_at' => 'datetime'];
    }
}
