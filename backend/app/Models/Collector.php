<?php

namespace App\Models;

use Database\Factories\CollectorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collector extends Model
{
    /** @use HasFactory<CollectorFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employee_code',
        'max_active_assignments',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_active_assignments' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CustomerAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->where('is_active', true);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(CollectionVisit::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(CollectionRoute::class);
    }
}
