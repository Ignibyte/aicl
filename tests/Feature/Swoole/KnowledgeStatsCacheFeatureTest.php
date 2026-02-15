<?php

namespace Aicl\Tests\Feature\Swoole;

use Aicl\Models\RlmPattern;
use Aicl\Rlm\EmbeddingService;
use Aicl\Rlm\KnowledgeService;
use Aicl\Swoole\Cache\KnowledgeStatsCacheManager;
use Aicl\Swoole\SwooleCache;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KnowledgeStatsCacheFeatureTest extends TestCase
{
    use RefreshDatabase;

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

        // Register all cache tables since service provider event listeners
        // from other managers survive SwooleCache::reset() and may fire.
        SwooleCache::register('widget_stats', rows: 100, ttl: 60, valueSize: 2000);
        SwooleCache::register('notification_badges', rows: 1000, ttl: 60, valueSize: 100);
        SwooleCache::register('permissions', rows: 2000, ttl: 300, valueSize: 5000);
        SwooleCache::register('service_health', rows: 10, ttl: 30, valueSize: 200);

        KnowledgeStatsCacheManager::register();
    }

    protected function tearDown(): void
    {
        SwooleCache::reset();
        $this->tables = [];
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_knowledge_service_stats_uses_cache(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        \App\Models\User::factory()->create();
        RlmPattern::factory()->count(3)->create(['is_active' => true]);

        $service = $this->createKnowledgeService();

        // First call — cache miss, computes from DB
        $stats1 = $service->stats();
        $this->assertSame(3, $stats1['patterns']);

        // Verify cache populated
        $cached = KnowledgeStatsCacheManager::getCachedStats();
        $this->assertNotNull($cached);
        $this->assertSame(3, $cached['patterns']);

        // Second call — cache hit (returns same data)
        $stats2 = $service->stats();
        $this->assertSame(3, $stats2['patterns']);

        // Runtime service fields are always present
        $this->assertArrayHasKey('storage', $stats2);
        $this->assertArrayHasKey('search_engine', $stats2);
        $this->assertArrayHasKey('embeddings', $stats2);
    }

    public function test_knowledge_service_stats_rebuilds_after_invalidation(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        \App\Models\User::factory()->create();
        RlmPattern::factory()->count(2)->create(['is_active' => true]);

        $service = $this->createKnowledgeService();

        // Populate cache
        $stats1 = $service->stats();
        $this->assertSame(2, $stats1['patterns']);

        // Create new pattern — invalidates cache
        RlmPattern::factory()->create(['is_active' => true]);

        // Cache should be invalidated
        $this->assertNull(KnowledgeStatsCacheManager::getCachedStats());

        // Recompute
        $stats2 = $service->stats();
        $this->assertSame(3, $stats2['patterns']);
    }

    public function test_non_octane_environment_computes_directly(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        SwooleCache::reset();
        SwooleCache::register('rlm_stats', rows: 10, ttl: 300, valueSize: 5000);
        SwooleCache::register('widget_stats', rows: 100, ttl: 60, valueSize: 2000);
        SwooleCache::register('notification_badges', rows: 1000, ttl: 60, valueSize: 100);
        SwooleCache::register('permissions', rows: 2000, ttl: 300, valueSize: 5000);
        SwooleCache::register('service_health', rows: 10, ttl: 30, valueSize: 200);
        $this->assertFalse(SwooleCache::isAvailable());

        \App\Models\User::factory()->create();
        RlmPattern::factory()->count(2)->create(['is_active' => true]);

        $service = $this->createKnowledgeService();
        $stats = $service->stats();

        $this->assertSame(2, $stats['patterns']);
        $this->assertSame('postgresql', $stats['storage']);

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
