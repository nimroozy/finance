<?php

namespace App\Models\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRenewal extends Model
{
    protected $fillable = [
        'service_id', 'old_expiration', 'new_expiration', 'term_months', 'price',
        'approved_by', 'zoho_invoice_ref', 'payment_ref', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'old_expiration' => 'date',
            'new_expiration' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
