<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZohoSyncJob extends Model
{
    public const TYPE_CUSTOMERS = 'customers';

    public const TYPE_INVOICES = 'invoices';

    public const TYPE_FULL = 'full';

    public const TYPE_TOKEN_REFRESH = 'token_refresh';

    public const TYPE_STRUCTURE = 'structure';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PARTIALLY_COMPLETED = 'partially_completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'type',
        'status',
        'started_at',
        'finished_at',
        'triggered_by',
        'progress',
        'stats',
        'error_message',
        'parent_job_id',
        'error_class',
        'retry_count',
        'next_retry_at',
        'circuit_open',
        'cursor_from',
        'cursor_to',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'progress' => 'array',
            'stats' => 'array',
            'retry_count' => 'integer',
            'next_retry_at' => 'datetime',
            'circuit_open' => 'boolean',
            'cursor_from' => 'datetime',
            'cursor_to' => 'datetime',
        ];
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_job_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_job_id');
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ZohoApiLog::class);
    }

    public function markRunning(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => $this->started_at ?? now(),
            'error_message' => null,
        ]);
    }

    /**
     * @param  array{processed?:int,created?:int,updated?:int,failed?:int}  $stats
     */
    public function markCompleted(array $stats = [], ?string $status = null): void
    {
        $failed = (int) ($stats['failed'] ?? 0);
        $resolved = $status ?? ($failed > 0 ? self::STATUS_PARTIALLY_COMPLETED : self::STATUS_COMPLETED);

        $this->update([
            'status' => $resolved,
            'finished_at' => now(),
            'stats' => array_merge([
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'failed' => 0,
            ], $stats),
        ]);
    }

    public function markFailed(string $message, ?string $errorClass = null, bool $circuitOpen = false): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'finished_at' => now(),
            'error_message' => $message,
            'error_class' => $errorClass,
            'circuit_open' => $circuitOpen,
        ]);
    }
}
