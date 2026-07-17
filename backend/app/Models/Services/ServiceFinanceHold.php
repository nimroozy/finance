<?php

namespace App\Models\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceFinanceHold extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_RELEASED = 'released';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'service_id', 'hold_type', 'reason', 'zoho_invoice_ref', 'outstanding_snapshot',
        'effective_at', 'review_at', 'approved_by', 'released_by', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'outstanding_snapshot' => 'array',
            'effective_at' => 'datetime',
            'review_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
