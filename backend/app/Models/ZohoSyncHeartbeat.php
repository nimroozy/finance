<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoSyncHeartbeat extends Model
{
    protected $fillable = [
        'component', 'status', 'last_heartbeat_at', 'last_start_at', 'last_completion_at',
        'last_duration_ms', 'next_expected_at', 'last_error', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
            'last_start_at' => 'datetime',
            'last_completion_at' => 'datetime',
            'next_expected_at' => 'datetime',
            'last_duration_ms' => 'integer',
            'meta' => 'array',
        ];
    }
}
