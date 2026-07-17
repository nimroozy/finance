<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserUiPreference extends Model
{
    public const LOCALE_EN = 'en';

    public const LOCALE_FA = 'fa';

    public const THEME_LIGHT = 'light';

    public const THEME_DARK = 'dark';

    public const THEME_SYSTEM = 'system';

    public const CALENDAR_GREGORIAN = 'gregorian';

    public const CALENDAR_SOLAR_HIJRI = 'solar_hijri';

    public const CALENDAR_BOTH = 'both';

    public const LOCALES = [
        self::LOCALE_EN,
        self::LOCALE_FA,
    ];

    public const THEMES = [
        self::THEME_LIGHT,
        self::THEME_DARK,
        self::THEME_SYSTEM,
    ];

    public const CALENDAR_SYSTEMS = [
        self::CALENDAR_GREGORIAN,
        self::CALENDAR_SOLAR_HIJRI,
        self::CALENDAR_BOTH,
    ];

    public const RECENT_APPS_LIMIT = 12;

    protected $fillable = [
        'user_id',
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'favorite_app_ids' => 'array',
            'recent_app_ids' => 'array',
            'collapsed_nav_groups' => 'array',
            'bottom_nav_overrides' => 'array',
            'notification_prefs' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'locale' => $this->locale,
            'theme' => $this->theme,
            'default_app_id' => $this->default_app_id,
            'favorite_app_ids' => array_values($this->favorite_app_ids ?? []),
            'recent_app_ids' => array_values($this->recent_app_ids ?? []),
            'date_format' => $this->date_format,
            'calendar_system' => $this->calendar_system,
            'collapsed_nav_groups' => $this->collapsed_nav_groups,
            'bottom_nav_overrides' => $this->bottom_nav_overrides,
            'notification_prefs' => $this->notification_prefs,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Compact summary for auth /me payloads.
     *
     * @return array<string, mixed>
     */
    public function toSummaryArray(): array
    {
        return [
            'locale' => $this->locale,
            'theme' => $this->theme,
            'default_app_id' => $this->default_app_id,
            'favorite_app_ids' => array_values($this->favorite_app_ids ?? []),
            'recent_app_ids' => array_values($this->recent_app_ids ?? []),
            'date_format' => $this->date_format,
            'calendar_system' => $this->calendar_system,
        ];
    }
}
