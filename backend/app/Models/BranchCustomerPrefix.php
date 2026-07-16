<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchCustomerPrefix extends Model
{
    protected $fillable = [
        'branch_id',
        'prefix',
        'normalized_prefix',
        'active',
        'priority',
        'description',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public static function normalizePrefix(string $prefix): string
    {
        return strtoupper(trim($prefix));
    }
}
