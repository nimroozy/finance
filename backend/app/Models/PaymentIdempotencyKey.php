<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentIdempotencyKey extends Model
{
    protected $fillable = [
        'key',
        'user_id',
        'request_hash',
        'payment_id',
        'response_json',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_json' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
