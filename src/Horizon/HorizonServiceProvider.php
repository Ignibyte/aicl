<?php

namespace Aicl\Horizon;

use Aicl\Horizon\Connectors\RedisConnector;
use Aicl\Horizon\Livewire as HorizonLivewire;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class HorizonServiceProvider extends ServiceProvider
{
    use EventMap, ServiceBindings;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerEvents();
        $this->registerCommands();
        $this->registerLivewireComponents();
    }

    /**
     * Register the Horizon job events.
     *
     * @return void
     */
    protected function registerEvents()
    {
        $events = $this->app->make(Dispatcher::class);

        foreach ($this->events as $event => $listeners) {
            foreach ($listeners as $listener) {
                $events->listen($event, $listener);
            }
        }
    }

    /**
     * Register the Horizon Artisan commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ClearCommand::class,
                Console\ClearMetricsCommand::class,
                Console\ContinueCommand::class,
                Console\ContinueSupervisorCommand::class,
                Console\ForgetFailedCommand::class,
                Console\HorizonCommand::class,
                Console\ListCommand::class,
                Console\PauseCommand::class,
                Console\PauseSupervisorCommand::class,
                Console\PurgeCommand::class,
                Console\SupervisorCommand::class,
                Console\SupervisorStatusCommand::class,
                Console\TerminateCommand::class,
                Console\TimeoutCommand::class,
                Console\WorkCommand::class,
            ]);
        }

        $this->commands([
            Console\SnapshotCommand::class,
            Console\StatusCommand::class,
            Console\SupervisorsCommand::class,
        ]);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(Console\WorkCommand::class, function ($app) {
            return new Console\WorkCommand($app['queue.worker'], $app['cache.store']);
        });

        $this->configure();
        $this->registerServices();
        $this->registerQueueConnectors();
    }

    /**
     * Setup the configuration for Horizon.
     *
     * @return void
     */
    protected function configure()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/aicl-horizon.php', 'aicl-horizon'
        );

        Horizon::use(config('aicl-horizon.use', 'default'));
    }

    /**
     * Register Horizon's services in the container.
     *
     * @return void
     */
    protected function registerServices()
    {
        foreach ($this->serviceBindings as $key => $value) {
            is_numeric($key)
                ? $this->app->singleton($value)
                : $this->app->singleton($key, $value);
        }
    }

    /**
     * Register the custom queue connectors for Horizon.
     *
     * @return void
     */
    protected function registerQueueConnectors()
    {
        $this->callAfterResolving(QueueManager::class, function ($manager) {
            $manager->addConnector('redis', function () {
                return new RedisConnector($this->app['redis']);
            });
        });
    }

    /**
     * Register Horizon Livewire components.
     */
    protected function registerLivewireComponents(): void
    {
        if (class_exists(Livewire::class)) {
            Livewire::component('aicl::horizon-recent-jobs-table', HorizonLivewire\RecentJobsTable::class);
            Livewire::component('aicl::horizon-pending-jobs-table', HorizonLivewire\PendingJobsTable::class);
            Livewire::component('aicl::horizon-completed-jobs-table', HorizonLivewire\CompletedJobsTable::class);
            Livewire::component('aicl::horizon-failed-jobs-table', HorizonLivewire\FailedJobsTable::class);
            Livewire::component('aicl::horizon-monitored-tags-table', HorizonLivewire\MonitoredTagsTable::class);
            Livewire::component('aicl::horizon-metrics-charts', HorizonLivewire\MetricsCharts::class);
            // BatchesTable is registered in AiclServiceProvider (always available, not Horizon-dependent)
        }
    }
}
