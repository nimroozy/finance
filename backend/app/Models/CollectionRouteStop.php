<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionRouteStop extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'route_id',
        'customer_id',
        'assignment_id',
        'sequence',
        'status',
        'visit_id',
        'notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(CollectionRoute::class, 'route_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CustomerAssignment::class, 'assignment_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(CollectionVisit::class, 'visit_id');
    }
}
