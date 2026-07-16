<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryCollectionAssignment extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'customer_id', 'branch_id', 'temporary_collector_id', 'permanent_collector_id',
        'start_date', 'end_date', 'reason', 'priority', 'status', 'created_by', 'approved_by',
        'is_active', 'expired_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean',
            'expired_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function temporaryCollector(): BelongsTo { return $this->belongsTo(Collector::class, 'temporary_collector_id'); }
    public function permanentCollector(): BelongsTo { return $this->belongsTo(Collector::class, 'permanent_collector_id'); }

    public function scopeCurrentlyActive($query, $on = null)
    {
        $on = $on ? \Carbon\Carbon::parse($on)->toDateString() : now()->toDateString();
        return $query->where('is_active', true)->where('status', self::STATUS_ACTIVE)
            ->whereDate('start_date', '<=', $on)->whereDate('end_date', '>=', $on);
    }
}
