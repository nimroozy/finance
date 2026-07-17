<?php

namespace App\Models\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceStatusTransition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'service_id', 'from_commercial', 'to_commercial', 'from_operational', 'to_operational',
        'from_billing', 'to_billing', 'user_id', 'reason', 'source', 'comment', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
