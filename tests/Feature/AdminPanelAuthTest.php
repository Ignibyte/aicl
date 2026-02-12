<?php

namespace Aicl\Tests\Feature;

use Aicl\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
    }

    public function test_login_page_renders_split_layout(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        $response->assertSee('Sign in to your account');
        $response->assertSee('Welcome Back!');
        $response->assertSee('fi-aicl-login-split', false);
        $response->assertSee(config('app.name', 'AICL'));
        $response->assertSee('Register');
    }

    public function test_registration_page_is_accessible(): void
    {
        $response = $this->get('/admin/register');

        $response->assertStatus(200);
    }

    public function test_password_reset_page_is_accessible(): void
    {
        $response = $this->get('/admin/password-reset/request');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(200);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_user_can_register(): void
    {
        Notification::fake();

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
        ]);
    }

    public function test_user_cannot_register_with_existing_email(): void
    {
        $user = User::factory()->create();

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Duplicate User',
                'email' => $user->email,
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register')
            ->assertHasFormErrors(['email']);
    }

    public function test_user_can_request_password_reset(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm([
                'email' => $user->email,
            ])
            ->call('request')
            ->assertHasNoFormErrors();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/admin/logout');

        $this->assertGuest();
    }

    public function test_email_is_required_for_login(): void
    {
        Livewire::test(Login::class)
            ->fillForm([
                'email' => '',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email' => 'required']);

        $this->assertGuest();
    }

    public function test_password_is_required_for_login(): void
    {
        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'test@example.com',
                'password' => '',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['password' => 'required']);

        $this->assertGuest();
    }

    public function test_api_user_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }
}
