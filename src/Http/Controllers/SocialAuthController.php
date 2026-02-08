<?php

namespace Aicl\Http\Controllers;

use Aicl\Auth\SamlAttributeMapper;
use Aicl\Models\SocialAccount;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

class SocialAuthController
{
    protected array $enabledProviders = ['google', 'github'];

    public function redirect(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['email' => 'Unable to authenticate with '.ucfirst($provider).'. Please try again.']);
        }

        // Check if this social account is already linked to a user
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            // Update avatar URL on each login (providers can change avatars)
            $avatarUrl = $socialUser->getAvatar();
            if ($avatarUrl) {
                $socialAccount->update(['avatar_url' => $avatarUrl]);
            }

            Auth::login($socialAccount->user);

            return redirect()->intended($this->getRedirectUrl());
        }

        // Find or create user by email
        $user = User::where('email', $socialUser->getEmail())->first();

        if (! $user) {
            // Create new user
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                'email' => $socialUser->getEmail(),
                'email_verified_at' => now(),
                'password' => bcrypt(Str::random(32)), // Random password
            ]);

            // Assign default role if available
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('viewer');
            }
        }

        // Link the social account
        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'avatar_url' => $socialUser->getAvatar(),
            'token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'token_expires_at' => $socialUser->expiresIn
                ? now()->addSeconds($socialUser->expiresIn)
                : null,
        ]);

        Auth::login($user);

        return redirect()->intended($this->getRedirectUrl());
    }

    /**
     * Serve the SP metadata XML for IdP administrators.
     */
    public function samlMetadata(): Response
    {
        return $this->samlDriver()->getServiceProviderMetadata();
    }

    /**
     * SP-initiated SAML redirect — sends AuthnRequest to IdP.
     */
    public function samlRedirect(): HttpFoundationResponse
    {
        return $this->samlDriver()->redirect();
    }

    /**
     * Handle SAML ACS callback — POST from IdP (CSRF-exempt).
     * Supports both SP-initiated and IdP-initiated (stateless) flows.
     */
    public function samlCallback(): RedirectResponse
    {
        $mapper = app(SamlAttributeMapper::class);

        try {
            $socialUser = $this->samlDriver()->stateless()->user();
        } catch (\Exception $e) {
            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['email' => 'SAML authentication failed. Please try again.']);
        }

        $attributes = $mapper->resolveAttributes($socialUser);
        $email = $attributes['email'] ?? $socialUser->getEmail();

        if (! $email) {
            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['email' => 'No email address was provided by the identity provider.']);
        }

        // Check if this SAML account is already linked
        $socialAccount = SocialAccount::where('provider', 'saml2')
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            $user = $socialAccount->user;

            // Sync roles from SAML assertion on each login
            $this->syncSamlRoles($user, $mapper, $socialUser);

            // Update name if changed at IdP
            if (! empty($attributes['name']) && $user->name !== $attributes['name']) {
                $user->update(['name' => $attributes['name']]);
            }

            Auth::login($user);

            return redirect()->intended($this->getRedirectUrl());
        }

        // Find existing user by email or create new
        $user = User::where('email', $email)->first();

        if (! $user) {
            if (! config('aicl.saml.auto_create_users', true)) {
                return redirect()->route('filament.admin.auth.login')
                    ->withErrors(['email' => 'No account exists for this email. Contact your administrator.']);
            }

            $name = $attributes['name']
                ?? trim(($attributes['first_name'] ?? '').' '.($attributes['last_name'] ?? ''))
                    ?: 'User';

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => bcrypt(Str::random(32)),
            ]);
        }

        // Link the SAML account (no OAuth tokens for SAML)
        $user->socialAccounts()->create([
            'provider' => 'saml2',
            'provider_id' => $socialUser->getId(),
            'avatar_url' => $socialUser->getAvatar(),
            'token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
        ]);

        // Sync roles from SAML assertion
        $this->syncSamlRoles($user, $mapper, $socialUser);

        Auth::login($user);

        return redirect()->intended($this->getRedirectUrl());
    }

    /**
     * Get the SAML2 Socialite driver with SSL verification config applied.
     *
     * The socialiteproviders/saml2 provider ignores the guzzle config from
     * services.php because its constructor doesn't pass it to the parent.
     * We configure the HTTP client directly here.
     */
    protected function samlDriver(): mixed
    {
        $driver = Socialite::driver('saml2');

        if (config('services.saml2.guzzle.verify') === false) {
            $driver->setHttpClient(new Client(['verify' => false]));
        }

        return $driver;
    }

    /**
     * Sync user roles based on SAML attribute mapping.
     */
    protected function syncSamlRoles(User $user, SamlAttributeMapper $mapper, mixed $socialUser): void
    {
        if (! method_exists($user, 'syncRoles')) {
            return;
        }

        $roles = $mapper->resolveRoles($socialUser);

        if (empty($roles)) {
            return;
        }

        $syncMode = config('aicl.saml.role_sync_mode', 'sync');

        if ($syncMode === 'additive') {
            foreach ($roles as $role) {
                if (! $user->hasRole($role)) {
                    $user->assignRole($role);
                }
            }
        } else {
            $user->syncRoles($roles);
        }
    }

    protected function validateProvider(string $provider): void
    {
        $enabled = config('aicl.social_providers', $this->enabledProviders);

        if (! in_array($provider, $enabled)) {
            abort(404, 'Social provider not found or not enabled.');
        }
    }

    protected function getRedirectUrl(): string
    {
        return filament()->getHomeUrl() ?? '/admin';
    }
}
