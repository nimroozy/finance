<?php

namespace App\Models\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceChangeRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'service_id', 'type', 'current_values', 'requested_values', 'requested_by',
        'effective_at', 'reason', 'finance_status', 'technical_status', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'current_values' => 'array',
            'requested_values' => 'array',
            'effective_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
