# AICL Single Sign-On & External Authentication

**Version:** 1.0
**Last Updated:** 2026-02-08
**Owner:** `/pipeline-implement`

---

## Overview

AICL supports three external authentication methods alongside native session login:

1. **SAML 2.0 SSO** — Enterprise identity providers (Okta, Azure AD, Google Workspace, ADFS)
2. **OAuth Social Login** — Consumer providers (Google, GitHub, Facebook, Twitter, LinkedIn)
3. **OAuth2 API** — Machine-to-machine via Laravel Passport (covered in [auth-rbac.md](auth-rbac.md))

SAML and social login operate independently — a project can enable one, both, or neither.

---

## Architecture

```
                            ┌──────────────────────────────┐
                            │        Login Page            │
                            │                              │
                            │  ┌────────────────────────┐  │
                            │  │   Email / Password      │  │
                            │  └────────────────────────┘  │
                            │                              │
                            │  ┌────────────────────────┐  │
                            │  │   Sign in with SSO      │  │  ← SAML 2.0
                            │  └────────────────────────┘  │
                            │                              │
                            │  ┌──────────┐ ┌───────────┐  │
                            │  │  Google   │ │  GitHub   │  │  ← OAuth
                            │  └──────────┘ └───────────┘  │
                            └──────────────────────────────┘
                                         │
                    ┌────────────────────┼────────────────────┐
                    ▼                    ▼                    ▼
            ┌──────────────┐   ┌──────────────┐   ┌──────────────┐
            │  SAML IdP    │   │  Google OAuth │   │ GitHub OAuth │
            │ (Okta, etc.) │   │              │   │              │
            └──────┬───────┘   └──────┬───────┘   └──────┬───────┘
                   │                  │                   │
                   ▼                  ▼                   ▼
            ┌─────────────────────────────────────────────────┐
            │            SocialAuthController                   │
            │                                                   │
            │  samlCallback()    callback('google')             │
            │       │                  │                         │
            │       ▼                  ▼                         │
            │  SamlAttributeMapper   Socialite User             │
            │       │                  │                         │
            │       └──────────┬───────┘                        │
            │                  ▼                                │
            │         Find or Create User                       │
            │         Link SocialAccount                        │
            │         Sync Roles (SAML only)                    │
            │         Auth::login($user)                        │
            └─────────────────────────────────────────────────┘
                                   │
                                   ▼
                        ┌──────────────────┐
                        │  social_accounts │
                        │  ├── provider    │
                        │  ├── provider_id │
                        │  ├── token       │
                        │  └── avatar_url  │
                        └──────────────────┘
```

---

## Feature Gates (Two-Layer System)

Every external auth method has two independent gates:

| Layer | Where | Controls | Who changes it |
|-------|-------|----------|----------------|
| **Config flag** (`config/local.php`) | `config('aicl.features.saml')` | Whether SAML code is loaded (routes, bindings) | DevOps / deployment |
| **Settings toggle** (DB) | `FeatureSettings::enable_saml` | Whether the SSO button is visible to users | Admin via Settings UI |

Both must be `true` for the feature to be active.

```
aicl.features.saml=true  +  Settings enable_saml=true   →  SSO button visible
aicl.features.saml=true  +  Settings enable_saml=false  →  Routes loaded, button hidden
aicl.features.saml=false +  Settings (any)              →  Routes not loaded, button hidden
```

The same pattern applies to social login (`aicl.features.social_login` + `enable_social_login`).

---

## SAML 2.0 SSO

### Package

`socialiteproviders/saml2` v4.8+ — wraps LightSAML inside Laravel Socialite's driver interface.

### How It Works

1. User clicks "Sign in with SSO" on the login page
2. SP (AICL) sends an AuthnRequest to the IdP via browser redirect
3. User authenticates at the IdP (Okta login page, Azure AD, etc.)
4. IdP POSTs a signed SAML Response to the ACS endpoint
5. SP validates the assertion, extracts attributes, creates/links user

### Files

| File | Purpose |
|------|---------|
| `packages/aicl/src/Auth/SamlAttributeMapper.php` | 3-layer attribute + role mapper |
| `packages/aicl/routes/saml.php` | SAML routes (metadata, redirect, callback) |
| `packages/aicl/src/Http/Controllers/SocialAuthController.php` | Controller with `samlCallback()`, `samlDriver()`, `syncSamlRoles()` |
| `packages/aicl/src/Filament/Pages/Auth/Login.php` | `hasSamlLogin()`, `getSamlIdpName()`, `getSamlRedirectUrl()` |
| `packages/aicl/resources/views/filament/pages/auth/login.blade.php` | SSO button rendering |
| `config/services.php` | `saml2` service block (IdP metadata URL, SP entity ID, etc.) |
| `packages/aicl/config/aicl.php` | `saml` config section (mapper, roles, attributes) |

### Routes

| Route | Method | Name | Purpose |
|-------|--------|------|---------|
| `/auth/saml2/metadata` | GET | `saml.metadata` | SP metadata XML (give to IdP admin) |
| `/auth/saml2/redirect` | GET | `saml.redirect` | Initiate SAML AuthnRequest |
| `/auth/saml2/callback` | POST | `saml.callback` | ACS endpoint (CSRF-exempt) |

### Setup Guide

#### Step 1: Enable the feature flag

```php
// config/local.php
'aicl.features.saml' => true,
```

#### Step 2: Configure the IdP connection

```php
// config/local.php
'aicl.features.saml' => true,

// IdP display name on the login button
'services.saml2.idp_name' => 'Okta',

// IdP metadata URL (required)
'services.saml2.metadata' => 'https://your-org.okta.com/app/exk123/sso/saml/metadata',

// Optional — for IdPs that don't publish metadata at a URL
// 'services.saml2.entityid'    => 'http://www.okta.com/exk123',
// 'services.saml2.certificate' => 'MIIC...',

// SP callback URL — override if your app uses a non-standard port
// 'services.saml2.sp_acs' => 'https://yourapp.com/auth/saml2/callback',

// Disable SSL verification for self-signed IdP certs (dev only!)
// 'services.saml2.verify_ssl' => false,
```

#### Step 3: Register AICL as a Service Provider in your IdP

Give your IdP admin these values:

| IdP Setting | Value |
|-------------|-------|
| **SP Entity ID** | `https://yourapp.com/auth/saml2/metadata` |
| **ACS URL** | `https://yourapp.com/auth/saml2/callback` |
| **ACS Binding** | HTTP-POST |
| **Name ID Format** | `urn:oasis:names:tc:SAML:2.0:nameid-format:persistent` |
| **SP Metadata URL** | `https://yourapp.com/auth/saml2/metadata` (download XML) |

Or point them to the metadata XML endpoint — most IdPs can import it directly.

#### Step 4: Enable in Settings

Log into the admin panel, go to **Settings > Features**, and toggle **SAML SSO** on.

#### Step 5: Test

1. Log out
2. Click "Sign in with SSO" on the login page
3. Authenticate at your IdP
4. You should be redirected back and logged in

### Attribute Mapping

The `SamlAttributeMapper` resolves user fields from SAML assertions using a 3-layer system:

#### Layer 1: Package Defaults

Built-in mappings for standard SAML attribute URIs:

| User Field | SAML Attributes (tried in order) |
|------------|----------------------------------|
| `email` | WS-Fed emailaddress, OID 0.9.2342.19200300.100.1.3, `email`, `Email` |
| `name` | WS-Fed name, OID 2.5.4.3, `displayName`, `name` |
| `first_name` | WS-Fed givenname, OID 2.5.4.42, `first_name`, `firstName` |
| `last_name` | WS-Fed surname, OID 2.5.4.4, `last_name`, `lastName` |

These cover Okta, Azure AD, ADFS, Google Workspace, and SimpleSAMLphp out of the box.

#### Layer 2: Config Overrides

Add custom attribute mappings in `config/aicl.php`:

```php
'saml' => [
    'attribute_map' => [
        'department' => ['urn:oid:2.5.4.11', 'department'],
        'employee_id' => ['employeeNumber', 'employee_id'],
    ],
],
```

Config entries merge with (and override) package defaults.

#### Layer 3: Custom Mapper Class

For complex logic, replace the mapper entirely:

```php
// config/aicl.php
'saml' => [
    'mapper_class' => App\Auth\CustomSamlMapper::class,
],

// app/Auth/CustomSamlMapper.php
namespace App\Auth;

use Aicl\Auth\SamlAttributeMapper;

class CustomSamlMapper extends SamlAttributeMapper
{
    public function resolveAttributes($socialiteUser): array
    {
        $attributes = parent::resolveAttributes($socialiteUser);

        // Custom logic: combine department + location
        $raw = $this->getRawAttributes($socialiteUser);
        $attributes['team'] = ($raw['department'] ?? '') . ' - ' . ($raw['location'] ?? '');

        return $attributes;
    }
}
```

Register via DI — the `AiclServiceProvider` binds `SamlAttributeMapper` as a singleton and checks `mapper_class`.

### Role Mapping

Map IdP group/role attributes to AICL's Spatie Permission roles.

#### Configuration

```php
// config/aicl.php
'saml' => [
    'role_map' => [
        'source_attribute' => 'groups',   // SAML attribute containing groups
        'map' => [
            'IT-Admins'    => 'super_admin',
            'Managers'     => 'admin',
            'Engineering'  => 'editor',
            'Contractors'  => 'viewer',
        ],
    ],
    'role_sync_mode' => 'sync',    // 'sync' or 'additive'
    'default_role' => 'viewer',     // Fallback when no groups match
],
```

#### Sync Modes

| Mode | Behavior |
|------|----------|
| `sync` | Replace all user roles with mapped roles on each login |
| `additive` | Only add new roles, never remove existing ones |

#### How It Works

1. On each SAML login (new or returning), `syncSamlRoles()` is called
2. The mapper reads the configured `source_attribute` from the SAML assertion
3. Each IdP group value is looked up in the `map`
4. Matched roles are synced to the user via Spatie's `syncRoles()` or `assignRole()`
5. If no groups match, the `default_role` is assigned

### Configuration Reference

All SAML settings are overridden via `config/local.php` using dot-notation keys. The underlying config lives in `config/services.php` (saml2 block) and `config/aicl.php` (saml section).

| Config Key (`config/local.php`) | Required | Default | Description |
|--------------------------------|----------|---------|-------------|
| `aicl.features.saml` | Yes | `false` | Enable SAML code loading |
| `services.saml2.idp_name` | No | `SSO` | Button label on login page |
| `services.saml2.metadata` | Yes* | — | IdP metadata XML endpoint |
| `services.saml2.entityid` | No | — | IdP entity ID (alt to metadata URL) |
| `services.saml2.certificate` | No | — | IdP signing cert (alt to metadata URL) |
| `services.saml2.sp_entity_id` | No | `{APP_URL}/auth/saml2/metadata` | SP entity ID |
| `services.saml2.sp_acs` | No | `auth/saml2/callback` | SP ACS callback URL |
| `services.saml2.sp_certificate` | No | — | SP signing certificate |
| `services.saml2.sp_private_key` | No | — | SP private key |
| `services.saml2.verify_ssl` | No | `true` | SSL verification for metadata fetch |
| `aicl.saml.auto_create_users` | No | `true` | Create users on first SAML login |
| `aicl.saml.default_role` | No | `viewer` | Role for unmapped users |
| `aicl.saml.role_sync_mode` | No | `sync` | Role sync mode (`sync`/`additive`) |

*Required when connecting to an IdP. Not needed while button is disabled.

---

## OAuth Social Login

### Package

`laravel/socialite` with `socialiteproviders/manager` for community providers.

### Supported Providers

| Provider | Package | Config Key |
|----------|---------|------------|
| Google | Built into Socialite | `services.google` |
| GitHub | Built into Socialite | `services.github` |
| Facebook | Built into Socialite | `services.facebook` |
| Twitter/X | Built into Socialite | `services.twitter` |
| LinkedIn | Built into Socialite | `services.linkedin` |
| Microsoft | `socialiteproviders/microsoft` | `services.microsoft` |

### Setup Guide

#### Step 1: Enable the feature flag

```php
// config/local.php
'aicl.features.social_login' => true,
```

#### Step 2: Create OAuth credentials at the provider

**Google:**
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create OAuth 2.0 credentials (Web application type)
3. Set authorized redirect URI: `https://yourapp.com/auth/google/callback`

**GitHub:**
1. Go to [GitHub Developer Settings](https://github.com/settings/developers)
2. Create a new OAuth App
3. Set authorization callback URL: `https://yourapp.com/auth/github/callback`

#### Step 3: Add credentials to `config/local.php`

```php
// config/local.php
'services.google.client_id'     => 'your-client-id',
'services.google.client_secret' => 'your-client-secret',
'services.google.redirect'      => 'https://yourapp.com/auth/google/callback',

'services.github.client_id'     => 'your-client-id',
'services.github.client_secret' => 'your-client-secret',
'services.github.redirect'      => 'https://yourapp.com/auth/github/callback',
```

> **Note:** `config/services.php` uses `env()` calls by default (e.g., `env('GOOGLE_CLIENT_ID')`). The `config/local.php` overrides take precedence, so you don't need to modify `config/services.php`.

#### Step 4: Configure enabled providers

In `config/aicl.php`:

```php
'social_providers' => ['google', 'github'],
```

#### Step 5: Enable in Settings

Go to **Settings > Features** and toggle **Social Login** on.

### Routes

| Route | Method | Name | Purpose |
|-------|--------|------|---------|
| `/auth/{provider}/redirect` | GET | `social.redirect` | Redirect to OAuth provider |
| `/auth/{provider}/callback` | GET | `social.callback` | Handle OAuth callback |

### User Flow

1. User clicks provider button on login page
2. Redirected to provider's OAuth consent screen
3. User authorizes the app
4. Provider redirects back with auth code
5. AICL exchanges code for token, gets user profile
6. Finds existing user by email or creates new one
7. Links `SocialAccount` record with encrypted tokens
8. Logs user in

### SocialAccount Model

All external auth methods (SAML + OAuth) store linked accounts in the same table:

```
social_accounts
├── id
├── user_id        → FK to users
├── provider       → 'google', 'github', 'saml2'
├── provider_id    → External ID from provider
├── avatar_url     → Profile picture URL
├── token          → Encrypted OAuth token (null for SAML)
├── refresh_token  → Encrypted refresh token (null for SAML)
├── token_expires_at → Token expiry (null for SAML)
└── timestamps
```

---

## IdP-Specific Setup Notes

### Okta

| Setting | Value |
|---------|-------|
| SSO URL | `https://yourorg.okta.com/app/app_id/sso/saml` |
| Metadata URL | `https://yourorg.okta.com/app/app_id/sso/saml/metadata` |
| Attribute Statements | email → `user.email`, name → `user.displayName` |
| Group Attribute | `groups` (filter by app group assignment) |

### Azure AD (Entra ID)

| Setting | Value |
|---------|-------|
| Metadata URL | `https://login.microsoftonline.com/{tenant}/federationmetadata/2007-06/federationmetadata.xml` |
| Reply URL | `https://yourapp.com/auth/saml2/callback` |
| Attribute Claims | Map `user.mail`, `user.displayname` |
| Group Claims | Enable "Groups assigned to the application" |

### Google Workspace

| Setting | Value |
|---------|-------|
| ACS URL | `https://yourapp.com/auth/saml2/callback` |
| Entity ID | `https://yourapp.com/auth/saml2/metadata` |
| Name ID | Basic Information > Primary email |
| Attributes | First name, Last name, Primary email |

Note: Google Workspace sends attributes as simple names (`first_name`, `last_name`, `email`) — the default mapper handles these.

---

## Testing

### Unit Tests (SamlAttributeMapper)

22 tests covering: standard URI resolution, OID URI resolution, simple name resolution, Socialite fallbacks, config overrides, custom fields, single/multiple role mapping, unmapped groups, default role fallback, deduplication, custom source attribute, `getAttribute()`, `getRawAttributes()`, custom mapper DI.

### Feature Tests (SamlAuth)

19 tests covering: new user creation, existing user login, email-based linking, null tokens, exception handling, no email rejection, auto-create disabled, role sync on new/returning users, additive role sync, name update on returning login, CSRF exemption, feature flag, login page methods, first+last name building, fallback name.

### Running

```bash
# SAML tests only
ddev exec php artisan test --compact tests/Unit/Auth/ tests/Feature/Auth/

# Full suite
ddev exec php artisan test --compact
```

---

## Troubleshooting

### SSO button not visible

1. Check `config('aicl.features.saml')` is `true` (set in `config/local.php`)
2. Check Settings > Features > SAML SSO is toggled on
3. Reload Octane: `ddev octane-reload`

### "Either the metadata or acs config keys must be set"

The `services.saml2.metadata` config value is not set. Add the IdP's metadata URL in `config/local.php`.

### SSL certificate error when fetching metadata

Set `'services.saml2.verify_ssl' => false` in `config/local.php` (dev only). In production, ensure the IdP certificate is trusted by the server's CA bundle.

### User created but no roles assigned

Check that `aicl.saml.role_map.map` is configured and the IdP sends the `groups` attribute (or whatever `source_attribute` is set to). Use `getRawAttributes()` in a test to inspect what the IdP actually sends.

### Social login buttons not showing

1. Check `config('aicl.features.social_login')` is `true` (set in `config/local.php`)
2. Check Settings > Features > Social Login is toggled on
3. Verify providers are listed in `config('aicl.social_providers')`
4. Verify OAuth credentials are configured in `config/local.php` (or `config/services.php`)

---

## Related Documents

- [Auth & RBAC](auth-rbac.md) — Session auth, MFA, Passport API, roles, policies
- [Foundation](foundation.md) — Laravel stack, DDEV, Octane
- [Filament UI](filament-ui.md) — Admin panel, login page customization
