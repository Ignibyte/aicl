<?php

namespace Aicl\Tests\Feature\Swoole;

use Aicl\Rlm\EmbeddingService;
use Aicl\Rlm\KnowledgeService;
use Aicl\Swoole\Cache\ServiceHealthCacheManager;
use Aicl\Swoole\SwooleCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServiceHealthCacheFeatureTest extends TestCase
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();

        SwooleCache::reset();

        SwooleCache::useClock(fn (): int => Carbon::now()->timestamp);

        SwooleCache::useResolver(function (string $table): ?object {
            if (! isset($this->tables[$table])) {
                $this->tables[$table] = [];
            }

            return $this->createMockTable($table);
        });

        // Register all tables that event listeners may reference
        SwooleCache::register('widget_stats', rows: 100, ttl: 60, valueSize: 2000);
        SwooleCache::register('notification_badges', rows: 1000, ttl: 60, valueSize: 100);
        SwooleCache::register('permissions', rows: 2000, ttl: 300, valueSize: 5000);
        SwooleCache::register('rlm_stats', rows: 10, ttl: 300, valueSize: 5000);

        ServiceHealthCacheManager::register();
    }

    protected function tearDown(): void
    {
        SwooleCache::reset();
        $this->tables = [];
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_knowledge_service_caches_es_availability(): void
    {
        // ES is available
        Http::fake([
            'http://elasticsearch:9200*' => Http::response(['status' => 'green'], 200),
            '*' => Http::response('', 500),
        ]);

        $service = $this->createKnowledgeService();

        // First call — cache miss, hits HTTP
        $this->assertTrue($service->isElasticsearchAvailable());

        // Verify cached in SwooleCache
        $cached = ServiceHealthCacheManager::getCachedAvailability('elasticsearch');
        $this->assertTrue($cached);

        // Reset per-instance cache to prove SwooleCache is used
        $service->resetAvailabilityCache();

        // Second call — SwooleCache hit, no HTTP call
        Http::fake(['*' => Http::response('', 500)]); // would fail if hit
        $this->assertTrue($service->isElasticsearchAvailable());
    }

    public function test_es_unavailable_is_cached_and_recovers_after_ttl(): void
    {
        $esAvailable = false;

        Http::fake(function () use (&$esAvailable) {
            return $esAvailable
                ? Http::response(['status' => 'green'], 200)
                : Http::response('', 500);
        });

        $service = $this->createKnowledgeService();

        // First call — cache miss, HTTP fails
        $this->assertFalse($service->isElasticsearchAvailable());

        // Cached as unavailable
        $this->assertFalse(ServiceHealthCacheManager::getCachedAvailability('elasticsearch'));

        // Advance past 30s TTL
        Carbon::setTestNow(now()->addSeconds(31));

        // Cache expired
        $this->assertNull(ServiceHealthCacheManager::getCachedAvailability('elasticsearch'));

        // ES is now available
        $esAvailable = true;

        // Reset per-instance cache
        $service->resetAvailabilityCache();

        // New check — cache miss, HTTP succeeds
        $this->assertTrue($service->isElasticsearchAvailable());
    }

    public function test_non_octane_falls_through_to_per_instance_cache(): void
    {
        SwooleCache::reset();
        SwooleCache::register('service_health', rows: 10, ttl: 30, valueSize: 200);
        $this->assertFalse(SwooleCache::isAvailable());

        Http::fake(['*' => Http::response('', 500)]);

        $service = $this->createKnowledgeService();
        $this->assertFalse($service->isElasticsearchAvailable());

        // Per-instance cache still works
        $this->assertFalse($service->isElasticsearchAvailable());

        // Re-set resolver for tearDown
        SwooleCache::useResolver(function (string $table): ?object {
            if (! isset($this->tables[$table])) {
                $this->tables[$table] = [];
            }

            return $this->createMockTable($table);
        });
    }

    // -- Helpers --

    private function createKnowledgeService(): KnowledgeService
    {
        $embeddingService = $this->createMock(EmbeddingService::class);
        $embeddingService->method('isAvailable')->willReturn(false);

        return new KnowledgeService($embeddingService);
    }

    private function createMockTable(string $tableName): object
    {
        $data = &$this->tables[$tableName];

        return new class($data) implements \Countable, \IteratorAggregate
        {
            public function __construct(private array &$data) {}

            public function set(string $key, array $value): bool
            {
                $this->data[$key] = $value;

                return true;
            }

            /**
             * @return array<string, mixed>|false
             */
            public function get(string $key, ?string $field = null): array|false
            {
                if (! isset($this->data[$key])) {
                    return false;
                }

                if ($field !== null) {
                    return $this->data[$key][$field] ?? false;
                }

                return $this->data[$key];
            }

            public function del(string $key): bool
            {
                if (! isset($this->data[$key])) {
                    return false;
                }

                unset($this->data[$key]);

                return true;
            }

            public function exist(string $key): bool
            {
                return isset($this->data[$key]);
            }

            public function count(): int
            {
                return count($this->data);
            }

            public function getIterator(): \ArrayIterator
            {
                return new \ArrayIterator($this->data);
            }
        };
    }
}
