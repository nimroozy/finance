<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaPolicy extends Model
{
    protected $fillable = [
        'branch_id', 'code', 'name', 'priority',
        'first_response_minutes', 'resolution_minutes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'first_response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
