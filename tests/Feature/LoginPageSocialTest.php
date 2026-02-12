<?php

namespace Aicl\Tests\Feature;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Filament\Pages\Auth\Login;
use Aicl\Http\Controllers\SocialAuthController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LoginPageSocialTest extends TestCase
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

        // Register socialite routes since they're conditionally loaded at boot time
        Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
            ->name('social.redirect');
        Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
            ->name('social.callback');

        // Force refresh the named route lookup cache
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_get_social_providers_returns_empty_when_disabled(): void
    {
        config(['aicl.features.social_login' => false]);

        $page = new Login;
        $providers = $page->getSocialProviders();

        $this->assertEmpty($providers);
    }

    public function test_get_social_providers_returns_configured_providers(): void
    {
        config([
            'aicl.features.social_login' => true,
            'aicl.social_providers' => ['google', 'github'],
        ]);

        $page = new Login;
        $providers = $page->getSocialProviders();

        $this->assertCount(2, $providers);
        $this->assertArrayHasKey('google', $providers);
        $this->assertArrayHasKey('github', $providers);
    }

    public function test_provider_has_required_keys(): void
    {
        config([
            'aicl.features.social_login' => true,
            'aicl.social_providers' => ['google'],
        ]);

        $page = new Login;
        $providers = $page->getSocialProviders();

        $provider = $providers['google'];
        $this->assertArrayHasKey('name', $provider);
        $this->assertArrayHasKey('url', $provider);
        $this->assertArrayHasKey('icon', $provider);
        $this->assertArrayHasKey('color', $provider);
    }

    public function test_provider_name_is_ucfirst(): void
    {
        config([
            'aicl.features.social_login' => true,
            'aicl.social_providers' => ['google'],
        ]);

        $page = new Login;
        $providers = $page->getSocialProviders();

        $this->assertEquals('Google', $providers['google']['name']);
    }

    public function test_google_icon_and_color(): void
    {
        config([
            'aicl.features.social_login' => true,
            'aicl.social_providers' => ['google'],
        ]);

        $page = new Login;
        $providers = $page->getSocialProviders();

        $this->assertEquals('heroicon-o-globe-alt', $providers['google']['icon']);
        $this->assertEquals('danger', $providers['google']['color']);
    }

    public function test_github_icon_and_color(): void
    {
        config([
            'aicl.features.social_login' => true,
            'aicl.social_providers' => ['github'],
        ]);

        $page = new Login;
        $providers = $page->getSocialProviders();

        $this->assertEquals('heroicon-o-code-bracket', $providers['github']['icon']);
        $this->assertEquals('gray', $providers['github']['color']);
    }

    public function test_facebook_icon_and_color(): void
    {
        config([
            'aicl.features.social_login' => true,
            'aicl.social_providers' => ['facebook'],
        ]);

        $page = new Login;
        $providers = $page->getSocialProviders();

        $this->assertEquals('heroicon-o-chat-bubble-oval-left', $providers['facebook']['icon']);
        $this->assertEquals('info', $providers['facebook']['color']);
    }

    public function test_twitter_icon_and_color(): void
    {
        config([
            'aicl.features.social_login' => true,
            'aicl.social_providers' => ['twitter'],
        ]);

        $page = new Login;
        $providers = $page->getSocialProviders();

        $this->assertEquals('heroicon-o-chat-bubble-bottom-center-text', $providers['twitter']['icon']);
        $this->assertEquals('info', $providers['twitter']['color']);
    }

    public function test_linkedin_icon_and_color(): void
    {
        config([
            'aicl.features.social_login' => true,
            'aicl.social_providers' => ['linkedin'],
        ]);

        $page = new Login;
        $providers = $page->getSocialProviders();

        $this->assertEquals('heroicon-o-briefcase', $providers['linkedin']['icon']);
        $this->assertEquals('info', $providers['linkedin']['color']);
    }

    public function test_microsoft_icon_and_color(): void
    {
        config([
            'aicl.features.social_login' => true,
            'aicl.social_providers' => ['microsoft'],
        ]);

        $page = new Login;
        $providers = $page->getSocialProviders();

        $this->assertEquals('heroicon-o-building-office', $providers['microsoft']['icon']);
        $this->assertEquals('info', $providers['microsoft']['color']);
    }

    public function test_unknown_provider_icon_and_color(): void
    {
        config([
            'aicl.features.social_login' => true,
            'aicl.social_providers' => ['unknown-provider'],
        ]);

        $page = new Login;
        $providers = $page->getSocialProviders();

        $this->assertEquals('heroicon-o-user-circle', $providers['unknown-provider']['icon']);
        $this->assertEquals('gray', $providers['unknown-provider']['color']);
    }

    public function test_has_social_login_returns_false_when_disabled(): void
    {
        config(['aicl.features.social_login' => false]);

        $page = new Login;

        $this->assertFalse($page->hasSocialLogin());
    }

    public function test_has_social_login_returns_false_when_no_providers(): void
    {
        config([
            'aicl.features.social_login' => true,
            'aicl.social_providers' => [],
        ]);

        $page = new Login;

        $this->assertFalse($page->hasSocialLogin());
    }

    public function test_has_social_login_returns_true_when_enabled_with_providers(): void
    {
        config([
            'aicl.features.social_login' => true,
            'aicl.social_providers' => ['google'],
        ]);

        $page = new Login;

        $this->assertTrue($page->hasSocialLogin());
    }

    public function test_login_page_title(): void
    {
        $page = new Login;

        $this->assertEquals('Sign in', $page->getTitle());
    }

    public function test_login_page_heading_is_empty(): void
    {
        $page = new Login;

        $this->assertEquals('', $page->getHeading());
    }

    public function test_login_page_subheading_is_null(): void
    {
        $page = new Login;

        $this->assertNull($page->getSubheading());
    }

    public function test_login_page_has_no_logo(): void
    {
        $page = new Login;

        $this->assertFalse($page->hasLogo());
    }
}
