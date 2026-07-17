<?php

namespace App\Models\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCancellation extends Model
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_EQUIPMENT_RECOVERY = 'equipment_recovery';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'service_id', 'reason', 'requested_by', 'approved_by', 'equipment_return_required',
        'equipment_recovered_at', 'equipment_recovered_by',
        'status', 'notes', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'equipment_return_required' => 'boolean',
            'cancelled_at' => 'datetime',
            'equipment_recovered_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
