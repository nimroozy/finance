<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerCollectorOwnershipHistory extends Model
{
    protected $table = 'customer_collector_ownership_history';
    protected $fillable = [
        'ownership_id', 'customer_id', 'branch_id', 'collector_id', 'actor_id',
        'event', 'before', 'after', 'notes',
    ];
    protected function casts(): array { return ['before' => 'array', 'after' => 'array']; }
}
