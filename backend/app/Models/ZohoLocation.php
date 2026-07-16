<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoLocation extends Model
{
    protected $fillable = [
        'zoho_location_id', 'organization_id', 'name', 'code', 'is_active',
        'is_primary', 'raw', 'sync_status', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_primary' => 'boolean', 'raw' => 'array', 'last_synced_at' => 'datetime'];
    }
}
