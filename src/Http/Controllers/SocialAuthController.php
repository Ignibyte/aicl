<?php

namespace Aicl\Http\Controllers;

use Aicl\Auth\SamlAttributeMapper;
use Aicl\Services\Exceptions\SocialAuthException;
use Aicl\Services\SocialAuthService;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

class SocialAuthController
{
    protected array $enabledProviders = ['google', 'github'];

    public function __construct(
        private SocialAuthService $socialAuth,
    ) {}

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

        $user = $this->socialAuth->handleOAuthCallback($provider, $socialUser);
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

        try {
            $user = $this->socialAuth->handleSamlCallback($socialUser, $mapper);
        } catch (SocialAuthException $e) {
            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['email' => $e->getMessage()]);
        }

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
            if (app()->isProduction()) {
                \Illuminate\Support\Facades\Log::warning('SAML SSL verification is disabled in production. Set services.saml2.guzzle.verify to true.');
            }
            $driver->setHttpClient(new Client(['verify' => false]));
        }

        return $driver;
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
