# AICL Authentication & Authorization Guide

**Version:** 1.2
**Last Updated:** 2026-02-07
**Owner:** `/architect`, `/tester`

---

## Overview

AICL provides a comprehensive authentication and authorization stack built on Laravel's native features and trusted packages. The system supports:

- **Session authentication** for dashboard users
- **OAuth2 authentication** for API consumers
- **Multi-factor authentication (MFA)** via TOTP
- **Social login** via OAuth providers
- **SAML 2.0 SSO** via enterprise identity providers
- **Role-based access control (RBAC)** with fine-grained permissions

---

## Architecture Summary

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        AUTHENTICATION STACK                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────┐    ┌─────────────────┐    ┌──────────────────────────┐ │
│  │ Filament Auth   │    │ Laravel Passport │    │ Laravel Socialite        │ │
│  │ (Session-based) │    │ (OAuth2 API)     │    │ (Social Login)           │ │
│  │                 │    │                  │    │                          │ │
│  │ • Login         │    │ • Client Creds   │    │ • Google                 │ │
│  │ • Register      │    │ • Auth Code      │    │ • GitHub                 │ │
│  │ • Password Reset│    │ • Personal Token │    │ • Facebook               │ │
│  │ • Email Verify  │    │ • Scoped Tokens  │    │ • Twitter                │ │
│  └────────┬────────┘    └────────┬─────────┘    └────────────┬─────────────┘ │
│           │                      │                           │               │
│           ▼                      ▼                           ▼               │
│  ┌─────────────────────────────────────────────────────────────────────────┐ │
│  │                         Laravel Session / Token                          │ │
│  └─────────────────────────────────────────────────────────────────────────┘ │
│                                     │                                        │
│                                     ▼                                        │
│  ┌─────────────────────────────────────────────────────────────────────────┐ │
│  │                    Filament Breezy (MFA/2FA)                             │ │
│  │                    TOTP Authentication                                   │ │
│  └─────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                        AUTHORIZATION STACK                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐ │
│  │                   Spatie Laravel Permission                              │ │
│  │                   Roles & Permissions (Database)                         │ │
│  └─────────────────────────────────────────────────────────────────────────┘ │
│                                     │                                        │
│           ┌─────────────────────────┼─────────────────────────┐              │
│           ▼                         ▼                         ▼              │
│  ┌─────────────────┐    ┌─────────────────┐    ┌──────────────────────────┐ │
│  │ Filament Shield │    │ Laravel Policies │    │ Gate Definitions         │ │
│  │ (Permission UI) │    │ (Model Auth)     │    │ (Custom Logic)           │ │
│  └─────────────────┘    └─────────────────┘    └──────────────────────────┘ │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Session Authentication (Dashboard)

### Filament Auth Pages

Filament provides built-in authentication pages that integrate seamlessly with the admin panel.

**Location:** `packages/aicl/src/Filament/Pages/Auth/`

| Page | Route | Purpose |
|------|-------|---------|
| Login | `/admin/login` | User authentication |
| Register | `/admin/register` | New user registration |
| Password Reset | `/admin/password-reset` | Password recovery |
| Email Verification | `/admin/email-verification` | Verify email address |

### Extended Login Page

AICL extends the default Filament login to add social login buttons:

```php
namespace Aicl\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

class Login extends BaseLogin
{
    protected static string $view = 'aicl::filament.pages.auth.login';
}
```

The view conditionally displays social login buttons based on the `AICL_SOCIAL_LOGIN` feature flag.

### Configuration

In `AdminPanelProvider`:

```php
->login(Login::class)
->registration()
->passwordReset()
->emailVerification()
```

---

## Multi-Factor Authentication (MFA)

### Filament Breezy

MFA is provided by `jeffgreco13/filament-breezy` v3.

**Features:**
- TOTP (Time-based One-Time Password) 2FA
- QR code enrollment
- Recovery codes
- Profile management

### Configuration

In `AdminPanelProvider`:

```php
->plugin(
    \Jeffgreco13\FilamentBreezy\BreezyCore::make()
        ->myProfile(
            shouldRegisterNavigation: true,
            slug: 'profile'
        )
        ->enableTwoFactorAuthentication()
)
```

### Database

Breezy v3 stores 2FA sessions in a dedicated table:

```
breezy_sessions
├── id
├── user_id
├── google2fa_secret (encrypted)
├── enabled
└── timestamps
```

**Note:** This is NOT stored on the users table directly.

### User Flow

1. User navigates to Profile → Two-Factor Authentication
2. Scans QR code with authenticator app (Google Authenticator, Authy, etc.)
3. Enters verification code to confirm
4. Recovery codes are displayed (store securely)
5. On next login, user enters TOTP code after password

---

## API Authentication (OAuth2)

### Laravel Passport v13

API authentication uses Laravel Passport v13 for OAuth2 support.

**Features:**
- Client credentials grant (machine-to-machine)
- Authorization code grant (user authorization)
- Personal access tokens
- Scoped tokens
- Token refresh

### Installation

Passport tables are created via migrations:

```
oauth_auth_codes
oauth_access_tokens
oauth_refresh_tokens
oauth_clients
oauth_device_codes
```

> **Note:** Passport v13 removed the `oauth_personal_access_clients` table and added `oauth_device_codes`. Client secrets are hashed by default. UUID client IDs on new installs.

### API Guard Configuration

```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'passport',
        'provider' => 'users',
    ],
],
```

### API Routes

Protected API routes use the `auth:api` middleware:

```php
Route::middleware('auth:api')->prefix('v1')->group(function () {
    Route::apiResource('projects', ProjectController::class);
});
```

### Token Management Page

Users can manage their personal access tokens via the ApiTokens Filament page:

**Location:** `packages/aicl/src/Filament/Pages/ApiTokens.php`
**Route:** `/admin/api-tokens`

**Features:**
- Create new personal access tokens
- View existing tokens (with ability scope)
- Revoke tokens

---

## Social Login

### Laravel Socialite

Social authentication uses Laravel Socialite with configurable providers.

**Supported Providers:**
- Google
- GitHub
- Facebook
- Twitter
- LinkedIn
- Azure AD
- (Custom providers via SocialiteProviders)

### Feature Flag

Social login is controlled by environment variable:

```env
AICL_SOCIAL_LOGIN=true
```

When enabled, the package loads social auth routes.

### Routes

**Location:** `packages/aicl/routes/socialite.php`

| Route | Method | Purpose |
|-------|--------|---------|
| `/auth/{provider}/redirect` | GET | Redirect to OAuth provider |
| `/auth/{provider}/callback` | GET | Handle OAuth callback |

### Controller

**Location:** `packages/aicl/src/Http/Controllers/SocialAuthController.php`

```php
class SocialAuthController extends Controller
{
    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $socialUser = Socialite::driver($provider)->user();

        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $socialUser->getEmail()],
            ['name' => $socialUser->getName()]
        );

        // Link social account
        $user->linkSocialAccount($provider, $socialUser->getId(), [
            'token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'avatar' => $socialUser->getAvatar(),
        ]);

        Auth::login($user);

        return redirect()->intended(route('filament.admin.pages.dashboard'));
    }
}
```

### SocialAccount Model

**Location:** `packages/aicl/src/Models/SocialAccount.php`

Stores linked social provider accounts with encrypted tokens:

```php
class SocialAccount extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'token',
        'refresh_token',
        'avatar',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'refresh_token' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### User Trait

**Location:** `packages/aicl/src/Traits/HasSocialAccounts.php`

Add to User model:

```php
use Aicl\Traits\HasSocialAccounts;

class User extends Authenticatable
{
    use HasSocialAccounts;
    // ...
}
```

**Methods:**
- `socialAccounts()` — HasMany relationship
- `linkSocialAccount(provider, id, data)` — Link or update social account
- `unlinkSocialAccount(provider)` — Remove social link
- `hasSocialAccount(provider)` — Check if linked

---

## Role-Based Access Control (RBAC)

### Spatie Permission

RBAC is implemented with `spatie/laravel-permission`.

**Core Concepts:**
- **Roles:** Named groups of permissions (e.g., `admin`, `editor`)
- **Permissions:** Specific abilities (e.g., `View:Project`, `Create:Project`)
- **Direct Permissions:** Assigned directly to users
- **Role Permissions:** Inherited through role membership

### Database Tables

```
roles
├── id
├── name
├── guard_name
└── timestamps

permissions
├── id
├── name
├── guard_name
└── timestamps

role_has_permissions (pivot)
model_has_roles (pivot)
model_has_permissions (pivot)
```

### Default Roles

Created by `RoleSeeder`:

| Role | Description |
|------|-------------|
| `super_admin` | Full access to everything |
| `admin` | Full access to most features |
| `editor` | Create and edit content |
| `viewer` | Read-only access |

### Permission Format

Filament Shield generates permissions in `Action:Resource` format:

```
ViewAny:Project
View:Project
Create:Project
Update:Project
Delete:Project
Restore:Project
ForceDelete:Project
```

### User Model Setup

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    // ...
}
```

---

## Filament Shield

### Overview

`bezhansalleh/filament-shield` provides a UI for managing roles and permissions within Filament.

**Features:**
- Auto-generate permissions for Filament resources
- Role management page
- Permission assignment UI
- Super admin role handling

### Installation

```bash
php artisan shield:install
php artisan shield:generate --all
```

### Panel Registration

```php
->plugin(
    \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make()
)
```

### Permission Generation

Shield automatically creates permissions for each Filament resource:

```php
// For ProjectResource, Shield generates:
// ViewAny:Project, View:Project, Create:Project, Update:Project, Delete:Project, etc.
```

### Super Admin

The `super_admin` role bypasses all permission checks:

```php
// In AuthServiceProvider or Gate
Gate::before(function ($user, $ability) {
    return $user->hasRole('super_admin') ? true : null;
});
```

---

## Policies

### BasePolicy Pattern

**Location:** `packages/aicl/src/Policies/BasePolicy.php`

```php
abstract class BasePolicy
{
    use HandlesAuthorization;

    protected string $permissionPrefix;

    public function viewAny(User $user): bool
    {
        return $user->can("ViewAny:{$this->permissionPrefix}");
    }

    public function create(User $user): bool
    {
        return $user->can("Create:{$this->permissionPrefix}");
    }
    // ... restore, forceDelete
}
```

### Entity Policy Pattern

```php
class ProjectPolicy extends BasePolicy
{
    protected string $permissionPrefix = 'Project';

    public function view(User $user, Project $project): bool
    {
        // Owner always has access
        if ($project->owner_id === $user->id) {
            return true;
        }
        return $user->can("View:{$this->permissionPrefix}");
    }

    public function update(User $user, Project $project): bool
    {
        if ($project->owner_id === $user->id) {
            return true;
        }
        return $user->can("Update:{$this->permissionPrefix}");
    }

    public function delete(User $user, Project $project): bool
    {
        if ($project->owner_id === $user->id) {
            return true;
        }
        return $user->can("Delete:{$this->permissionPrefix}");
    }
}
```

### Policy Registration

In service provider:

```php
Gate::policy(Project::class, ProjectPolicy::class);
```

### Filament Resource Integration

Filament automatically checks policies for resources:

```php
class ProjectResource extends Resource
{
    // Filament calls ProjectPolicy methods automatically
    // when rendering list, create, edit, delete actions
}
```

---

## Authorization in Filament

### Resource-Level Authorization

Filament resources automatically check policies:

```php
// In ProjectResource
public static function canViewAny(): bool
{
    return auth()->user()->can('viewAny', Project::class);
}

public static function canCreate(): bool
{
    return auth()->user()->can('create', Project::class);
}
```

### Navigation Authorization

Hide navigation items based on permissions:

```php
->navigationItems([
    NavigationItem::make('Settings')
        ->visible(fn () => auth()->user()->can('manage-settings')),
])
```

### Page Authorization

For custom pages:

```php
class ManageSettings extends Page
{
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin');
    }
}
```

### Table Action Authorization

```php
Tables\Actions\EditAction::make()
    ->visible(fn (Project $record) => auth()->user()->can('update', $record)),

Tables\Actions\DeleteAction::make()
    ->visible(fn (Project $record) => auth()->user()->can('delete', $record)),
```

---

## Testing Authentication

### Auth Test Patterns

```php
class AdminPanelAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create();

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_with_role_can_access_resource(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/admin/projects')
            ->assertOk();
    }

    public function test_user_without_permission_cannot_create(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $this->actingAs($user)
            ->get('/admin/projects/create')
            ->assertForbidden();
    }
}
```

### API Auth Test Patterns

```php
class ApiAuthTest extends TestCase
{
    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/projects')
            ->assertUnauthorized();
    }

    public function test_api_with_token_succeeds(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->accessToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/projects')
            ->assertOk();
    }
}
```

---

## Configuration Reference

### Environment Variables

```env
# Social Login
AICL_SOCIAL_LOGIN=false
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URL=
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URL=

# SAML SSO (see sso-external-auth.md for full setup)
AICL_SAML=false
SAML_IDP_METADATA_URL=
SAML_IDP_ENTITY_ID=
SAML_IDP_CERTIFICATE=
SAML_SP_ENTITY_ID=
SAML_SP_ACS_URL=
SAML_VERIFY_SSL=true

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120
```

### Auth Config (`config/auth.php`)

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'passport',
        'provider' => 'users',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
],
```

### Passport Config (`config/passport.php`)

```php
'personal_access_client' => [
    'id' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_ID'),
    'secret' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET'),
],

'token_lifetime' => 15, // days
'refresh_token_lifetime' => 30, // days
```

---

## Security Best Practices

1. **Always hash passwords** — Laravel does this by default
2. **Use HTTPS** — Especially for OAuth callbacks
3. **Encrypt sensitive data** — Social tokens are encrypted at rest
4. **Rate limit auth endpoints** — Prevent brute force
5. **Implement account lockout** — After failed attempts
6. **Use MFA** — Encourage or require for sensitive roles
7. **Audit auth events** — Log login attempts
8. **Rotate tokens** — Implement token refresh flows
9. **Scope API tokens** — Grant minimum necessary permissions
10. **Validate redirect URLs** — Prevent open redirect attacks

---

## Related Documents

- [SSO & External Authentication](sso-external-auth.md) — SAML 2.0, OAuth social login, attribute mapping, IdP setup guides
- [Foundation](foundation.md)
- [Entity System](entity-system.md)
- [Filament UI](filament-ui.md)
- [Notifications](notifications.md)
