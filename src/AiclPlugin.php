<?php

namespace Aicl;

use Aicl\Filament\Pages\ActivityLog;
use Aicl\Filament\Pages\AiAssistant;
use Aicl\Filament\Pages\ApiTokens;
use Aicl\Filament\Pages\Auth\Register;
use Aicl\Filament\Pages\Changelog;
use Aicl\Filament\Pages\DocumentBrowser;
use Aicl\Filament\Pages\Errors\Forbidden;
use Aicl\Filament\Pages\Errors\NotFound;
use Aicl\Filament\Pages\Errors\ServerError;
use Aicl\Filament\Pages\Errors\ServiceUnavailable;
use Aicl\Filament\Pages\ManageSettings;
use Aicl\Filament\Pages\NotificationCenter;
use Aicl\Filament\Pages\OperationsManager;
use Aicl\Filament\Pages\OpsPanel;
use Aicl\Filament\Pages\Search;
use Aicl\Filament\Pages\Tools;
use Aicl\Filament\Resources\Users\UserResource;
use Aicl\Filament\Widgets\GlobalSearchWidget;
use Aicl\Filament\Widgets\PresenceIndicator;
use Aicl\Filament\Widgets\QueueStatsWidget;
use Aicl\Filament\Widgets\RecentFailedJobsWidget;
use Aicl\Http\Middleware\MustTwoFactor;
use Aicl\Http\Middleware\TrackPresenceMiddleware;
use Aicl\Services\VersionService;
use Aicl\Settings\FeatureSettings;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Jeffgreco13\FilamentBreezy\BreezyCore;

class AiclPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'aicl';
    }

    public function register(Panel $panel): void
    {
        // Register Breezy for profile + MFA (unless project already registered it)
        if (! $panel->hasPlugin('filament-breezy')) {
            $panel->plugin(
                BreezyCore::make()
                    ->myProfile(
                        shouldRegisterUserMenu: true,
                        shouldRegisterNavigation: false,
                        hasAvatars: true,
                    )
                    ->enableTwoFactorAuthentication(
                        force: fn (): bool => rescue(
                            fn () => app(FeatureSettings::class)->require_mfa
                                  || (bool) filament()->auth()->user()?->force_mfa,
                            false
                        ),
                        authMiddleware: MustTwoFactor::class,
                    )
            );
        }

        // Always register the registration route — runtime gating is handled by
        // the custom Register page (checks isRegistrationEnabled() on each request).
        // This avoids Octane boot-time caching issues where toggling the admin
        // setting had no effect until workers were reloaded.
        $panel->registration(Register::class);

        // Email verification — always register the route, runtime-gated by setting.
        // The emailVerification page handles the "verify your email" prompt.
        $panel->emailVerification();

        $panel
            ->resources($this->getResources())
            ->pages($this->getPages())
            ->widgets($this->getWidgets())
            ->authMiddleware([
                TrackPresenceMiddleware::class,
            ])
            ->topNavigation();
    }

    /**
     * Check if user registration is enabled via env config OR database settings.
     *
     * The env flag (AICL_ALLOW_REGISTRATION) provides infrastructure-level control.
     * The database toggle (Settings > Features > User Registration) provides admin UI control.
     * Either being true enables registration.
     */
    public static function isRegistrationEnabled(): bool
    {
        if (config('aicl.features.allow_registration', false)) {
            return true;
        }

        try {
            return app(FeatureSettings::class)->enable_registration;
        } catch (\Throwable) {
            // Database may not be available yet (fresh install, pre-migration)
            return false;
        }
    }

    /**
     * Check if email verification is required via database settings.
     *
     * When disabled, users bypass the email verification prompt even though
     * the User model implements MustVerifyEmail. Override hasVerifiedEmail()
     * in the User model to use this check.
     */
    public static function isEmailVerificationRequired(): bool
    {
        try {
            return app(FeatureSettings::class)->require_email_verification;
        } catch (\Throwable) {
            return true;
        }
    }

    public function boot(Panel $panel): void
    {
        if (config('aicl.features.websockets', true)) {
            // Inject the app's Vite JS bundle (includes Echo for WebSocket presence)
            // Uses SCRIPTS_AFTER so @vite generates same-origin URLs avoiding CORS
            FilamentView::registerRenderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => Blade::render('@vite(\'resources/js/app.js\')'),
            );

            FilamentView::registerRenderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => Blade::render('@livewire(\'toolbar-presence\')'),
            );
        }

        // Inject early script in <head> to read localStorage and set data-nav-mode
        // before first paint, preventing flash-of-wrong-layout (FOWL)
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => Blade::render('@include("aicl::components.navigation-switcher-init")'),
        );

        // Nav switcher toggle — rendered before user menu (inside .fi-topbar-end)
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): string => Blade::render('@include("aicl::components.navigation-switcher-toggle")'),
        );

        // Extended favicon tags (apple-touch-icon, android-chrome, sizes)
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => Blade::render('@include("aicl::components.favicon-meta")'),
        );

        // Version badge — rendered before user menu (inside .fi-topbar-end)
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): string => view('aicl::components.version-badge', [
                'version' => app(VersionService::class)->current(),
            ])->render(),
        );
    }

    /**
     * @return array<class-string>
     */
    protected function getResources(): array
    {
        return [
            UserResource::class,
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getPages(): array
    {
        return [
            ActivityLog::class,
            AiAssistant::class,
            OpsPanel::class,
            OperationsManager::class,
            ManageSettings::class,
            Changelog::class,
            DocumentBrowser::class,
            Tools::class,
            NotificationCenter::class,
            Search::class,
            ApiTokens::class,
            NotFound::class,
            Forbidden::class,
            ServerError::class,
            ServiceUnavailable::class,
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getWidgets(): array
    {
        return [
            GlobalSearchWidget::class,
            PresenceIndicator::class,
            QueueStatsWidget::class,
            RecentFailedJobsWidget::class,
        ];
    }
}
