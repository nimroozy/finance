<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoAccount extends Model
{
    protected $fillable = [
        'zoho_account_id', 'organization_id', 'name', 'account_type', 'account_code',
        'is_active', 'raw', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'raw' => 'array', 'last_synced_at' => 'datetime'];
    }
}
