<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkLogAmendment extends Model
{
    protected $fillable = [
        'work_log_id', 'amended_by', 'previous_body', 'new_body', 'reason',
    ];

    public function workLog(): BelongsTo
    {
        return $this->belongsTo(WorkLog::class);
    }
}
