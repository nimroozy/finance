<?php

namespace App\Models\Tickets;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalationRule extends Model
{
    protected $fillable = [
        'name',
        'branch_id',
        'ticket_type_code',
        'priority',
        'department_code',
        'trigger_type',
        'trigger_after_minutes',
        'from_status',
        'escalate_to_role',
        'escalate_to_department_code',
        'escalate_to_user_id',
        'notify_whatsapp',
        'is_active',
        'sort_order',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'trigger_after_minutes' => 'integer',
            'notify_whatsapp' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'meta' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function escalateToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalate_to_user_id');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Escalation::class);
    }
}
