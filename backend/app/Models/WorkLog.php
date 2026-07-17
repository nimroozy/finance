<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkLog extends Model
{
    protected $fillable = [
        'branch_id', 'ticket_id', 'task_id', 'author_id', 'visibility',
        'body', 'minutes_spent', 'is_locked', 'locked_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'minutes_spent' => 'integer',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(WorkLogAmendment::class);
    }
}
