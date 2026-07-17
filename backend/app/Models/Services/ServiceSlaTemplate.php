<?php

namespace App\Models\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceSlaTemplate extends Model
{
    protected $fillable = [
        'name', 'response_minutes', 'resolution_minutes', 'availability_target',
        'support_hours', 'escalation_rules', 'priority_matrix', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'support_hours' => 'array',
            'escalation_rules' => 'array',
            'priority_matrix' => 'array',
            'availability_target' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'sla_template_id');
    }
}
