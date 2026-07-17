<?php

namespace App\Support;

use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;

class PermissionRegistry
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(config('permissions_registry.permissions', []));
    }

    /**
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        return config('permissions_registry.aliases', []);
    }

    public static function canonical(string $permission): string
    {
        $aliases = self::aliases();

        return $aliases[$permission] ?? $permission;
    }

    /**
     * @param  list<string>|string  $permissions
     * @return list<string>
     */
    public static function expand(array|string $permissions): array
    {
        $list = is_array($permissions) ? $permissions : [$permissions];
        $expanded = [];
        foreach ($list as $permission) {
            $canonical = self::canonical($permission);
            $expanded[] = $canonical;
            foreach (self::aliases() as $alias => $target) {
                if ($target === $canonical) {
                    $expanded[] = $alias;
                }
            }
        }

        return array_values(array_unique($expanded));
    }

    public static function allows($user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        $canonical = self::canonical($permission);
        if (method_exists($user, 'can') && $user->can($canonical)) {
            return true;
        }

        foreach (self::aliases() as $alias => $target) {
            if ($target === $canonical && method_exists($user, 'can') && $user->can($alias)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register Gate checks so alias permission names resolve to canonical ones.
     */
    public static function registerGates(): void
    {
        foreach (self::aliases() as $alias => $canonical) {
            Gate::define($alias, function ($user) use ($canonical) {
                return $user->can($canonical);
            });
        }
    }

    /**
     * Ensure alias permission rows exist (optional; gates cover runtime).
     */
    public static function syncAliasPermissions(): void
    {
        foreach (array_keys(self::aliases()) as $alias) {
            Permission::findOrCreate($alias, 'web');
        }
    }
}
