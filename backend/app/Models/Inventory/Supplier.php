<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_suppliers';

    protected $fillable = [
        'code', 'name', 'contact_name', 'phone', 'whatsapp', 'email', 'address',
        'payment_terms', 'lead_time_days', 'rating', 'zoho_vendor_id',
        'zoho_sync_status', 'zoho_idempotency_key', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
