<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBranchMappingHistory extends Model
{
    protected $fillable = [
        'customer_id',
        'from_branch_id',
        'to_branch_id',
        'resolution_source',
        'confidence',
        'detected_prefix',
        'dry_run',
        'applied',
        'protected_history',
        'evidence',
        'explanation',
        'actor_id',
        'run_id',
    ];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'applied' => 'boolean',
            'protected_history' => 'boolean',
            'evidence' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
