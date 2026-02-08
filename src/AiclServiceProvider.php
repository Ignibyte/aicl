<?php

namespace Aicl;

use Aicl\Auth\SamlAttributeMapper;
use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Listeners\EntityEventNotificationListener;
use Aicl\Listeners\NotificationSentLogger;
use Aicl\Services\NotificationDispatcher;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AiclServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/aicl.php', 'aicl');

        $this->app->singleton(NotificationDispatcher::class);

        $this->app->singleton(SamlAttributeMapper::class, function ($app): SamlAttributeMapper {
            $customClass = config('aicl.saml.mapper_class');
            if ($customClass && class_exists($customClass)) {
                return new $customClass;
            }

            return new SamlAttributeMapper;
        });

        $this->configureScoutDriver();
    }

    public function boot(): void
    {
        Gate::policy(\App\Models\User::class, \Aicl\Policies\UserPolicy::class);
        Gate::policy(\Spatie\Permission\Models\Role::class, \Aicl\Policies\RolePolicy::class);

        Event::listen(EntityCreated::class, [EntityEventNotificationListener::class, 'handleCreated']);
        Event::listen(EntityUpdated::class, [EntityEventNotificationListener::class, 'handleUpdated']);
        Event::listen(EntityDeleted::class, [EntityEventNotificationListener::class, 'handleDeleted']);
        Event::listen(NotificationSent::class, NotificationSentLogger::class);
        $this->publishes([
            __DIR__.'/../config/aicl.php' => config_path('aicl.php'),
        ], 'aicl-config');

        $this->publishes([
            __DIR__.'/../resources/assets/images' => public_path('vendor/aicl/images'),
        ], 'aicl-assets');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'aicl');

        $this->loadViewComponentsAs('aicl', [
            View\Components\SplitLayout::class,
            View\Components\CardGrid::class,
            View\Components\StatsRow::class,
            View\Components\EmptyState::class,
            View\Components\StatCard::class,
            View\Components\KpiCard::class,
            View\Components\TrendCard::class,
            View\Components\ProgressCard::class,
            View\Components\MetadataList::class,
            View\Components\InfoCard::class,
            View\Components\StatusBadge::class,
            View\Components\Timeline::class,
            View\Components\ActionBar::class,
            View\Components\QuickAction::class,
            View\Components\AlertBanner::class,
            View\Components\Divider::class,
            View\Components\Spinner::class,
            View\Components\AuthSplitLayout::class,
            View\Components\Tabs::class,
            View\Components\TabPanel::class,
            View\Components\IgnibyteLogo::class,
        ]);

        Livewire::component('aicl-activity-feed', \Aicl\Livewire\ActivityFeed::class);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Load social auth routes if enabled
        if (config('aicl.features.social_login', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/socialite.php');
        }

        // Load SAML SSO routes and register Socialite SAML2 driver if enabled
        if (config('aicl.features.saml', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/saml.php');

            Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event): void {
                $event->extendSocialite('saml2', \SocialiteProviders\Saml2\Provider::class);
            });
        }

        $this->registerSecurityMiddleware();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\InstallCommand::class,
                Console\Commands\MakeEntityCommand::class,
                Console\Commands\ValidateEntityCommand::class,
                Console\Commands\ScoutImportCommand::class,
            ]);
        }
    }

    /**
     * Register OWASP security middleware.
     *
     * SecurityHeaders is registered as global middleware so it applies to all
     * routes including Filament's custom middleware stack.
     * ApiRequestLog is applied only to the api middleware group.
     */
    protected function registerSecurityMiddleware(): void
    {
        /** @var \Illuminate\Foundation\Http\Kernel $kernel */
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->pushMiddleware(Http\Middleware\SecurityHeadersMiddleware::class);

        if (config('aicl.security.api_logging', true)) {
            /** @var \Illuminate\Routing\Router $router */
            $router = $this->app['router'];
            $router->pushMiddlewareToGroup('api', Http\Middleware\ApiRequestLogMiddleware::class);
        }
    }

    /**
     * Configure Scout to use an external search engine when the feature flag is set.
     *
     * Supported drivers: 'meilisearch', 'elasticsearch', or false (database default).
     */
    protected function configureScoutDriver(): void
    {
        $driver = config('aicl.features.scout_driver', false);

        if (! $driver) {
            return;
        }

        match ($driver) {
            'meilisearch' => $this->configureMeilisearch(),
            'elasticsearch' => $this->configureElasticsearch(),
            default => null,
        };
    }

    protected function configureMeilisearch(): void
    {
        config([
            'scout.driver' => 'meilisearch',
            'scout.meilisearch.host' => config('aicl.search.meilisearch.host', 'http://meilisearch:7700'),
            'scout.meilisearch.key' => config('aicl.search.meilisearch.key', ''),
        ]);
    }

    protected function configureElasticsearch(): void
    {
        if (! class_exists(\Matchish\ScoutElasticSearch\ElasticSearchServiceProvider::class)) {
            return;
        }

        $host = config('aicl.search.elasticsearch.host', 'elasticsearch');
        $port = config('aicl.search.elasticsearch.port', 9200);
        $scheme = config('aicl.search.elasticsearch.scheme', 'http');

        config([
            'scout.driver' => \Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine::class,
            'elasticsearch.host' => "{$scheme}://{$host}:{$port}",
        ]);
    }
}
