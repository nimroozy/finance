<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectorDailyActivity extends Model
{
    protected $table = 'collector_daily_activity';

    protected $fillable = [
        'collector_id',
        'branch_id',
        'activity_date',
        'visits_count',
        'assignments_completed',
        'promises_created',
        'routes_completed',
        'distance_traveled_meters',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'visits_count' => 'integer',
            'assignments_completed' => 'integer',
            'promises_created' => 'integer',
            'routes_completed' => 'integer',
            'distance_traveled_meters' => 'decimal:2',
        ];
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
