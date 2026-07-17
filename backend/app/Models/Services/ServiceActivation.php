<?php

namespace App\Models\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceActivation extends Model
{
    protected $fillable = [
        'service_id', 'activated_by', 'activated_at', 'checklist', 'notes', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'checklist' => 'array',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }
}
