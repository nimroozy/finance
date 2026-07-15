<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentComment extends Model
{
    protected $fillable = [
        'assignment_id',
        'user_id',
        'body',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CustomerAssignment::class, 'assignment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
