<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZohoConnection extends Model
{
    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'connected_by',
        'organization_id',
        'organization_name',
        'accounts_domain',
        'api_domain',
        'data_center',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'status',
        'last_connected_at',
        'last_error',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_connected_at' => 'datetime',
        ];
    }

    public function connectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(ZohoOrganization::class);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED
            && filled($this->access_token)
            && filled($this->refresh_token);
    }

    public function tokenNeedsRefresh(?int $bufferSeconds = null): bool
    {
        if ($this->token_expires_at === null) {
            return true;
        }

        $buffer = $bufferSeconds ?? (int) config('zoho.token_refresh_buffer_seconds', 120);

        return $this->token_expires_at->lte(now()->addSeconds($buffer));
    }

    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }
}
