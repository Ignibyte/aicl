<?php

namespace Aicl\Tests\Feature;

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
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialAuthControllerTest extends TestCase
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

        config(['aicl.social_providers' => ['google', 'github']]);

        // Register socialite routes manually since they're conditionally loaded at boot time
        Route::middleware('web')->group(function (): void {
            Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
                ->name('social.redirect');
            Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
                ->name('social.callback');
        });
    }

    protected function mockSocialiteUser(array $overrides = []): SocialiteUser
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn(array_key_exists('id', $overrides) ? $overrides['id'] : '123456');
        $socialiteUser->shouldReceive('getEmail')->andReturn(array_key_exists('email', $overrides) ? $overrides['email'] : 'social@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn(array_key_exists('name', $overrides) ? $overrides['name'] : 'Social User');
        $socialiteUser->shouldReceive('getNickname')->andReturn(array_key_exists('nickname', $overrides) ? $overrides['nickname'] : 'socialuser');
        $socialiteUser->shouldReceive('getAvatar')->andReturn(array_key_exists('avatar', $overrides) ? $overrides['avatar'] : 'https://example.com/avatar.jpg');
        $socialiteUser->token = array_key_exists('token', $overrides) ? $overrides['token'] : 'mock-token';
        $socialiteUser->refreshToken = array_key_exists('refreshToken', $overrides) ? $overrides['refreshToken'] : 'mock-refresh-token';
        $socialiteUser->expiresIn = array_key_exists('expiresIn', $overrides) ? $overrides['expiresIn'] : 3600;

        return $socialiteUser;
    }

    public function test_redirect_to_valid_provider(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/oauth'));

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($driver);

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirect();
    }

    public function test_redirect_to_invalid_provider_returns_404(): void
    {
        $response = $this->get('/auth/invalid-provider/redirect');

        $response->assertStatus(404);
    }

    public function test_callback_with_existing_social_account_logs_in_user(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => '123456',
            'token' => 'old-token',
        ]);

        $socialiteUser = $this->mockSocialiteUser(['email' => 'existing@example.com']);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($driver);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_with_existing_user_by_email_links_account(): void
    {
        $user = User::factory()->create(['email' => 'social@example.com']);

        $socialiteUser = $this->mockSocialiteUser();

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($driver);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => '123456',
        ]);
    }

    public function test_callback_creates_new_user_when_not_found(): void
    {
        $socialiteUser = $this->mockSocialiteUser([
            'email' => 'brand-new@example.com',
            'name' => 'Brand New User',
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('github')
            ->once()
            ->andReturn($driver);

        $response = $this->get('/auth/github/callback');

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'brand-new@example.com',
            'name' => 'Brand New User',
        ]);

        $newUser = User::where('email', 'brand-new@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertAuthenticatedAs($newUser);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $newUser->id,
            'provider' => 'github',
            'provider_id' => '123456',
        ]);
    }

    public function test_callback_assigns_viewer_role_to_new_user(): void
    {
        $socialiteUser = $this->mockSocialiteUser([
            'email' => 'role-test@example.com',
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($driver);

        $this->get('/auth/google/callback');

        $newUser = User::where('email', 'role-test@example.com')->first();
        $this->assertTrue($newUser->hasRole('viewer'));
    }

    public function test_callback_stores_token_expiry(): void
    {
        $socialiteUser = $this->mockSocialiteUser([
            'email' => 'expiry-test@example.com',
            'expiresIn' => 7200,
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($driver);

        $this->get('/auth/google/callback');

        $newUser = User::where('email', 'expiry-test@example.com')->first();
        $account = $newUser->socialAccounts()->first();

        $this->assertNotNull($account->token_expires_at);
    }

    public function test_callback_handles_null_expires_in(): void
    {
        $socialiteUser = $this->mockSocialiteUser([
            'email' => 'no-expiry@example.com',
            'expiresIn' => null,
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($driver);

        $this->get('/auth/google/callback');

        $newUser = User::where('email', 'no-expiry@example.com')->first();
        $account = $newUser->socialAccounts()->first();

        $this->assertNull($account->token_expires_at);
    }

    public function test_callback_handles_socialite_exception(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')
            ->once()
            ->andThrow(new \Exception('OAuth error'));

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($driver);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    }

    public function test_callback_with_invalid_provider_returns_404(): void
    {
        $response = $this->get('/auth/invalid/callback');

        $response->assertStatus(404);
    }

    public function test_callback_uses_nickname_when_name_is_null(): void
    {
        $socialiteUser = $this->mockSocialiteUser([
            'email' => 'nickname-test@example.com',
            'name' => null,
            'nickname' => 'nicknameuser',
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($driver);

        $this->get('/auth/google/callback');

        $this->assertDatabaseHas('users', [
            'email' => 'nickname-test@example.com',
            'name' => 'nicknameuser',
        ]);
    }

    public function test_callback_uses_fallback_name_when_all_null(): void
    {
        $socialiteUser = $this->mockSocialiteUser([
            'email' => 'fallback-test@example.com',
            'name' => null,
            'nickname' => null,
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($driver);

        $this->get('/auth/google/callback');

        $this->assertDatabaseHas('users', [
            'email' => 'fallback-test@example.com',
            'name' => 'User',
        ]);
    }

    public function test_social_accounts_trait_link_social_account(): void
    {
        $user = User::factory()->create();

        $account = $user->linkSocialAccount('github', 'gh-123', 'token', 'refresh', now()->addHour());

        $this->assertInstanceOf(SocialAccount::class, $account);
        $this->assertEquals('github', $account->provider);
        $this->assertEquals('gh-123', $account->provider_id);
    }

    public function test_social_accounts_trait_link_updates_existing(): void
    {
        $user = User::factory()->create();

        $user->linkSocialAccount('github', 'gh-123', 'old-token');
        $updated = $user->linkSocialAccount('github', 'gh-456', 'new-token');

        $this->assertEquals('gh-456', $updated->provider_id);
        $this->assertCount(1, $user->socialAccounts);
    }

    public function test_social_accounts_trait_unlink(): void
    {
        $user = User::factory()->create();
        $user->linkSocialAccount('github', 'gh-123');

        $this->assertTrue($user->unlinkSocialAccount('github'));
        $this->assertFalse($user->hasSocialAccount('github'));
    }

    public function test_social_accounts_trait_unlink_nonexistent_returns_false(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->unlinkSocialAccount('github'));
    }

    public function test_social_accounts_trait_has_social_account(): void
    {
        $user = User::factory()->create();
        $user->linkSocialAccount('google', 'g-123');

        $this->assertTrue($user->hasSocialAccount('google'));
        $this->assertFalse($user->hasSocialAccount('github'));
    }

    public function test_social_accounts_trait_get_social_account(): void
    {
        $user = User::factory()->create();
        $user->linkSocialAccount('google', 'g-123');

        $account = $user->getSocialAccount('google');
        $this->assertInstanceOf(SocialAccount::class, $account);
        $this->assertEquals('g-123', $account->provider_id);

        $this->assertNull($user->getSocialAccount('github'));
    }

    public function test_callback_stores_avatar_url_on_new_social_account(): void
    {
        $socialiteUser = $this->mockSocialiteUser([
            'email' => 'avatar-new@example.com',
            'avatar' => 'https://google.com/photo.jpg',
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($driver);

        $this->get('/auth/google/callback');

        $newUser = User::where('email', 'avatar-new@example.com')->first();
        $account = $newUser->socialAccounts()->first();

        $this->assertEquals('https://google.com/photo.jpg', $account->avatar_url);
    }

    public function test_callback_stores_null_avatar_url_when_provider_has_none(): void
    {
        $socialiteUser = $this->mockSocialiteUser([
            'email' => 'avatar-null@example.com',
            'avatar' => null,
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($driver);

        $this->get('/auth/google/callback');

        $newUser = User::where('email', 'avatar-null@example.com')->first();
        $account = $newUser->socialAccounts()->first();

        $this->assertNull($account->avatar_url);
    }

    public function test_callback_updates_avatar_url_on_existing_social_account(): void
    {
        $user = User::factory()->create(['email' => 'avatar-update@example.com']);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => '123456',
            'avatar_url' => 'https://old-avatar.com/photo.jpg',
            'token' => 'old-token',
        ]);

        $socialiteUser = $this->mockSocialiteUser([
            'email' => 'avatar-update@example.com',
            'avatar' => 'https://new-avatar.com/photo.jpg',
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($driver);

        $this->get('/auth/google/callback');

        $account = $user->socialAccounts()->first();
        $this->assertEquals('https://new-avatar.com/photo.jpg', $account->avatar_url);
    }

    public function test_callback_does_not_overwrite_avatar_with_null_on_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'avatar-keep@example.com']);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => '123456',
            'avatar_url' => 'https://existing-avatar.com/photo.jpg',
            'token' => 'old-token',
        ]);

        $socialiteUser = $this->mockSocialiteUser([
            'email' => 'avatar-keep@example.com',
            'avatar' => null,
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($driver);

        $this->get('/auth/google/callback');

        $account = $user->socialAccounts()->first();
        $this->assertEquals('https://existing-avatar.com/photo.jpg', $account->avatar_url);
    }
}
