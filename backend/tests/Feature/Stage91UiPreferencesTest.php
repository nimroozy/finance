<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserUiPreference;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Stage91UiPreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_get_or_create_defaults_on_show(): void
    {
        $user = User::factory()->create(['force_password_change' => false]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/ui-preferences')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.theme', UserUiPreference::THEME_SYSTEM)
            ->assertJsonPath('data.locale', null)
            ->assertJsonPath('data.favorite_app_ids', [])
            ->assertJsonPath('data.recent_app_ids', []);

        $this->assertDatabaseHas('user_ui_preferences', [
            'user_id' => $user->id,
            'theme' => 'system',
        ]);
    }

    public function test_update_preferences(): void
    {
        $user = User::factory()->create(['force_password_change' => false]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me/ui-preferences', [
            'locale' => 'fa',
            'theme' => 'dark',
            'default_app_id' => 'crm',
            'date_format' => 'Y/m/d',
            'calendar_system' => 'solar_hijri',
            'collapsed_nav_groups' => ['customers' => true],
            'notification_prefs' => ['desktop' => true],
        ])
            ->assertOk()
            ->assertJsonPath('data.locale', 'fa')
            ->assertJsonPath('data.theme', 'dark')
            ->assertJsonPath('data.default_app_id', 'crm')
            ->assertJsonPath('data.calendar_system', 'solar_hijri')
            ->assertJsonPath('data.date_format', 'Y/m/d')
            ->assertJsonPath('data.collapsed_nav_groups.customers', true)
            ->assertJsonPath('data.notification_prefs.desktop', true);
    }

    public function test_rejects_invalid_theme_and_locale(): void
    {
        $user = User::factory()->create(['force_password_change' => false]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me/ui-preferences', [
            'theme' => 'neon',
        ])->assertStatus(422);

        $this->putJson('/api/v1/me/ui-preferences', [
            'locale' => 'de',
        ])->assertStatus(422);

        $this->putJson('/api/v1/me/ui-preferences', [
            'calendar_system' => 'lunar',
        ])->assertStatus(422);
    }

    public function test_favorites_add_remove_and_reorder(): void
    {
        $user = User::factory()->create(['force_password_change' => false]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/ui-preferences/favorites/inventory')
            ->assertOk()
            ->assertJsonPath('data.favorite_app_ids.0', 'inventory');

        $this->postJson('/api/v1/me/ui-preferences/favorites/crm')
            ->assertOk()
            ->assertJsonPath('data.favorite_app_ids', ['inventory', 'crm']);

        $this->postJson('/api/v1/me/ui-preferences/favorites/tickets')
            ->assertOk();

        $this->putJson('/api/v1/me/ui-preferences/favorites/reorder', [
            'ids' => ['tickets', 'crm', 'inventory'],
        ])
            ->assertOk()
            ->assertJsonPath('data.favorite_app_ids', ['tickets', 'crm', 'inventory']);

        $this->deleteJson('/api/v1/me/ui-preferences/favorites/crm')
            ->assertOk()
            ->assertJsonPath('data.favorite_app_ids', ['tickets', 'inventory']);
    }

    public function test_record_recent_app_open_dedupes_and_caps(): void
    {
        $user = User::factory()->create(['force_password_change' => false]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/ui-preferences/recent/crm')
            ->assertOk()
            ->assertJsonPath('data.recent_app_ids.0', 'crm');

        $this->postJson('/api/v1/me/ui-preferences/recent/inventory')
            ->assertOk()
            ->assertJsonPath('data.recent_app_ids', ['inventory', 'crm']);

        $this->postJson('/api/v1/me/ui-preferences/recent/crm')
            ->assertOk()
            ->assertJsonPath('data.recent_app_ids', ['crm', 'inventory']);

        for ($i = 0; $i < 15; $i++) {
            $this->postJson('/api/v1/me/ui-preferences/recent/app-'.$i)->assertOk();
        }

        $recent = $this->getJson('/api/v1/me/ui-preferences')->json('data.recent_app_ids');
        $this->assertCount(UserUiPreference::RECENT_APPS_LIMIT, $recent);
        $this->assertSame('app-14', $recent[0]);
    }

    public function test_auth_me_includes_ui_preferences_summary(): void
    {
        $user = User::factory()->create(['force_password_change' => false]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me/ui-preferences', [
            'theme' => 'light',
            'locale' => 'en',
            'default_app_id' => 'dashboard',
        ])->assertOk();

        $this->postJson('/api/v1/me/ui-preferences/favorites/crm')->assertOk();

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.ui_preferences.theme', 'light')
            ->assertJsonPath('data.ui_preferences.locale', 'en')
            ->assertJsonPath('data.ui_preferences.default_app_id', 'dashboard')
            ->assertJsonPath('data.ui_preferences.favorite_app_ids.0', 'crm');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/ui-preferences')->assertUnauthorized();
        $this->putJson('/api/v1/me/ui-preferences', ['theme' => 'dark'])->assertUnauthorized();
    }
}
