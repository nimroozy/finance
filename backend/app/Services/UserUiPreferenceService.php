<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserUiPreference;
use InvalidArgumentException;

class UserUiPreferenceService
{
    public function getOrCreate(User $user): UserUiPreference
    {
        return UserUiPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'locale' => null,
                'theme' => UserUiPreference::THEME_SYSTEM,
                'default_app_id' => null,
                'favorite_app_ids' => [],
                'recent_app_ids' => [],
                'date_format' => null,
                'calendar_system' => null,
                'collapsed_nav_groups' => null,
                'bottom_nav_overrides' => null,
                'notification_prefs' => null,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): UserUiPreference
    {
        $prefs = $this->getOrCreate($user);

        $allowed = [
            'locale',
            'theme',
            'default_app_id',
            'favorite_app_ids',
            'recent_app_ids',
            'date_format',
            'calendar_system',
            'collapsed_nav_groups',
            'bottom_nav_overrides',
            'notification_prefs',
        ];

        $data = array_intersect_key($attributes, array_flip($allowed));

        if (array_key_exists('locale', $data) && $data['locale'] !== null) {
            if (! in_array($data['locale'], UserUiPreference::LOCALES, true)) {
                throw new InvalidArgumentException('Invalid locale.');
            }
        }

        if (array_key_exists('theme', $data)) {
            if (! in_array($data['theme'], UserUiPreference::THEMES, true)) {
                throw new InvalidArgumentException('Invalid theme.');
            }
        }

        if (array_key_exists('calendar_system', $data) && $data['calendar_system'] !== null) {
            if (! in_array($data['calendar_system'], UserUiPreference::CALENDAR_SYSTEMS, true)) {
                throw new InvalidArgumentException('Invalid calendar_system.');
            }
        }

        foreach (['favorite_app_ids', 'recent_app_ids'] as $listKey) {
            if (array_key_exists($listKey, $data)) {
                $data[$listKey] = $this->normalizeAppIdList($data[$listKey] ?? []);
            }
        }

        if (array_key_exists('default_app_id', $data) && $data['default_app_id'] !== null) {
            $data['default_app_id'] = $this->normalizeAppId((string) $data['default_app_id']);
        }

        $prefs->fill($data);
        $prefs->save();

        return $prefs->fresh();
    }

    public function recordAppOpen(User $user, string $appId): UserUiPreference
    {
        $appId = $this->normalizeAppId($appId);
        $prefs = $this->getOrCreate($user);

        $recent = array_values(array_filter(
            $prefs->recent_app_ids ?? [],
            fn ($id) => $id !== $appId
        ));
        array_unshift($recent, $appId);
        $prefs->recent_app_ids = array_slice($recent, 0, UserUiPreference::RECENT_APPS_LIMIT);
        $prefs->save();

        return $prefs->fresh();
    }

    public function toggleFavorite(User $user, string $appId, bool $favorite = true): UserUiPreference
    {
        $appId = $this->normalizeAppId($appId);
        $prefs = $this->getOrCreate($user);
        $favorites = array_values($prefs->favorite_app_ids ?? []);

        if ($favorite) {
            if (! in_array($appId, $favorites, true)) {
                $favorites[] = $appId;
            }
        } else {
            $favorites = array_values(array_filter($favorites, fn ($id) => $id !== $appId));
        }

        $prefs->favorite_app_ids = $favorites;
        $prefs->save();

        return $prefs->fresh();
    }

    /**
     * @param  list<string>  $ids
     */
    public function reorderFavorites(User $user, array $ids): UserUiPreference
    {
        $prefs = $this->getOrCreate($user);
        $normalized = $this->normalizeAppIdList($ids);
        $existing = array_values($prefs->favorite_app_ids ?? []);

        // Keep only ids that are already favorites; append any missing favorites at the end.
        $ordered = [];
        foreach ($normalized as $id) {
            if (in_array($id, $existing, true) && ! in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
        }
        foreach ($existing as $id) {
            if (! in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
        }

        $prefs->favorite_app_ids = $ordered;
        $prefs->save();

        return $prefs->fresh();
    }

    private function normalizeAppId(string $appId): string
    {
        $appId = trim($appId);
        if ($appId === '' || strlen($appId) > 64) {
            throw new InvalidArgumentException('Invalid app id.');
        }

        return $appId;
    }

    /**
     * @param  mixed  $ids
     * @return list<string>
     */
    private function normalizeAppIdList(mixed $ids): array
    {
        if (! is_array($ids)) {
            throw new InvalidArgumentException('App id list must be an array.');
        }

        $normalized = [];
        foreach ($ids as $id) {
            if (! is_string($id) && ! is_numeric($id)) {
                continue;
            }
            $value = $this->normalizeAppId((string) $id);
            if (! in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }
}
