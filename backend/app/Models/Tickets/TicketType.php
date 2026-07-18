<?php

namespace App\Models\Tickets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketType extends Model
{
    protected $fillable = [
        'code',
        'name_en',
        'name_fa',
        'default_department_code',
        'default_priority',
        'sla_policy_id',
        'requires_customer',
        'requires_site',
        'expects_field_visit',
        'requires_finance_review',
        'requires_radius_review',
        'required_fields',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_customer' => 'boolean',
            'requires_site' => 'boolean',
            'expects_field_visit' => 'boolean',
            'requires_finance_review' => 'boolean',
            'requires_radius_review' => 'boolean',
            'required_fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class, 'sla_policy_id');
    }
}
