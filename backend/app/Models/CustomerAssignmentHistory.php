<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAssignmentHistory extends Model
{
    public $timestamps = false;

    protected $table = 'customer_assignment_history';

    protected $fillable = [
        'assignment_id',
        'event',
        'from_status',
        'to_status',
        'changed_by',
        'from_collector_id',
        'to_collector_id',
        'notes',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CustomerAssignment::class, 'assignment_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
