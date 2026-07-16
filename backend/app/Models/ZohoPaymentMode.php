<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoPaymentMode extends Model
{
    protected $fillable = [
        'zoho_payment_mode_id', 'organization_id', 'name', 'is_active', 'raw', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'raw' => 'array', 'last_synced_at' => 'datetime'];
    }
}
