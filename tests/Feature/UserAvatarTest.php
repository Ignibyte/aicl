<?php

namespace Aicl\Tests\Feature;

use Aicl\Models\SocialAccount;
use App\Models\User;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\SettingsSeeder']);
    }

    public function test_user_implements_has_avatar_contract(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(HasAvatar::class, $user);
    }

    public function test_returns_null_when_no_avatar(): void
    {
        $user = User::factory()->create(['avatar_url' => null]);

        $this->assertNull($user->getFilamentAvatarUrl());
    }

    public function test_returns_uploaded_avatar_url(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['avatar_url' => 'avatars/test.jpg']);

        $result = $user->getFilamentAvatarUrl();

        $this->assertNotNull($result);
        $this->assertEquals(Storage::disk('public')->url('avatars/test.jpg'), $result);
    }

    public function test_returns_sso_avatar_when_no_upload(): void
    {
        $user = User::factory()->create(['avatar_url' => null]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'g-123',
            'avatar_url' => 'https://google.com/photo.jpg',
            'token' => 'test-token',
        ]);

        $this->assertEquals('https://google.com/photo.jpg', $user->getFilamentAvatarUrl());
    }

    public function test_uploaded_avatar_takes_priority_over_sso(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['avatar_url' => 'avatars/custom.jpg']);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'g-123',
            'avatar_url' => 'https://google.com/photo.jpg',
            'token' => 'test-token',
        ]);

        $result = $user->getFilamentAvatarUrl();

        $this->assertEquals(Storage::disk('public')->url('avatars/custom.jpg'), $result);
    }

    public function test_sso_avatar_uses_most_recently_updated_account(): void
    {
        $user = User::factory()->create(['avatar_url' => null]);

        $google = SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'g-123',
            'avatar_url' => 'https://google.com/photo.jpg',
            'token' => 'test-token',
        ]);

        $github = SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => 'gh-456',
            'avatar_url' => 'https://github.com/avatar.jpg',
            'token' => 'test-token',
        ]);

        // Force specific updated_at timestamps via query builder to bypass Eloquent timestamps
        SocialAccount::where('id', $google->id)->update(['updated_at' => now()->subDay()]);
        SocialAccount::where('id', $github->id)->update(['updated_at' => now()]);

        $this->assertEquals('https://github.com/avatar.jpg', $user->getFilamentAvatarUrl());
    }

    public function test_sso_avatar_skips_null_avatar_urls(): void
    {
        $user = User::factory()->create(['avatar_url' => null]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'g-123',
            'avatar_url' => null,
            'token' => 'test-token',
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => 'gh-456',
            'avatar_url' => 'https://github.com/avatar.jpg',
            'token' => 'test-token',
        ]);

        $this->assertEquals('https://github.com/avatar.jpg', $user->getFilamentAvatarUrl());
    }

    public function test_avatar_url_is_fillable_on_user(): void
    {
        $this->assertContains('avatar_url', (new User)->getFillable());
    }

    public function test_clearing_uploaded_avatar_falls_back_to_sso(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['avatar_url' => 'avatars/test.jpg']);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'g-123',
            'avatar_url' => 'https://google.com/photo.jpg',
            'token' => 'test-token',
        ]);

        // Uploaded avatar takes priority
        $this->assertEquals(Storage::disk('public')->url('avatars/test.jpg'), $user->getFilamentAvatarUrl());

        // Clear the uploaded avatar
        $user->update(['avatar_url' => null]);
        $user->refresh();

        // Should now fall back to SSO avatar
        $this->assertEquals('https://google.com/photo.jpg', $user->getFilamentAvatarUrl());
    }
}
