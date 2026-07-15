<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSyncAttempt extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DRY_RUN = 'dry_run';

    protected $fillable = [
        'payment_id',
        'attempt_number',
        'status',
        'zoho_payment_id',
        'request_payload',
        'response_payload',
        'error_message',
        'is_dry_run',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'is_dry_run' => 'boolean',
            'attempt_number' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
