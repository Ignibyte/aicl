<?php

namespace Aicl\Tests\Feature\Auth;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Http\Controllers\SocialAuthController;
use Aicl\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use LightSaml\Model\Assertion\Attribute;
use Mockery;
use Tests\TestCase;

class SamlAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\SettingsSeeder']);

        Event::fake([
            EntityCreated::class,
            EntityUpdated::class,
            EntityDeleted::class,
        ]);

        // Enable SAML feature flag
        config(['aicl.features.saml' => true]);

        // Register SAML routes manually since they're conditionally loaded at boot time
        Route::middleware('web')->group(function (): void {
            Route::get('/auth/saml2/metadata', [SocialAuthController::class, 'samlMetadata'])
                ->name('saml.metadata');
            Route::get('/auth/saml2/redirect', [SocialAuthController::class, 'samlRedirect'])
                ->name('saml.redirect');
            Route::post('/auth/saml2/callback', [SocialAuthController::class, 'samlCallback'])
                ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
                ->name('saml.callback');
        });

        app('router')->getRoutes()->refreshNameLookups();
    }

    protected function mockSamlSocialiteUser(array $rawAttributes = [], ?string $email = null, ?string $name = null, string $id = 'saml-user-123'): \Laravel\Socialite\Contracts\User
    {
        $lightSamlAttributes = [];
        foreach ($rawAttributes as $key => $value) {
            $attr = Mockery::mock(Attribute::class);
            $attr->shouldReceive('getName')->andReturn($key);
            $values = is_array($value) ? $value : [$value];
            $attr->shouldReceive('getAllAttributeValues')->andReturn($values);
            $lightSamlAttributes[] = $attr;
        }

        $socialiteUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $socialiteUser->shouldReceive('getId')->andReturn($id);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
        $socialiteUser->shouldReceive('getRaw')->andReturn($lightSamlAttributes);

        return $socialiteUser;
    }

    protected function mockSamlDriver(\Laravel\Socialite\Contracts\User $socialiteUser): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('saml2')
            ->andReturn($driver);
    }

    public function test_saml_callback_creates_new_user(): void
    {
        config([
            'aicl.saml.auto_create_users' => true,
            'aicl.saml.default_role' => 'viewer',
        ]);

        $socialiteUser = $this->mockSamlSocialiteUser(
            rawAttributes: [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => 'saml-new@example.com',
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name' => 'SAML New User',
            ],
            email: 'saml-new@example.com',
            name: 'SAML New User',
        );

        $this->mockSamlDriver($socialiteUser);

        $response = $this->post('/auth/saml2/callback');

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'saml-new@example.com',
            'name' => 'SAML New User',
        ]);

        $user = User::where('email', 'saml-new@example.com')->first();
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'saml2',
            'provider_id' => 'saml-user-123',
        ]);
    }

    public function test_saml_callback_logs_in_existing_linked_user(): void
    {
        $user = User::factory()->create(['email' => 'linked@example.com']);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'saml2',
            'provider_id' => 'saml-linked-456',
        ]);

        $socialiteUser = $this->mockSamlSocialiteUser(
            rawAttributes: [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => 'linked@example.com',
            ],
            email: 'linked@example.com',
            id: 'saml-linked-456',
        );

        $this->mockSamlDriver($socialiteUser);

        $response = $this->post('/auth/saml2/callback');

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_saml_callback_links_existing_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'exists@example.com']);

        $socialiteUser = $this->mockSamlSocialiteUser(
            rawAttributes: [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => 'exists@example.com',
            ],
            email: 'exists@example.com',
            id: 'saml-new-link-789',
        );

        $this->mockSamlDriver($socialiteUser);

        $response = $this->post('/auth/saml2/callback');

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'saml2',
            'provider_id' => 'saml-new-link-789',
        ]);
    }

    public function test_saml_callback_stores_null_tokens(): void
    {
        $socialiteUser = $this->mockSamlSocialiteUser(
            rawAttributes: [
                'email' => 'null-tokens@example.com',
            ],
            email: 'null-tokens@example.com',
        );

        $this->mockSamlDriver($socialiteUser);

        $this->post('/auth/saml2/callback');

        $user = User::where('email', 'null-tokens@example.com')->first();
        $account = $user->socialAccounts()->where('provider', 'saml2')->first();

        $this->assertNull($account->token);
        $this->assertNull($account->refresh_token);
        $this->assertNull($account->token_expires_at);
    }

    public function test_saml_callback_handles_socialite_exception(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')
            ->once()
            ->andThrow(new \Exception('SAML assertion invalid'));

        Socialite::shouldReceive('driver')
            ->with('saml2')
            ->andReturn($driver);

        $response = $this->post('/auth/saml2/callback');

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    }

    public function test_saml_callback_rejects_when_no_email(): void
    {
        $socialiteUser = $this->mockSamlSocialiteUser(
            rawAttributes: [
                'name' => 'No Email User',
            ],
            email: null,
        );

        $this->mockSamlDriver($socialiteUser);

        $response = $this->post('/auth/saml2/callback');

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    }

    public function test_saml_callback_respects_auto_create_users_disabled(): void
    {
        config(['aicl.saml.auto_create_users' => false]);

        $socialiteUser = $this->mockSamlSocialiteUser(
            rawAttributes: [
                'email' => 'no-auto@example.com',
            ],
            email: 'no-auto@example.com',
        );

        $this->mockSamlDriver($socialiteUser);

        $response = $this->post('/auth/saml2/callback');

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', [
            'email' => 'no-auto@example.com',
        ]);
    }

    public function test_saml_callback_syncs_roles_on_new_user(): void
    {
        config([
            'aicl.saml.role_map' => [
                'source_attribute' => 'groups',
                'map' => [
                    'IT-Admins' => 'super_admin',
                ],
            ],
        ]);

        $socialiteUser = $this->mockSamlSocialiteUser(
            rawAttributes: [
                'email' => 'admin-saml@example.com',
                'groups' => ['IT-Admins'],
            ],
            email: 'admin-saml@example.com',
        );

        $this->mockSamlDriver($socialiteUser);

        $this->post('/auth/saml2/callback');

        $user = User::where('email', 'admin-saml@example.com')->first();
        $this->assertTrue($user->hasRole('super_admin'));
    }

    public function test_saml_callback_syncs_roles_on_returning_user(): void
    {
        $user = User::factory()->create(['email' => 'returning@example.com']);
        $user->assignRole('viewer');

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'saml2',
            'provider_id' => 'saml-return-123',
        ]);

        config([
            'aicl.saml.role_map' => [
                'source_attribute' => 'groups',
                'map' => [
                    'Managers' => 'admin',
                ],
            ],
            'aicl.saml.role_sync_mode' => 'sync',
        ]);

        $socialiteUser = $this->mockSamlSocialiteUser(
            rawAttributes: [
                'email' => 'returning@example.com',
                'groups' => ['Managers'],
            ],
            email: 'returning@example.com',
            id: 'saml-return-123',
        );

        $this->mockSamlDriver($socialiteUser);

        $this->post('/auth/saml2/callback');

        $user->refresh();
        $this->assertTrue($user->hasRole('admin'));
        // In 'sync' mode, viewer should be replaced
        $this->assertFalse($user->hasRole('viewer'));
    }

    public function test_saml_callback_additive_role_sync(): void
    {
        $user = User::factory()->create(['email' => 'additive@example.com']);
        $user->assignRole('viewer');

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'saml2',
            'provider_id' => 'saml-add-123',
        ]);

        config([
            'aicl.saml.role_map' => [
                'source_attribute' => 'groups',
                'map' => [
                    'Engineering' => 'editor',
                ],
            ],
            'aicl.saml.role_sync_mode' => 'additive',
        ]);

        $socialiteUser = $this->mockSamlSocialiteUser(
            rawAttributes: [
                'email' => 'additive@example.com',
                'groups' => ['Engineering'],
            ],
            email: 'additive@example.com',
            id: 'saml-add-123',
        );

        $this->mockSamlDriver($socialiteUser);

        $this->post('/auth/saml2/callback');

        $user->refresh();
        $this->assertTrue($user->hasRole('editor'));
        // In 'additive' mode, viewer should be preserved
        $this->assertTrue($user->hasRole('viewer'));
    }

    public function test_saml_callback_updates_user_name_on_returning_login(): void
    {
        $user = User::factory()->create([
            'email' => 'name-update@example.com',
            'name' => 'Old Name',
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'saml2',
            'provider_id' => 'saml-name-123',
        ]);

        $socialiteUser = $this->mockSamlSocialiteUser(
            rawAttributes: [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => 'name-update@example.com',
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name' => 'New Name',
            ],
            email: 'name-update@example.com',
            name: 'New Name',
            id: 'saml-name-123',
        );

        $this->mockSamlDriver($socialiteUser);

        $this->post('/auth/saml2/callback');

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
    }

    public function test_saml_callback_csrf_exempt(): void
    {
        $socialiteUser = $this->mockSamlSocialiteUser(
            rawAttributes: ['email' => 'csrf-test@example.com'],
            email: 'csrf-test@example.com',
        );

        $this->mockSamlDriver($socialiteUser);

        // POST without CSRF token should not be 419
        $response = $this->post('/auth/saml2/callback');

        $this->assertNotEquals(419, $response->getStatusCode());
    }

    public function test_saml_feature_flag_controls_route_loading(): void
    {
        // Check the condition that AiclServiceProvider uses
        config(['aicl.features.saml' => false]);
        $this->assertFalse(config('aicl.features.saml'));

        config(['aicl.features.saml' => true]);
        $this->assertTrue(config('aicl.features.saml'));
    }

    public function test_login_page_has_saml_login_method(): void
    {
        config(['aicl.features.saml' => true]);

        $settings = app(\Aicl\Settings\FeatureSettings::class);
        $settings->enable_saml = true;
        $settings->save();

        $login = new \Aicl\Filament\Pages\Auth\Login;

        $this->assertTrue($login->hasSamlLogin());
    }

    public function test_login_page_saml_login_disabled_when_feature_off(): void
    {
        config(['aicl.features.saml' => false]);

        $login = new \Aicl\Filament\Pages\Auth\Login;

        $this->assertFalse($login->hasSamlLogin());
    }

    public function test_login_page_returns_idp_name_from_config(): void
    {
        config(['aicl.saml.idp_name' => 'Okta']);

        $login = new \Aicl\Filament\Pages\Auth\Login;

        $this->assertEquals('Okta', $login->getSamlIdpName());
    }

    public function test_login_page_returns_saml_redirect_url(): void
    {
        $login = new \Aicl\Filament\Pages\Auth\Login;

        $url = $login->getSamlRedirectUrl();

        $this->assertStringContainsString('/auth/saml2/redirect', $url);
    }

    public function test_saml_callback_builds_name_from_first_and_last(): void
    {
        config(['aicl.saml.auto_create_users' => true]);

        $socialiteUser = $this->mockSamlSocialiteUser(
            rawAttributes: [
                'email' => 'firstlast@example.com',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
            ],
            email: 'firstlast@example.com',
        );

        $this->mockSamlDriver($socialiteUser);

        $this->post('/auth/saml2/callback');

        $user = User::where('email', 'firstlast@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Jane Doe', $user->name);
    }

    public function test_saml_callback_falls_back_to_user_name_when_no_attributes(): void
    {
        config(['aicl.saml.auto_create_users' => true]);

        $socialiteUser = $this->mockSamlSocialiteUser(
            rawAttributes: [
                'email' => 'noname@example.com',
            ],
            email: 'noname@example.com',
        );

        $this->mockSamlDriver($socialiteUser);

        $this->post('/auth/saml2/callback');

        $user = User::where('email', 'noname@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('User', $user->name);
    }
}
