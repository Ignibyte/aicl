<?php

namespace Aicl\Filament\Pages\Auth;

use Aicl\Auth\SamlAttributeMapper;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Custom login page with split-layout design and social/SAML SSO support.
 *
 * Extends Filament's base login page with a full-screen split layout,
 * optional social login buttons (Google, GitHub, Facebook, etc.), and
 * SAML2 SSO redirect support. Social and SAML providers are configured
 * via the aicl.features.social_login, aicl.social_providers, and
 * aicl.features.saml config keys.
 *
 * @see Register  Companion registration page
 * @see SamlAttributeMapper  Maps SAML assertion attributes to user fields
 */
class Login extends BaseLogin
{
    /** @var string Custom Blade view with split layout */
    protected string $view = 'aicl::filament.pages.auth.login';

    /** @var bool Disable the top navigation bar on the login page */
    protected bool $hasTopbar = false;

    /** @var Width|string|null Use full screen width for the split layout */
    protected Width|string|null $maxContentWidth = Width::Screen;

    /**
     * Get the page title displayed in the browser tab.
     */
    public function getTitle(): string|Htmlable
    {
        return __('Sign in');
    }

    /**
     * Get the page heading (empty for the split layout).
     */
    public function getHeading(): string|Htmlable
    {
        return '';
    }

    /**
     * Get the page subheading (null for the split layout).
     */
    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * Whether to show Filament's logo on the login form (disabled for split layout).
     */
    public function hasLogo(): bool
    {
        return false;
    }

    /**
     * Get configured social login providers with their display metadata.
     *
     * Returns an associative array keyed by provider name, each containing
     * the display name, redirect URL, Heroicon name, and Filament color.
     *
     * @return array<string, array{name: string, url: string, icon: string, color: string}>
     */
    public function getSocialProviders(): array
    {
        if (! config('aicl.features.social_login', false)) {
            return [];
        }

        $providers = config('aicl.social_providers', []);
        $result = [];

        foreach ($providers as $provider) {
            $result[$provider] = [
                'name' => ucfirst($provider),
                'url' => route('social.redirect', $provider),
                'icon' => $this->getProviderIcon($provider),
                'color' => $this->getProviderColor($provider),
            ];
        }

        return $result;
    }

    /**
     * Map a social provider name to a Heroicon identifier.
     *
     * @param  string  $provider  The provider key (e.g. 'google', 'github')
     * @return string Heroicon component name
     */
    protected function getProviderIcon(string $provider): string
    {
        return match ($provider) {
            'google' => 'heroicon-o-globe-alt',
            'github' => 'heroicon-o-code-bracket',
            'facebook' => 'heroicon-o-chat-bubble-oval-left',
            'twitter' => 'heroicon-o-chat-bubble-bottom-center-text',
            'linkedin' => 'heroicon-o-briefcase',
            'microsoft' => 'heroicon-o-building-office',
            default => 'heroicon-o-user-circle',
        };
    }

    /**
     * Map a social provider name to a Filament color string.
     *
     * @param  string  $provider  The provider key (e.g. 'google', 'github')
     * @return string Filament color name (e.g. 'danger', 'info', 'gray')
     */
    protected function getProviderColor(string $provider): string
    {
        return match ($provider) {
            'google' => 'danger',
            'github' => 'gray',
            'facebook' => 'info',
            'twitter' => 'info',
            'linkedin' => 'info',
            'microsoft' => 'info',
            default => 'gray',
        };
    }

    /**
     * Whether any social login providers are configured and enabled.
     */
    public function hasSocialLogin(): bool
    {
        if (! config('aicl.features.social_login', false)) {
            return false;
        }

        return count($this->getSocialProviders()) > 0;
    }

    /**
     * Whether SAML SSO login is enabled.
     */
    public function hasSamlLogin(): bool
    {
        return (bool) config('aicl.features.saml', false);
    }

    /**
     * Get the display name for the SAML identity provider.
     */
    public function getSamlIdpName(): string
    {
        return config('aicl.saml.idp_name', 'SSO');
    }

    /**
     * Get the URL to initiate the SAML SSO redirect flow.
     */
    public function getSamlRedirectUrl(): string
    {
        return route('saml.redirect');
    }
}
