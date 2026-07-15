<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoPaymentModeMapping extends Model
{
    protected $fillable = [
        'zoho_payment_mode_id',
        'name',
        'local_method',
        'payment_method_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function paymentMethod(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
