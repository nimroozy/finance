<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoCircuitBreaker extends Model
{
    protected $fillable = [
        'sync_type', 'state', 'failure_count', 'last_error_class', 'last_error_message',
        'opened_at', 'resume_after',
    ];

    protected function casts(): array
    {
        return [
            'failure_count' => 'integer',
            'opened_at' => 'datetime',
            'resume_after' => 'datetime',
        ];
    }
}
