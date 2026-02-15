<?php

namespace Aicl\Tests\Feature;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Filament\Pages\ApiTokens;
use Aicl\Filament\Pages\Auth\Login;
use Aicl\Http\Controllers\SocialAuthController;
use Aicl\Models\SocialAccount;
use Aicl\Traits\HasSocialAccounts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SocialOAuthTest extends TestCase
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
    }

    public function test_social_account_model_exists(): void
    {
        $this->assertTrue(class_exists(SocialAccount::class));
    }

    public function test_social_account_has_required_fillable_attributes(): void
    {
        $account = new SocialAccount;
        $fillable = $account->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('provider', $fillable);
        $this->assertContains('provider_id', $fillable);
        $this->assertContains('token', $fillable);
    }

    public function test_social_account_belongs_to_user(): void
    {
        $account = new SocialAccount;

        $this->assertTrue(method_exists($account, 'user'));
    }

    public function test_has_social_accounts_trait_exists(): void
    {
        $this->assertTrue(trait_exists(HasSocialAccounts::class));
    }

    public function test_has_social_accounts_trait_has_required_methods(): void
    {
        $reflection = new \ReflectionClass(HasSocialAccounts::class);

        $this->assertTrue($reflection->hasMethod('socialAccounts'));
        $this->assertTrue($reflection->hasMethod('hasSocialAccount'));
        $this->assertTrue($reflection->hasMethod('getSocialAccount'));
        $this->assertTrue($reflection->hasMethod('linkSocialAccount'));
        $this->assertTrue($reflection->hasMethod('unlinkSocialAccount'));
    }

    public function test_social_auth_controller_exists(): void
    {
        $this->assertTrue(class_exists(SocialAuthController::class));
    }

    public function test_social_auth_controller_has_redirect_method(): void
    {
        $controller = app(SocialAuthController::class);

        $this->assertTrue(method_exists($controller, 'redirect'));
    }

    public function test_social_auth_controller_has_callback_method(): void
    {
        $controller = app(SocialAuthController::class);

        $this->assertTrue(method_exists($controller, 'callback'));
    }

    public function test_login_page_class_exists(): void
    {
        $this->assertTrue(class_exists(Login::class));
    }

    public function test_login_page_has_social_provider_methods(): void
    {
        $page = new Login;

        $this->assertTrue(method_exists($page, 'getSocialProviders'));
        $this->assertTrue(method_exists($page, 'hasSocialLogin'));
    }

    public function test_login_page_returns_empty_providers_when_disabled(): void
    {
        config(['aicl.features.social_login' => false]);

        $page = new Login;
        $providers = $page->getSocialProviders();

        $this->assertEmpty($providers);
    }

    public function test_login_page_has_social_login_returns_false_when_disabled(): void
    {
        config(['aicl.features.social_login' => false]);

        $page = new Login;

        $this->assertFalse($page->hasSocialLogin());
    }

    public function test_api_tokens_page_exists(): void
    {
        $this->assertTrue(class_exists(ApiTokens::class));
    }

    public function test_api_tokens_page_has_required_methods(): void
    {
        $reflection = new \ReflectionClass(ApiTokens::class);

        $this->assertTrue($reflection->hasMethod('getTokens'));
        $this->assertTrue($reflection->hasMethod('createToken'));
        $this->assertTrue($reflection->hasMethod('revokeToken'));
    }

    public function test_api_tokens_page_accessible_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $response = $this->actingAs($user)->get('/admin/api-tokens');

        $response->assertStatus(200);
    }

    public function test_api_tokens_page_redirects_guests(): void
    {
        $response = $this->get('/admin/api-tokens');

        $response->assertRedirect();
    }

    public function test_aicl_config_has_social_login_feature_flag(): void
    {
        $this->assertArrayHasKey('features', config('aicl'));
        $this->assertArrayHasKey('social_login', config('aicl.features'));
    }

    public function test_aicl_config_has_social_providers(): void
    {
        $this->assertArrayHasKey('social_providers', config('aicl'));
        $this->assertIsArray(config('aicl.social_providers'));
    }

    public function test_social_account_migration_exists(): void
    {
        $migrationPath = base_path('packages/aicl/database/migrations');
        $files = glob($migrationPath.'/*create_social_accounts_table.php');

        $this->assertNotEmpty($files, 'Social accounts migration should exist');
    }

    public function test_socialite_routes_file_exists(): void
    {
        $routePath = base_path('packages/aicl/routes/socialite.php');

        $this->assertTrue(file_exists($routePath), 'Socialite routes file should exist');
    }

    public function test_login_view_template_exists(): void
    {
        $this->assertTrue(
            view()->exists('aicl::filament.pages.auth.login'),
            'Login view template should exist'
        );
    }

    public function test_api_tokens_view_template_exists(): void
    {
        $this->assertTrue(
            view()->exists('aicl::filament.pages.api-tokens'),
            'API tokens view template should exist'
        );
    }

    public function test_social_account_is_expired_returns_false_when_no_expiry(): void
    {
        $account = new SocialAccount;
        $account->token_expires_at = null;

        $this->assertFalse($account->isExpired());
    }

    public function test_social_account_is_expired_returns_true_for_past_date(): void
    {
        $account = new SocialAccount;
        $account->token_expires_at = now()->subDay();

        $this->assertTrue($account->isExpired());
    }

    public function test_social_account_is_expired_returns_false_for_future_date(): void
    {
        $account = new SocialAccount;
        $account->token_expires_at = now()->addDay();

        $this->assertFalse($account->isExpired());
    }
}
