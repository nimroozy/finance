<?php

namespace App\Models\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSuspension extends Model
{
    public const TYPE_VOLUNTARY = 'voluntary';

    public const TYPE_FINANCE = 'finance';

    public const TYPE_TECHNICAL = 'technical';

    public const TYPE_ABUSE = 'abuse';

    protected $fillable = [
        'service_id', 'type', 'reason', 'effective_at', 'expected_reactivation_at',
        'approved_by', 'requested_by', 'finance_reference', 'ticket_id', 'task_id',
        'notes', 'notification_status', 'reactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'expected_reactivation_at' => 'datetime',
            'reactivated_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
