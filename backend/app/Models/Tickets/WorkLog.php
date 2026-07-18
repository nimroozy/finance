<?php

namespace App\Models\Tickets;

use App\Models\Org\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'task_id',
        'user_id',
        'department_id',
        'started_at',
        'ended_at',
        'duration_minutes',
        'work_type',
        'internal_note',
        'customer_visible_note',
        'gps_lat',
        'gps_lng',
        'equipment_used',
        'result',
        'follow_up_required',
        'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_minutes' => 'integer',
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
            'follow_up_required' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(WorkLogAmendment::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(OperationalAttachment::class, 'attachable');
    }
}
