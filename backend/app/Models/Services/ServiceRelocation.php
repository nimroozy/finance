<?php

namespace App\Models\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRelocation extends Model
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'service_id', 'status', 'old_location_id', 'new_location_id',
        'old_tower_id', 'new_tower_id', 'coverage_check_id', 'survey_id', 'quote_id', 'notes',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function oldLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'old_location_id');
    }

    public function newLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'new_location_id');
    }
}
