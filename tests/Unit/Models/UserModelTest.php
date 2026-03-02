<?php

namespace Aicl\Tests\Unit\Models;

use App\Models\User;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    public function test_user_extends_authenticatable(): void
    {
        $user = new User;

        $this->assertInstanceOf(Authenticatable::class, $user);
    }

    public function test_user_implements_filament_user(): void
    {
        $this->assertTrue(
            in_array(FilamentUser::class, class_implements(User::class))
        );
    }

    public function test_user_implements_has_avatar(): void
    {
        $this->assertTrue(
            in_array(HasAvatar::class, class_implements(User::class))
        );
    }

    public function test_user_implements_must_verify_email(): void
    {
        $this->assertTrue(
            in_array(MustVerifyEmail::class, class_implements(User::class))
        );
    }

    public function test_user_implements_oauthenticatable(): void
    {
        $this->assertTrue(
            in_array(OAuthenticatable::class, class_implements(User::class))
        );
    }

    public function test_user_fillable_attributes(): void
    {
        $user = new User;

        $this->assertEquals(
            ['name', 'email', 'password', 'avatar_url', 'force_mfa'],
            $user->getFillable()
        );
    }

    public function test_user_hidden_attributes(): void
    {
        $user = new User;

        $this->assertEquals(
            ['password', 'remember_token'],
            $user->getHidden()
        );
    }

    public function test_user_casts_email_verified_at(): void
    {
        $user = new User;
        $casts = $user->getCasts();

        $this->assertEquals('datetime', $casts['email_verified_at']);
    }

    public function test_user_casts_password_as_hashed(): void
    {
        $user = new User;
        $casts = $user->getCasts();

        $this->assertEquals('hashed', $casts['password']);
    }

    public function test_user_can_access_panel(): void
    {
        $user = new User;
        $panel = \Filament\Facades\Filament::getPanel('admin');

        $this->assertTrue($user->canAccessPanel($panel));
    }

    public function test_user_uses_has_audit_trail_trait(): void
    {
        $this->assertContains(
            \Aicl\Traits\HasAuditTrail::class,
            class_uses_recursive(User::class)
        );
    }

    public function test_user_uses_has_entity_events_trait(): void
    {
        $this->assertContains(
            \Aicl\Traits\HasEntityEvents::class,
            class_uses_recursive(User::class)
        );
    }

    public function test_user_uses_has_standard_scopes_trait(): void
    {
        $this->assertContains(
            \Aicl\Traits\HasStandardScopes::class,
            class_uses_recursive(User::class)
        );
    }

    public function test_user_uses_has_roles_trait(): void
    {
        $this->assertContains(
            \Spatie\Permission\Traits\HasRoles::class,
            class_uses_recursive(User::class)
        );
    }

    public function test_user_uses_has_api_tokens_trait(): void
    {
        $this->assertContains(
            \Laravel\Passport\HasApiTokens::class,
            class_uses_recursive(User::class)
        );
    }

    public function test_user_uses_two_factor_authenticatable_trait(): void
    {
        $this->assertContains(
            \Jeffgreco13\FilamentBreezy\Traits\TwoFactorAuthenticatable::class,
            class_uses_recursive(User::class)
        );
    }

    public function test_user_uses_has_notification_logging_trait(): void
    {
        $this->assertContains(
            \Aicl\Traits\HasNotificationLogging::class,
            class_uses_recursive(User::class)
        );
    }

    public function test_user_uses_has_social_accounts_trait(): void
    {
        $this->assertContains(
            \Aicl\Traits\HasSocialAccounts::class,
            class_uses_recursive(User::class)
        );
    }

    public function test_user_uses_notifiable_trait(): void
    {
        $this->assertContains(
            \Illuminate\Notifications\Notifiable::class,
            class_uses_recursive(User::class)
        );
    }

    public function test_user_searchable_columns(): void
    {
        $user = new User;
        $reflection = new \ReflectionMethod($user, 'searchableColumns');
        $reflection->setAccessible(true);

        $this->assertEquals(['name', 'email'], $reflection->invoke($user));
    }

    public function test_filament_avatar_url_returns_null_without_avatar(): void
    {
        $user = new User;
        $user->avatar_url = null;

        $this->assertNull($user->getFilamentAvatarUrl());
    }

    public function test_filament_avatar_url_returns_storage_url_with_avatar(): void
    {
        $user = new User;
        $user->avatar_url = 'avatars/test.jpg';

        $url = $user->getFilamentAvatarUrl();

        $this->assertNotNull($url);
        $this->assertStringContainsString('avatars/test.jpg', $url);
    }
}
