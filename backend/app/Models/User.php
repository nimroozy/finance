<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    public const ROLE_SUPER_ADMIN = 'Super Administrator';

    public const ROLE_CENTRAL_FINANCE = 'Central Finance Administrator';

    public const ROLE_BRANCH_MANAGER = 'Branch Finance Manager';

    public const ROLE_COLLECTOR = 'Collector';

    public const ROLE_AUDITOR = 'Auditor';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'phone',
        'locale',
        'status',
        'password',
        'force_password_change',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'force_password_change' => 'boolean',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public function isCentralFinanceAdmin(): bool
    {
        return $this->hasRole(self::ROLE_CENTRAL_FINANCE);
    }

    /**
     * @return list<int>
     */
    public function branchIds(): array
    {
        return $this->branches()
            ->withoutGlobalScopes()
            ->pluck('branches.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeDisabled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DISABLED);
    }
}
