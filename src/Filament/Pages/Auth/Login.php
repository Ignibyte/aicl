<?php

namespace Aicl\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    protected string $view = 'aicl::filament.pages.auth.login';

    protected bool $hasTopbar = false;

    protected Width|string|null $maxContentWidth = Width::Screen;

    public function getTitle(): string|Htmlable
    {
        return __('Sign in');
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    public function hasLogo(): bool
    {
        return false;
    }

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

    public function hasSocialLogin(): bool
    {
        if (! config('aicl.features.social_login', false)) {
            return false;
        }

        try {
            if (! app(\Aicl\Settings\FeatureSettings::class)->enable_social_login) {
                return false;
            }
        } catch (\Throwable) {
            // Settings table may not exist yet (fresh install)
        }

        return count($this->getSocialProviders()) > 0;
    }

    public function hasSamlLogin(): bool
    {
        if (! config('aicl.features.saml', false)) {
            return false;
        }

        try {
            return app(\Aicl\Settings\FeatureSettings::class)->enable_saml;
        } catch (\Throwable) {
            return true;
        }
    }

    public function getSamlIdpName(): string
    {
        return config('aicl.saml.idp_name', 'SSO');
    }

    public function getSamlRedirectUrl(): string
    {
        return route('saml.redirect');
    }
}
