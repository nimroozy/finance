<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoApiLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'zoho_sync_job_id',
        'request_type',
        'endpoint_name',
        'method',
        'entity_type',
        'local_entity_id',
        'zoho_entity_id',
        'http_status',
        'zoho_code',
        'attempt',
        'duration_ms',
        'success',
        'error_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function syncJob(): BelongsTo
    {
        return $this->belongsTo(ZohoSyncJob::class, 'zoho_sync_job_id');
    }
}
