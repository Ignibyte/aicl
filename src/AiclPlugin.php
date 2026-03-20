<?php

declare(strict_types=1);

namespace Aicl;

use Aicl\Filament\Pages\ActivityLog;
use Aicl\Filament\Pages\ApiTokens;
use Aicl\Filament\Pages\Auth\Register;
use Aicl\Filament\Pages\Backups;
use Aicl\Filament\Pages\Changelog;
use Aicl\Filament\Pages\DocumentBrowser;
use Aicl\Filament\Pages\Errors\Forbidden;
use Aicl\Filament\Pages\Errors\NotFound;
use Aicl\Filament\Pages\Errors\ServerError;
use Aicl\Filament\Pages\Errors\ServiceUnavailable;
use Aicl\Filament\Pages\NotificationCenter;
use Aicl\Filament\Pages\OperationsManager;
use Aicl\Filament\Pages\OpsPanel;
use Aicl\Filament\Pages\Search;
use Aicl\Filament\Pages\Tools;
use Aicl\Filament\Resources\AiAgents\AiAgentResource;
use Aicl\Filament\Resources\AiConversations\AiConversationResource;
use Aicl\Filament\Resources\Users\UserResource;
use Aicl\Filament\Widgets\AiAgentStatsWidget;
use Aicl\Filament\Widgets\GlobalSearchWidget;
use Aicl\Filament\Widgets\PresenceIndicator;
use Aicl\Filament\Widgets\QueueStatsWidget;
use Aicl\Filament\Widgets\RecentFailedJobsWidget;
use Aicl\Http\Middleware\MustTwoFactor;
use Aicl\Http\Middleware\TrackPresenceMiddleware;
use Aicl\Services\VersionService;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Blade;
use Jeffgreco13\FilamentBreezy\BreezyCore;

/**
 * Filament panel plugin for the AICL framework.
 *
 * Registers all AICL-provided Filament resources (Users, AI Agents, AI Conversations),
 * pages (OpsPanel, ActivityLog, ApiTokens, NotificationCenter, error pages, etc.),
 * and widgets (presence indicator, queue stats, AI agent stats, etc.) into the admin panel.
 *
 * Also configures Breezy (profile + MFA), email verification, top navigation mode,
 * render hooks for WebSocket scripts, Reverb config injection, navigation switcher,
 * favicon meta, version badge, search bar, and the AI assistant floating widget.
 *
 * @see AiclServiceProvider  Service provider that boots the underlying services
 */
class AiclPlugin implements Plugin
{
    /**
     * Create a new plugin instance from the container.
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Retrieve the registered plugin instance from the current Filament panel.
     */
    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    /**
     * Get the unique plugin identifier.
     */
    public function getId(): string
    {
        return 'aicl';
    }

    /**
     * Register resources, pages, widgets, auth middleware, and Breezy into the panel.
     *
     * Conditionally registers Breezy for profile and MFA if not already present.
     * Always registers the registration route (runtime-gated by Register page)
     * and email verification. Disables Filament's built-in global search in favor
     * of the custom AICL search bar.
     *
     * @param  Panel  $panel  The Filament panel being configured
     */
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
                            fn () => (bool) config('aicl.features.require_mfa', true)
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

        // Always disable Filament's built-in global search.
        // When aicl.search.enabled=true, the custom nav search bar replaces it.
        // When disabled, no search bar is shown at all.
        $panel->globalSearch(false);
    }

    /**
     * Check if user registration is enabled via config.
     */
    public static function isRegistrationEnabled(): bool
    {
        return (bool) config('aicl.features.allow_registration', false);
    }

    /**
     * Check if email verification is required via config.
     */
    public static function isEmailVerificationRequired(): bool
    {
        return (bool) config('aicl.features.require_email_verification', true);
    }

    /**
     * Boot render hooks for WebSocket scripts, search bar, nav switcher, and version badge.
     *
     * Injects JavaScript bundles (Echo/Reverb), the Reverb config object into window.__reverb,
     * the toolbar presence indicator, the custom search bar (when search is enabled),
     * the navigation switcher init script and toggle, extended favicon meta tags,
     * a version badge, and the AI assistant floating panel (for admin users when enabled).
     *
     * @param  Panel  $panel  The Filament panel being booted
     */
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

        // Nav search bar — replaces Filament's built-in global search
        if (config('aicl.search.enabled', false)) {
            FilamentView::registerRenderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => view('aicl::components.nav-search-bar')->render(),
            );
        }

        // Inject Reverb WebSocket config for echo.js (replaces VITE_REVERB_* env vars)
        // Config is resolved once and cached in the closure scope for Swoole workers.
        if (config('aicl.features.websockets', true)) {
            $reverbConfig = json_encode([
                'key' => config('broadcasting.connections.reverb.key', ''),
                'host' => config('aicl.ai.streaming.reverb.host', 'localhost'),
                'port' => (int) config('aicl.ai.streaming.reverb.port', 8080),
                'scheme' => config('aicl.ai.streaming.reverb.scheme', 'http'),
            ]);

            FilamentView::registerRenderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => "<script>window.__reverb={$reverbConfig};</script>",
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
        // Resolve version once at boot time (constant for the worker's lifetime).
        $version = app(VersionService::class)->current();
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): string => view('aicl::components.version-badge', [
                'version' => $version,
            ])->render(),
        );

        // AI Assistant floating widget — injected on all admin pages when enabled
        if (config('aicl.ai.assistant.enabled', false)) {
            FilamentView::registerRenderHook(
                PanelsRenderHook::BODY_END,
                function (): string {
                    $user = auth()->user();
                    $allowedRoles = config('aicl.ai.assistant.allowed_roles', ['super_admin', 'admin']);
                    if (! $user || ! $user->hasRole($allowedRoles)) {
                        return '';
                    }

                    return Blade::render('@livewire(\'aicl::ai-assistant-panel\')');
                },
            );
        }
    }

    /**
     * Get the Filament resource classes provided by AICL.
     *
     * @return array<class-string>
     */
    protected function getResources(): array
    {
        return [
            AiAgentResource::class,
            AiConversationResource::class,
            UserResource::class,
        ];
    }

    /**
     * Get the Filament page classes provided by AICL.
     *
     * Includes ops panel, activity log, backups, changelog, document browser,
     * tools, notification center, search, API tokens, and custom error pages.
     *
     * @return array<class-string>
     */
    protected function getPages(): array
    {
        return [
            ActivityLog::class,
            Backups::class,
            OpsPanel::class,
            OperationsManager::class,
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
     * Get the Filament widget classes provided by AICL.
     *
     * Includes AI agent stats, global search, presence indicator,
     * queue stats, and recent failed jobs.
     *
     * @return array<class-string<Widget>>
     */
    protected function getWidgets(): array
    {
        return [
            AiAgentStatsWidget::class,
            GlobalSearchWidget::class,
            PresenceIndicator::class,
            QueueStatsWidget::class,
            RecentFailedJobsWidget::class,
        ];
    }
}
