<?php

declare(strict_types=1);

namespace Aicl\Providers;

use Aicl\Observers\SearchObserver;
use Aicl\Search\SearchIndexingService;
use Aicl\Search\SearchService;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;

/** Registers Elasticsearch client, search services, and entity model search observers. */
/**
 * @codeCoverageIgnore Service provider boot
 */
class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function (): Client {
            $config = config('aicl.search.elasticsearch', []);

            $scheme = $config['scheme'] ?? 'http';
            $host = $config['host'] ?? 'elasticsearch';
            $port = (int) ($config['port'] ?? 9200);

            $builder = ClientBuilder::create()
                ->setHosts(["{$scheme}://{$host}:{$port}"]);

            if (! empty($config['api_key'])) {
                $builder->setApiKey($config['api_key']);
            } elseif (! empty($config['username']) && ! empty($config['password'])) {
                $builder->setBasicAuthentication($config['username'], $config['password']);
            }

            return $builder->build();
        });

        $this->app->singleton(SearchIndexingService::class, function (): SearchIndexingService {
            return new SearchIndexingService($this->app->make(Client::class));
        });

        $this->app->singleton(SearchService::class, function (): SearchService {
            return new SearchService($this->app->make(Client::class));
        });
    }

    public function boot(): void
    {
        if (! config('aicl.search.enabled', false)) {
            return;
        }

        $this->registerSearchObservers();
    }

    protected function registerSearchObservers(): void
    {
        $entities = config('aicl.search.entities', []);

        foreach (array_keys($entities) as $modelClass) {
            $modelClass = (string) $modelClass;
            if (class_exists($modelClass)) {
                $modelClass::observe(SearchObserver::class);
            }
        }
    }
}
