<?php

declare(strict_types=1);

namespace Aicl;

use Aicl\Auth\SamlAttributeMapper;
use Aicl\Components\ComponentDiscoveryService;
use Aicl\Components\ComponentRegistry;
use Aicl\Events\DomainEventSubscriber;
use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Events\SessionTerminated;
use Aicl\Health\Checks\ApplicationCheck;
use Aicl\Health\Checks\ElasticsearchCheck;
use Aicl\Health\Checks\PostgresCheck;
use Aicl\Health\Checks\QueueCheck;
use Aicl\Health\Checks\RedisCheck;
use Aicl\Health\Checks\ReverbCheck;
use Aicl\Health\Checks\SchedulerCheck;
use Aicl\Health\Checks\SwooleCheck;
use Aicl\Health\HealthCheckRegistry;
use Aicl\Listeners\EntityEventNotificationListener;
use Aicl\Listeners\NotificationSentLogger;
use Aicl\Listeners\ScheduleEventSubscriber;
use Aicl\Livewire\ActivityFeed;
use Aicl\Livewire\AiAssistantPanel;
use Aicl\Livewire\AuditTable;
use Aicl\Livewire\DomainEventTable;
use Aicl\Livewire\FailedDeliveriesTable;
use Aicl\Livewire\NotificationLogTable;
use Aicl\Livewire\ScheduleHistoryTable;
use Aicl\Mcp\McpRegistry;
use Aicl\Mcp\Tools\SearchArchitectureDocsTool;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use Aicl\Models\AiMessage;
use Aicl\Notifications\ChannelRateLimiter;
use Aicl\Notifications\Contracts\NotificationChannelResolver;
use Aicl\Notifications\Contracts\NotificationRecipientResolver;
use Aicl\Notifications\DriverRegistry;
use Aicl\Notifications\Drivers\EmailDriver;
use Aicl\Notifications\Drivers\PagerDutyDriver;
use Aicl\Notifications\Drivers\SlackDriver;
use Aicl\Notifications\Drivers\SmsDriver;
use Aicl\Notifications\Drivers\TeamsDriver;
use Aicl\Notifications\Drivers\WebhookDriver;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Adapters\EmailHtmlAdapter;
use Aicl\Notifications\Templates\Adapters\PagerDutyAdapter;
use Aicl\Notifications\Templates\Adapters\PlainTextAdapter;
use Aicl\Notifications\Templates\Adapters\SlackBlockAdapter;
use Aicl\Notifications\Templates\Adapters\TeamsCardAdapter;
use Aicl\Notifications\Templates\Adapters\WebhookJsonAdapter;
use Aicl\Notifications\Templates\FilterRegistry;
use Aicl\Notifications\Templates\Filters\DateFilter;
use Aicl\Notifications\Templates\Filters\DefaultFilter;
use Aicl\Notifications\Templates\Filters\LowerFilter;
use Aicl\Notifications\Templates\Filters\Nl2brFilter;
use Aicl\Notifications\Templates\Filters\NumberFilter;
use Aicl\Notifications\Templates\Filters\RawFilter;
use Aicl\Notifications\Templates\Filters\RelativeFilter;
use Aicl\Notifications\Templates\Filters\StripTagsFilter;
use Aicl\Notifications\Templates\Filters\TitleFilter;
use Aicl\Notifications\Templates\Filters\TruncateFilter;
use Aicl\Notifications\Templates\Filters\UpperFilter;
use Aicl\Notifications\Templates\FormatAdapterRegistry;
use Aicl\Notifications\Templates\MessageTemplateRenderer;
use Aicl\Notifications\Templates\Resolvers\AppVariableResolver;
use Aicl\Notifications\Templates\Resolvers\ChannelVariableResolver;
use Aicl\Notifications\Templates\Resolvers\ModelVariableResolver;
use Aicl\Notifications\Templates\Resolvers\RecipientVariableResolver;
use Aicl\Notifications\Templates\Resolvers\UserVariableResolver;
use Aicl\Observers\AiAgentObserver;
use Aicl\Observers\AiConversationObserver;
use Aicl\Observers\AiMessageObserver;
use Aicl\Policies\AiAgentPolicy;
use Aicl\Policies\AiConversationPolicy;
use Aicl\Policies\RolePolicy;
use Aicl\Policies\UserPolicy;
use Aicl\Services\EntityRegistry;
use Aicl\Services\NotificationDispatcher;
use Aicl\Services\PresenceRegistry;
use Aicl\Swoole\Listeners\RestoreSwooleTimers;
use Aicl\Swoole\Listeners\WarmSwooleCaches;
use Aicl\Workflows\Events\ApprovalGranted;
use Aicl\Workflows\Events\ApprovalRejected;
use Aicl\Workflows\Events\ApprovalRequested;
use Aicl\Workflows\Events\ApprovalRevoked;
use App\Models\User;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Octane\Events\WorkerStarting;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Matchish\ScoutElasticSearch\ElasticSearchServiceProvider;
use Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Saml2\Provider;
use Spatie\Permission\Models\Role;

/**
 * AICL framework service provider.
 *
 * Bootstraps the entire AICL package: configuration merging (package defaults,
 * project overrides, local overrides), singleton registrations for core services
 * (notifications, health checks, entity registry, presence, MCP, AI tools,
 * template engine), Filament asset publishing, Livewire component wiring,
 * route loading, middleware registration, and Artisan command registration.
 *
 * Configuration precedence (highest wins):
 *   1. config/local.php (Drupal-style, gitignored)
 *   2. config/aicl-project.php (project-level overrides)
 *   3. packages/aicl/config/aicl.php (package defaults)
 *
 * @see AiclPlugin  Filament panel plugin that registers pages, resources, and widgets
 * @see EntityRegistry  Central registry of all entity types
 */
class AiclServiceProvider extends ServiceProvider
{
    /**
     * Current package version, used by VersionService and the admin version badge.
     */
    public const VERSION = '1.16.6';

    /**
     * Register package services, singletons, and configuration.
     *
     * Merges package config with project and local overrides, then binds
     * all core singletons into the container: DriverRegistry, NotificationDispatcher,
     * HealthCheckRegistry, EntityRegistry, PresenceRegistry, McpRegistry,
     * ComponentDiscoveryService, ComponentRegistry, AiToolRegistry,
     * SamlAttributeMapper, and the template rendering engine.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/aicl.php', 'aicl');
        $this->mergeConfigFrom(__DIR__.'/../config/aicl-project.php', 'aicl-project');

        $this->mergeProjectConfigOverrides();

        $this->loadLocalConfig();
        $this->registerBoostTools();

        $this->app->singleton(DriverRegistry::class, function ($app): DriverRegistry {
            // @codeCoverageIgnoreStart — Untestable in unit context
            $registry = new DriverRegistry($app);
            $registry->register(ChannelType::Slack, SlackDriver::class);
            $registry->register(ChannelType::Email, EmailDriver::class);
            $registry->register(ChannelType::Webhook, WebhookDriver::class);
            $registry->register(ChannelType::PagerDuty, PagerDutyDriver::class);
            $registry->register(ChannelType::Teams, TeamsDriver::class);
            $registry->register(ChannelType::Sms, SmsDriver::class);

            return $registry;
            // @codeCoverageIgnoreEnd
        });

        $this->app->singleton(ChannelRateLimiter::class);

        $this->app->singleton(NotificationDispatcher::class, function ($app): NotificationDispatcher {
            // @codeCoverageIgnoreStart — Untestable in unit context
            return new NotificationDispatcher(
                $app->make(DriverRegistry::class),
                $app->make(ChannelRateLimiter::class),
                $app->bound(NotificationChannelResolver::class) ? $app->make(NotificationChannelResolver::class) : null,
                $app->bound(NotificationRecipientResolver::class) ? $app->make(NotificationRecipientResolver::class) : null,
            );
            // @codeCoverageIgnoreEnd
        });

        $this->registerNotificationResolvers();

        $this->app->singleton(HealthCheckRegistry::class, function ($app): HealthCheckRegistry {
            // @codeCoverageIgnoreStart — Untestable in unit context
            $registry = new HealthCheckRegistry($app);

            $registry->registerMany([
                SwooleCheck::class,
                PostgresCheck::class,
                RedisCheck::class,
                ReverbCheck::class,
                ElasticsearchCheck::class,
                QueueCheck::class,
                SchedulerCheck::class,
                ApplicationCheck::class,
            ]);

            return $registry;
            // @codeCoverageIgnoreEnd
        });

        $this->app->singleton(EntityRegistry::class);
        $this->app->singleton(PresenceRegistry::class);
        $this->app->singleton(McpRegistry::class);
        $this->app->singleton(ComponentDiscoveryService::class);
        $this->app->singleton(ComponentRegistry::class);

        $this->app->singleton(AI\AiToolRegistry::class, function ($app): AI\AiToolRegistry {
            $registry = new AI\AiToolRegistry($app);

            $registry->registerMany([
                AI\Tools\WhosOnlineTool::class,
                AI\Tools\CurrentUserTool::class,
                AI\Tools\QueryEntityTool::class,
                AI\Tools\EntityCountTool::class,
                AI\Tools\HealthStatusTool::class,
            ]);

            // Register client-configured tools
            $configTools = config('aicl.ai.tools', []);
            if (! empty($configTools)) {
                // @codeCoverageIgnoreStart — Untestable in unit context
                $registry->registerMany($configTools);
                // @codeCoverageIgnoreEnd
            }

            return $registry;
        });

        $this->app->singleton(SamlAttributeMapper::class, function ($app): SamlAttributeMapper {
            $customClass = config('aicl.saml.mapper_class');
            if ($customClass && class_exists($customClass)) {
                /** @var SamlAttributeMapper */
                // @codeCoverageIgnoreStart — Untestable in unit context
                return new $customClass;
                // @codeCoverageIgnoreEnd
            }

            return new SamlAttributeMapper;
        });

        $this->registerTemplateEngine();

        $this->configureScoutDriver();

        if (config('aicl.search.enabled', false)) {
            // @codeCoverageIgnoreStart — Untestable in unit context
            $this->app->register(Providers\SearchServiceProvider::class);
            // @codeCoverageIgnoreEnd
        }

        if (config('aicl.features.horizon', true)) {
            $this->app->register(Horizon\HorizonServiceProvider::class);
        }
    }

    /**
     * Boot package services: routes, views, assets, middleware, listeners, and commands.
     *
     * Registers policies, observers, event listeners, Swoole cache managers,
     * SwooleTimer jobs, rate limiters, publishable assets, Blade/Livewire
     * components, routes (web, API, social, MCP, SAML), security and presence
     * middleware, and Artisan console commands.
     */
    /**
     * Merge aicl-project config overrides into the main aicl config.
     *
     * @codeCoverageIgnore Reason: framework-bootstrap -- Only runs when aicl-project.php has overrides
     */
    private function mergeProjectConfigOverrides(): void
    {
        $projectConfig = config('aicl-project', []);
        if (! empty($projectConfig)) {
            config(['aicl' => array_replace_recursive(config('aicl', []), $projectConfig)]);
        }
    }

    public function boot(): void
    {
        // Force HTTPS URL generation when APP_URL uses https.
        // This prevents mixed-content issues when nginx proxies HTTPS
        // to Swoole/Octane over internal HTTP.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Register Passport token scopes for API and MCP access control
        if (class_exists(Passport::class)) {
            Passport::tokensCan([
                'read' => 'Read — List and view entities',
                'write' => 'Write — Create and update entities',
                'delete' => 'Delete — Remove entities',
                'mcp' => 'MCP — Access MCP server endpoint',
                'transitions' => 'Transitions — Change entity states',
            ]);
        }

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(AiAgent::class, AiAgentPolicy::class);
        Gate::policy(AiConversation::class, AiConversationPolicy::class);

        AiAgent::observe(AiAgentObserver::class);
        AiConversation::observe(AiConversationObserver::class);
        AiMessage::observe(AiMessageObserver::class);

        Event::listen(EntityCreated::class, [EntityEventNotificationListener::class, 'handleCreated']);
        Event::listen(EntityUpdated::class, [EntityEventNotificationListener::class, 'handleUpdated']);
        Event::listen(EntityDeleted::class, [EntityEventNotificationListener::class, 'handleDeleted']);
        Event::listen(NotificationSent::class, NotificationSentLogger::class);

        // Domain event bus — auto-persists all DomainEvent subclasses
        Event::subscribe(DomainEventSubscriber::class);

        // Scheduler event tracking — records schedule execution history
        Event::subscribe(ScheduleEventSubscriber::class);

        // Register entity lifecycle events for DomainEvent replay support
        EntityCreated::register();
        EntityUpdated::register();
        EntityDeleted::register();

        // Register approval workflow events for DomainEvent replay support
        ApprovalRequested::register();
        ApprovalGranted::register();
        ApprovalRejected::register();
        ApprovalRevoked::register();

        // Register session lifecycle events for DomainEvent replay support
        SessionTerminated::register();

        // Swoole/Octane cache wiring and listeners
        Swoole\Cache\PermissionCacheManager::register();
        Swoole\Cache\NotificationBadgeCacheManager::register();
        Swoole\Cache\ServiceHealthCacheManager::register();

        Event::listen(WorkerStarting::class, WarmSwooleCaches::class);
        Event::listen(WorkerStarting::class, RestoreSwooleTimers::class);

        // Default SwooleTimer jobs — register only when Redis is reachable
        if (! app()->runningUnitTests()) {
            try {
                // @codeCoverageIgnoreStart — Untestable in unit context
                Swoole\SwooleTimer::every('health-refresh', 300, Jobs\RefreshHealthChecksJob::class);
                Swoole\SwooleTimer::every('delivery-cleanup', 3600, Jobs\CleanStaleDeliveriesJob::class);
            } catch (\Throwable) {
                // @codeCoverageIgnoreEnd
                // Redis unavailable — timers will register on next Swoole worker boot via restore()
            }
        }

        // AI assistant rate limiter
        $rateConfig = config('aicl.ai.rate_limit', ['max_attempts' => 10, 'decay_minutes' => 1]);
        RateLimiter::for('ai_assistant', fn (Request $request) => Limit::perMinutes(
            $rateConfig['decay_minutes'],
            $rateConfig['max_attempts'],
        )->by($request->user()?->id ?? $request->ip())); // @phpstan-ignore nullsafe.neverNull

        $this->publishes([
            __DIR__.'/../config/aicl.php' => config_path('aicl.php'),
        ], 'aicl-config');

        $this->publishes([
            __DIR__.'/../resources/assets/images' => public_path('vendor/aicl/images'),
        ], 'aicl-assets');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'aicl');

        FilamentAsset::register([
            Css::make('aicl-navigation-switcher', __DIR__.'/../resources/css/navigation-switcher.css'),
            Js::make('aicl-widgets', __DIR__.'/../resources/js/aicl-widgets.js'),
        ], package: 'aicl/aicl');

        $this->bootComponentRegistry();

        // Register non-SDC utility view components (no component.json)
        $this->loadViewComponentsAs('aicl', []);

        Livewire::component('aicl::ai-assistant-panel', AiAssistantPanel::class);
        Livewire::component('aicl-activity-feed', ActivityFeed::class);
        Livewire::component('aicl::audit-table', AuditTable::class);
        Livewire::component('aicl::domain-event-table', DomainEventTable::class);
        Livewire::component('aicl::notification-log-table', NotificationLogTable::class);
        Livewire::component('aicl::schedule-history-table', ScheduleHistoryTable::class);
        Livewire::component('aicl::failed-deliveries-table', FailedDeliveriesTable::class);
        // Horizon Livewire components registered in HorizonServiceProvider::boot()
        // BatchesTable is always available (reads from job_batches DB table, not Horizon-dependent)
        Livewire::component('aicl::horizon-batches-table', Horizon\Livewire\BatchesTable::class);
        Livewire::component('toolbar-presence', Filament\Widgets\ToolbarPresence::class);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Load social auth routes if enabled
        if (config('aicl.features.social_login', false)) {
            // @codeCoverageIgnoreStart — Untestable in unit context
            $this->loadRoutesFrom(__DIR__.'/../routes/socialite.php');
            // @codeCoverageIgnoreEnd
        }

        // Load MCP server routes if enabled and laravel/mcp is installed
        if (config('aicl.features.mcp', false) && class_exists(Mcp::class)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/mcp.php');
        }

        // Load SAML SSO routes and register Socialite SAML2 driver if enabled
        if (config('aicl.features.saml', false)) {
            // @codeCoverageIgnoreStart — Untestable in unit context
            $this->loadRoutesFrom(__DIR__.'/../routes/saml.php');

            Event::listen(function (SocialiteWasCalled $event): void {
                $event->extendSocialite('saml2', Provider::class);
            });
            // @codeCoverageIgnoreEnd
        }

        $this->registerSecurityMiddleware();
        $this->registerPresenceMiddleware();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\ComponentsCommand::class,
                Console\Commands\InstallCommand::class,
                Console\Commands\MakeEntityCommand::class,
                Console\Commands\PipelineContextCommand::class,
                Console\Commands\PruneSearchLogsCommand::class,
                Console\Commands\RemoveEntityCommand::class,
                Console\Commands\ScoutImportCommand::class,
                Console\Commands\SearchReindexCommand::class,
                Console\Commands\PruneScheduleHistoryCommand::class,
                Console\Commands\UpgradeCommand::class,
                Console\Commands\ValidateSpecCommand::class,
                Console\Commands\CompactConversationsCommand::class,
            ]);
        }
    }

    /**
     * Boot the SDC Component Registry.
     *
     * Scans framework components from packages/aicl/components/ and registers
     * each as a Blade component. Client components in app/Components/ can
     * override framework components by using the same tag name.
     */
    protected function bootComponentRegistry(): void
    {
        /** @var ComponentRegistry $registry */
        $registry = $this->app->make(ComponentRegistry::class);

        $scanPaths = [
            [
                'path' => dirname(__DIR__).'/components',
                'source' => 'framework',
                'namespace' => 'Aicl\\View\\Components',
            ],
        ];

        // Support client-side component directory
        $clientPath = app_path('Components');
        if (is_dir($clientPath)) {
            // @codeCoverageIgnoreStart — Untestable in unit context
            $scanPaths[] = [
                'path' => $clientPath,
                'source' => 'client',
                'namespace' => 'App\\Components',
            ];
            // @codeCoverageIgnoreEnd
        }

        $registry->boot($scanPaths);

        // Register discovered components as Blade components
        foreach ($registry->all() as $definition) {
            $shortTag = $definition->shortTag();
            Blade::component($definition->class, "aicl-{$shortTag}");
        }
    }

    /**
     * Bind optional notification resolver classes from config.
     */
    protected function registerNotificationResolvers(): void
    {
        $channelResolver = config('aicl.notifications.channel_resolver');
        if ($channelResolver && class_exists($channelResolver)) {
            // @codeCoverageIgnoreStart — Untestable in unit context
            $this->app->bind(NotificationChannelResolver::class, $channelResolver);
            // @codeCoverageIgnoreEnd
        }

        $recipientResolver = config('aicl.notifications.recipient_resolver');
        if ($recipientResolver && class_exists($recipientResolver)) {
            // @codeCoverageIgnoreStart — Untestable in unit context
            $this->app->bind(NotificationRecipientResolver::class, $recipientResolver);
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Register the message template rendering engine singletons.
     */
    protected function registerTemplateEngine(): void
    {
        $this->app->singleton(FilterRegistry::class, function (): FilterRegistry {
            // @codeCoverageIgnoreStart — Untestable in unit context
            $registry = new FilterRegistry;
            $registry->register('upper', new UpperFilter);
            $registry->register('lower', new LowerFilter);
            $registry->register('title', new TitleFilter);
            $registry->register('truncate', new TruncateFilter);
            $registry->register('relative', new RelativeFilter);
            $registry->register('date', new DateFilter);
            $registry->register('default', new DefaultFilter);
            $registry->register('raw', new RawFilter);
            $registry->register('nl2br', new Nl2brFilter);
            $registry->register('strip_tags', new StripTagsFilter);
            $registry->register('number', new NumberFilter);

            return $registry;
        });

        $this->app->singleton(FormatAdapterRegistry::class, function (): FormatAdapterRegistry {
            $registry = new FormatAdapterRegistry;
            $registry->register(ChannelType::Sms, new PlainTextAdapter);
            $registry->register(ChannelType::Slack, new SlackBlockAdapter);
            $registry->register(ChannelType::Email, new EmailHtmlAdapter);
            $registry->register(ChannelType::Teams, new TeamsCardAdapter);
            $registry->register(ChannelType::PagerDuty, new PagerDutyAdapter);
            $registry->register(ChannelType::Webhook, new WebhookJsonAdapter);

            return $registry;
        });

        $this->app->singleton(MessageTemplateRenderer::class, function ($app): MessageTemplateRenderer {
            $renderer = new MessageTemplateRenderer(
                $app->make(FilterRegistry::class),
                $app->make(FormatAdapterRegistry::class),
                (bool) config('aicl.notifications.templates.escape_html', true),
            );

            $renderer->registerResolver('model', new ModelVariableResolver);
            $renderer->registerResolver('user', new UserVariableResolver);
            $renderer->registerResolver('recipient', new RecipientVariableResolver);
            $renderer->registerResolver('app', new AppVariableResolver);
            $renderer->registerResolver('channel', new ChannelVariableResolver);

            return $renderer;
            // @codeCoverageIgnoreEnd
        });
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
        /** @var Kernel $kernel */
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->pushMiddleware(Http\Middleware\SecurityHeadersMiddleware::class);

        if (config('aicl.security.api_logging', true)) {
            /** @var Router $router */
            $router = app('router');
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
            // @codeCoverageIgnoreStart — Untestable in unit context
            'meilisearch' => $this->configureMeilisearch(),
            'elasticsearch' => $this->configureElasticsearch(),
            default => null,
            // @codeCoverageIgnoreEnd
        };
    }

    /**
     * Set Scout config values for Meilisearch driver.
     *
     * Reads host and key from aicl.search.meilisearch config namespace.
     */
    protected function configureMeilisearch(): void
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        config([
            'scout.driver' => 'meilisearch',
            'scout.meilisearch.host' => config('aicl.search.meilisearch.host', 'http://meilisearch:7700'),
            'scout.meilisearch.key' => config('aicl.search.meilisearch.key', ''),
        ]);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Register the presence tracking middleware alias.
     *
     * The middleware is registered as an alias so it can be added to
     * the Filament panel middleware stack via AiclPlugin.
     */
    protected function registerPresenceMiddleware(): void
    {
        /** @var Router $router */
        $router = app('router');
        $router->aliasMiddleware('track-presence', Http\Middleware\TrackPresenceMiddleware::class);
    }

    /**
     * Set Scout config values for the Elasticsearch driver.
     *
     * Configures host, port, scheme, and optional authentication (API key
     * or basic auth) for the matchish/laravel-scout-elasticsearch package.
     * Explicitly registers the deferred ElasticSearchServiceProvider to ensure
     * the Client binding is available before Scout observers fire.
     */
    protected function configureElasticsearch(): void
    {
        if (! class_exists(ElasticSearchServiceProvider::class)) {
            // @codeCoverageIgnoreStart — Untestable in unit context
            return;
            // @codeCoverageIgnoreEnd
        }

        $host = config('aicl.search.elasticsearch.host', 'elasticsearch');
        $port = config('aicl.search.elasticsearch.port', 9200);
        $scheme = config('aicl.search.elasticsearch.scheme', 'http');

        $esConfig = [
            'scout.driver' => ElasticSearchEngine::class,
            'elasticsearch.host' => "{$scheme}://{$host}:{$port}",
        ];

        // Pass authentication to the Elasticsearch client when configured
        $apiKey = config('aicl.search.elasticsearch.api_key');
        $username = config('aicl.search.elasticsearch.username');
        $password = config('aicl.search.elasticsearch.password');

        if ($apiKey) {
            // @codeCoverageIgnoreStart — Untestable in unit context
            $esConfig['elasticsearch.api-key'] = $apiKey;
        } elseif ($username && $password) {
            $esConfig['elasticsearch.basicAuthentication'] = [$username, $password];
            // @codeCoverageIgnoreEnd
        }

        config($esConfig);

        // Ensure the deferred ElasticSearchServiceProvider is explicitly registered
        // so Client::class binding is available before Scout observers fire
        if (! $this->app->providerIsLoaded(ElasticSearchServiceProvider::class)) {
            $this->app->register(ElasticSearchServiceProvider::class);
        }
    }

    /**
     * Load local config overrides (Drupal-style settings.php pattern).
     *
     * Loads config/local.php first (instance-specific: secrets, credentials).
     * When APP_ENV=testing, also loads config/local.testing.php on top
     * (lightweight drivers for test runs). Both are gitignored.
     *
     * Precedence: package defaults < project config < local.php < local.testing.php.
     */
    protected function loadLocalConfig(): void
    {
        $this->applyLocalOverrides($this->app->configPath('local.php'));

        // When running tests, layer testing overrides on top
        if ($this->app->environment('testing')) {
            $this->applyLocalOverrides($this->app->configPath('local.testing.php'));
        }
    }

    /**
     * Apply dot-notation overrides from a PHP config file.
     */
    protected function applyLocalOverrides(string $path): void
    {
        if (! file_exists($path)) {
            return;
        }

        $overrides = require $path;

        if (! is_array($overrides)) {
            return;
        }

        foreach ($overrides as $key => $value) {
            config()->set($key, $value);
        }
    }

    /**
     * Register AICL tools into Laravel Boost's MCP server.
     *
     * Pushes the architecture docs search tool into Boost's include list
     * so it's automatically available when Boost MCP is running.
     */
    protected function registerBoostTools(): void
    {
        $existing = config('boost.mcp.tools.include', []);
        $aiclTools = [
            SearchArchitectureDocsTool::class,
        ];

        config()->set('boost.mcp.tools.include', array_merge($existing, $aiclTools));
    }
}
