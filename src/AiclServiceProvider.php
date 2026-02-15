<?php

namespace Aicl;

use Aicl\Auth\SamlAttributeMapper;
use Aicl\Events\DomainEventSubscriber;
use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Health\Checks\ApplicationCheck;
use Aicl\Health\Checks\ElasticsearchCheck;
use Aicl\Health\Checks\PostgresCheck;
use Aicl\Health\Checks\QueueCheck;
use Aicl\Health\Checks\RedisCheck;
use Aicl\Health\Checks\SwooleCheck;
use Aicl\Health\HealthCheckRegistry;
use Aicl\Listeners\EntityEventNotificationListener;
use Aicl\Listeners\NotificationSentLogger;
use Aicl\Models\FailureReport;
use Aicl\Models\GenerationTrace;
use Aicl\Models\GoldenAnnotation;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\Models\RlmScore;
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
use Aicl\Observers\FailureReportObserver;
use Aicl\Observers\GenerationTraceObserver;
use Aicl\Observers\GoldenAnnotationObserver;
use Aicl\Observers\PreventionRuleObserver;
use Aicl\Observers\RlmFailureDistillObserver;
use Aicl\Observers\RlmFailureObserver;
use Aicl\Observers\RlmLessonObserver;
use Aicl\Observers\RlmPatternObserver;
use Aicl\Policies\FailureReportPolicy;
use Aicl\Policies\GenerationTracePolicy;
use Aicl\Policies\PreventionRulePolicy;
use Aicl\Policies\RlmFailurePolicy;
use Aicl\Policies\RlmLessonPolicy;
use Aicl\Policies\RlmPatternPolicy;
use Aicl\Policies\RlmScorePolicy;
use Aicl\Rlm\EmbeddingService;
use Aicl\Rlm\KnowledgeSearchEngine;
use Aicl\Rlm\KnowledgeService;
use Aicl\Rlm\KnowledgeWriter;
use Aicl\Rlm\RecallService;
use Aicl\Services\EntityRegistry;
use Aicl\Services\NotificationDispatcher;
use Aicl\Services\PresenceRegistry;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AiclServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/aicl.php', 'aicl');

        $this->app->singleton(DriverRegistry::class, function ($app): DriverRegistry {
            $registry = new DriverRegistry($app);
            $registry->register(ChannelType::Slack, SlackDriver::class);
            $registry->register(ChannelType::Email, EmailDriver::class);
            $registry->register(ChannelType::Webhook, WebhookDriver::class);
            $registry->register(ChannelType::PagerDuty, PagerDutyDriver::class);
            $registry->register(ChannelType::Teams, TeamsDriver::class);
            $registry->register(ChannelType::Sms, SmsDriver::class);

            return $registry;
        });

        $this->app->singleton(ChannelRateLimiter::class);

        $this->app->singleton(NotificationDispatcher::class, function ($app): NotificationDispatcher {
            return new NotificationDispatcher(
                $app->make(DriverRegistry::class),
                $app->make(ChannelRateLimiter::class),
                $app->bound(NotificationChannelResolver::class) ? $app->make(NotificationChannelResolver::class) : null,
                $app->bound(NotificationRecipientResolver::class) ? $app->make(NotificationRecipientResolver::class) : null,
            );
        });

        $this->registerNotificationResolvers();

        $this->app->singleton(HealthCheckRegistry::class, function ($app): HealthCheckRegistry {
            $registry = new HealthCheckRegistry($app);

            $registry->registerMany([
                SwooleCheck::class,
                PostgresCheck::class,
                RedisCheck::class,
                ElasticsearchCheck::class,
                QueueCheck::class,
                ApplicationCheck::class,
            ]);

            return $registry;
        });

        $this->app->singleton(Rlm\ProjectIdentity::class);
        $this->app->singleton(Rlm\HubClient::class);
        $this->app->singleton(EmbeddingService::class);
        $this->app->singleton(KnowledgeSearchEngine::class);
        $this->app->singleton(RecallService::class);
        $this->app->singleton(KnowledgeWriter::class);
        $this->app->singleton(KnowledgeService::class);
        $this->app->singleton(EntityRegistry::class);
        $this->app->singleton(PresenceRegistry::class);

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
                $registry->registerMany($configTools);
            }

            return $registry;
        });

        $this->app->singleton(SamlAttributeMapper::class, function ($app): SamlAttributeMapper {
            $customClass = config('aicl.saml.mapper_class');
            if ($customClass && class_exists($customClass)) {
                return new $customClass;
            }

            return new SamlAttributeMapper;
        });

        $this->registerTemplateEngine();

        $this->configureNeuronAi();
        $this->configureScoutDriver();
    }

    public function boot(): void
    {
        Gate::policy(\App\Models\User::class, \Aicl\Policies\UserPolicy::class);
        Gate::policy(\Spatie\Permission\Models\Role::class, \Aicl\Policies\RolePolicy::class);

        // RLM Hub entity policies
        Gate::policy(RlmPattern::class, RlmPatternPolicy::class);
        Gate::policy(RlmFailure::class, RlmFailurePolicy::class);
        Gate::policy(FailureReport::class, FailureReportPolicy::class);
        Gate::policy(RlmLesson::class, RlmLessonPolicy::class);
        Gate::policy(GenerationTrace::class, GenerationTracePolicy::class);
        Gate::policy(PreventionRule::class, PreventionRulePolicy::class);
        Gate::policy(RlmScore::class, RlmScorePolicy::class);

        // RLM Hub entity observers
        RlmPattern::observe(RlmPatternObserver::class);
        RlmFailure::observe(RlmFailureObserver::class);
        RlmFailure::observe(RlmFailureDistillObserver::class);
        FailureReport::observe(FailureReportObserver::class);
        RlmLesson::observe(RlmLessonObserver::class);
        GenerationTrace::observe(GenerationTraceObserver::class);
        PreventionRule::observe(PreventionRuleObserver::class);
        GoldenAnnotation::observe(GoldenAnnotationObserver::class);

        Event::listen(EntityCreated::class, [EntityEventNotificationListener::class, 'handleCreated']);
        Event::listen(EntityUpdated::class, [EntityEventNotificationListener::class, 'handleUpdated']);
        Event::listen(EntityDeleted::class, [EntityEventNotificationListener::class, 'handleDeleted']);
        Event::listen(NotificationSent::class, NotificationSentLogger::class);

        // Domain event bus — auto-persists all DomainEvent subclasses
        Event::subscribe(DomainEventSubscriber::class);

        // Register entity lifecycle events for DomainEvent replay support
        EntityCreated::register();
        EntityUpdated::register();
        EntityDeleted::register();

        // Register approval workflow events for DomainEvent replay support
        \Aicl\Workflows\Events\ApprovalRequested::register();
        \Aicl\Workflows\Events\ApprovalGranted::register();
        \Aicl\Workflows\Events\ApprovalRejected::register();
        \Aicl\Workflows\Events\ApprovalRevoked::register();

        // Register session lifecycle events for DomainEvent replay support
        \Aicl\Events\SessionTerminated::register();

        // Swoole/Octane cache wiring and listeners
        Swoole\Cache\PermissionCacheManager::register();
        Swoole\Cache\WidgetStatsCacheManager::register();
        Swoole\Cache\NotificationBadgeCacheManager::register();
        Swoole\Cache\KnowledgeStatsCacheManager::register();
        Swoole\Cache\ServiceHealthCacheManager::register();
        Event::listen(\Laravel\Octane\Events\WorkerStarting::class, \Aicl\Swoole\Listeners\WarmSwooleCaches::class);
        Event::listen(\Laravel\Octane\Events\WorkerStarting::class, \Aicl\Swoole\Listeners\RestoreSwooleTimers::class);

        // Default SwooleTimer jobs — register only when Redis is reachable
        if (! app()->runningUnitTests()) {
            try {
                Swoole\SwooleTimer::every('health-refresh', 300, Jobs\RefreshHealthChecksJob::class);
                Swoole\SwooleTimer::every('delivery-cleanup', 3600, Jobs\CleanStaleDeliveriesJob::class);
            } catch (\Throwable) {
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
            Js::make('aicl-widgets', __DIR__.'/../resources/js/aicl-widgets.js'),
        ], package: 'aicl/aicl');

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
        Livewire::component('toolbar-presence', Filament\Widgets\ToolbarPresence::class);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/hub-api.php');

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
        $this->registerPresenceMiddleware();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\DiscoverPatternsCommand::class,
                Console\Commands\HubSeedCommand::class,
                Console\Commands\InstallCommand::class,
                Console\Commands\MakeEntityCommand::class,
                Console\Commands\PipelineContextCommand::class,
                Console\Commands\RemoveEntityCommand::class,
                Console\Commands\RlmCommand::class,
                Console\Commands\ScoutImportCommand::class,
                Console\Commands\UpgradeCommand::class,
                Console\Commands\ValidateEntityCommand::class,
                Console\Commands\ValidateSpecCommand::class,
            ]);
        }
    }

    /**
     * Bind optional notification resolver classes from config.
     */
    protected function registerNotificationResolvers(): void
    {
        $channelResolver = config('aicl.notifications.channel_resolver');
        if ($channelResolver && class_exists($channelResolver)) {
            $this->app->bind(NotificationChannelResolver::class, $channelResolver);
        }

        $recipientResolver = config('aicl.notifications.recipient_resolver');
        if ($recipientResolver && class_exists($recipientResolver)) {
            $this->app->bind(NotificationRecipientResolver::class, $recipientResolver);
        }
    }

    /**
     * Register the message template rendering engine singletons.
     */
    protected function registerTemplateEngine(): void
    {
        $this->app->singleton(FilterRegistry::class, function (): FilterRegistry {
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
     * Map AICL embedding/LLM config to NeuronAI's expected config keys.
     *
     * NeuronAI's EmbeddingProviderManager reads config('neuron.embedding.*').
     * We derive these from existing AICL config so no new .env vars are needed.
     */
    protected function configureNeuronAi(): void
    {
        $embeddingDriver = config('aicl.rlm.embeddings.driver', 'openai');

        $this->app['config']->set('neuron.embedding.default', $embeddingDriver === 'null' ? 'openai' : $embeddingDriver);

        $this->app['config']->set('neuron.embedding.openai', [
            'key' => (string) config('aicl.rlm.embeddings.openai.api_key', ''),
            'model' => (string) config('aicl.rlm.embeddings.openai.model', 'text-embedding-3-small'),
            'dimensions' => (int) config('aicl.rlm.embeddings.dimension', 1536),
        ]);

        $this->app['config']->set('neuron.embedding.ollama', [
            'model' => (string) config('aicl.rlm.embeddings.ollama.model', 'nomic-embed-text'),
            'url' => rtrim((string) config('aicl.rlm.embeddings.ollama.host', 'http://localhost:11434'), '/').'/api',
            'parameters' => [],
        ]);

        // LLM provider config (for future AI streaming in 4.3c)
        $this->app['config']->set('neuron.providers.anthropic', [
            'key' => (string) config('aicl.rlm.semantic.api_key', ''),
            'model' => (string) config('aicl.rlm.semantic.model', 'claude-haiku-4-5-20251001'),
        ]);
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

    /**
     * Register the presence tracking middleware alias.
     *
     * The middleware is registered as an alias so it can be added to
     * the Filament panel middleware stack via AiclPlugin.
     */
    protected function registerPresenceMiddleware(): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('track-presence', Http\Middleware\TrackPresenceMiddleware::class);
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

        // Ensure the deferred ElasticSearchServiceProvider is explicitly registered
        // so Client::class binding is available before Scout observers fire
        if (! $this->app->providerIsLoaded(\Matchish\ScoutElasticSearch\ElasticSearchServiceProvider::class)) {
            $this->app->register(\Matchish\ScoutElasticSearch\ElasticSearchServiceProvider::class);
        }
    }
}
