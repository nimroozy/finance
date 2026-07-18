<?php

namespace App\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplateLanguage extends Model
{
    protected $table = 'whatsapp_template_languages';

    protected $guarded = [];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['components' => 'array'];
    }
}
