<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoReportingTagOption extends Model
{
    protected $fillable = [
        'zoho_tag_option_id', 'zoho_tag_id', 'name', 'is_active', 'raw', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'raw' => 'array', 'last_synced_at' => 'datetime'];
    }
}
