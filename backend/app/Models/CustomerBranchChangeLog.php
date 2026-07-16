<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerBranchChangeLog extends Model
{
    protected $fillable = [
        'customer_id', 'old_branch_id', 'new_branch_id', 'source', 'actor_type',
        'actor_id', 'reason', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
