<?php

namespace App\Models\Tickets;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StaffActionToken extends Model
{
    protected $fillable = [
        'token_hash',
        'user_id',
        'action',
        'payload',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isUsed() && ! $this->isExpired();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{token: string, model: self}
     */
    public static function issue(User $user, string $action, ?array $payload = null, int $ttlMinutes = 60): array
    {
        $plain = Str::random(64);

        $model = static::query()->create([
            'token_hash' => hash('sha256', $plain),
            'user_id' => $user->id,
            'action' => $action,
            'payload' => $payload,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return ['token' => $plain, 'model' => $model];
    }
}
