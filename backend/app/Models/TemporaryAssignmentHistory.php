<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemporaryAssignmentHistory extends Model
{
    protected $table = 'temporary_assignment_history';
    protected $fillable = ['temporary_assignment_id', 'customer_id', 'actor_id', 'event', 'before', 'after', 'notes'];
    protected function casts(): array { return ['before' => 'array', 'after' => 'array']; }
}
