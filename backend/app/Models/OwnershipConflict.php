<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OwnershipConflict extends Model
{
    protected $fillable = [
        'customer_id', 'branch_id', 'conflict_type', 'status', 'details', 'resolved_by', 'resolved_at',
    ];
    protected function casts(): array
    {
        return ['details' => 'array', 'resolved_at' => 'datetime'];
    }
}
