<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OwnershipSyncEvent extends Model
{
    protected $fillable = ['customer_id', 'event_type', 'source', 'payload'];
    protected function casts(): array { return ['payload' => 'array']; }
}
