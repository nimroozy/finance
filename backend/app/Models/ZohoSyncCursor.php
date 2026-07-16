<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoSyncCursor extends Model
{
    protected $fillable = [
        'entity', 'successful_cursor', 'last_requested_from', 'last_requested_to',
        'last_success_at', 'last_error', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'successful_cursor' => 'datetime',
            'last_requested_from' => 'datetime',
            'last_requested_to' => 'datetime',
            'last_success_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
