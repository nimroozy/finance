<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAssignmentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'task_id', 'action', 'actor_id', 'from_user_id', 'to_user_id', 'reason', 'meta', 'created_at',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
