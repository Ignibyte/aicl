<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\SemanticCache;
use Aicl\Rlm\SemanticCheckRegistry;
use Aicl\Rlm\SemanticResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemanticCacheTest extends TestCase
{
    use RefreshDatabase;

    private SemanticCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        config(['aicl.rlm.semantic.cache_ttl' => 604800]);
        $this->cache = new SemanticCache;
    }

    public function test_cache_key_is_deterministic(): void
    {
        $check = SemanticCheckRegistry::all()[0];
        $files = ['migration' => 'CREATE TABLE...', 'factory' => 'class Factory...'];

        $key1 = $this->cache->cacheKey($check, $files);
        $key2 = $this->cache->cacheKey($check, $files);

        $this->assertSame($key1, $key2);
        $this->assertSame(64, strlen($key1));
    }

    public function test_cache_key_differs_for_different_content(): void
    {
        $check = SemanticCheckRegistry::all()[0];

        $key1 = $this->cache->cacheKey($check, ['migration' => 'v1']);
        $key2 = $this->cache->cacheKey($check, ['migration' => 'v2']);

        $this->assertNotSame($key1, $key2);
    }

    public function test_cache_key_differs_for_different_checks(): void
    {
        $checks = SemanticCheckRegistry::all();
        $files = ['migration' => 'CREATE TABLE...'];

        $key1 = $this->cache->cacheKey($checks[0], $files);
        $key2 = $this->cache->cacheKey($checks[1], $files);

        $this->assertNotSame($key1, $key2);
    }

    public function test_cache_key_order_independent(): void
    {
        $check = SemanticCheckRegistry::all()[0];

        $key1 = $this->cache->cacheKey($check, ['migration' => 'a', 'factory' => 'b']);
        $key2 = $this->cache->cacheKey($check, ['factory' => 'b', 'migration' => 'a']);

        $this->assertSame($key1, $key2);
    }

    public function test_put_and_get(): void
    {
        $check = SemanticCheckRegistry::all()[0];
        $result = new SemanticResult(
            check: $check,
            passed: true,
            message: 'All types match',
            confidence: 0.95,
        );

        $key = 'test-key-'.uniqid();
        $this->cache->put($key, $result, 'TestEntity');

        $cached = $this->cache->get($key);
        $this->assertNotNull($cached);
        $this->assertTrue($cached->passed);
        $this->assertSame('All types match', $cached->message);
        $this->assertSame(0.95, $cached->confidence);
        $this->assertSame($check->name, $cached->check->name);
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $this->assertNull($this->cache->get('nonexistent-key'));
    }

    public function test_skipped_results_not_cached(): void
    {
        $check = SemanticCheckRegistry::all()[0];
        $result = new SemanticResult(
            check: $check,
            passed: false,
            message: 'API unavailable',
            skipped: true,
        );

        $key = 'skipped-key-'.uniqid();
        $this->cache->put($key, $result, 'TestEntity');

        $this->assertNull($this->cache->get($key));
    }

    public function test_clear_for_entity(): void
    {
        $check = SemanticCheckRegistry::all()[0];
        $result = new SemanticResult(check: $check, passed: true, message: 'ok');

        $this->cache->put('key-a', $result, 'EntityA');
        $this->cache->put('key-b', $result, 'EntityB');

        $this->assertNotNull($this->cache->get('key-a'));
        $this->assertNotNull($this->cache->get('key-b'));

        $cleared = $this->cache->clearForEntity('EntityA');
        $this->assertSame(1, $cleared);
        $this->assertNull($this->cache->get('key-a'));
        $this->assertNotNull($this->cache->get('key-b'));
    }

    public function test_prune_removes_expired_entries(): void
    {
        $check = SemanticCheckRegistry::all()[0];
        $result = new SemanticResult(check: $check, passed: true, message: 'ok');

        // Insert with very short TTL
        config(['aicl.rlm.semantic.cache_ttl' => -1]);
        $this->cache->put('expired-key', $result, 'TestEntity');

        // Reset TTL and insert a valid entry
        config(['aicl.rlm.semantic.cache_ttl' => 604800]);
        $this->cache->put('valid-key', $result, 'TestEntity');

        $pruned = $this->cache->prune();
        $this->assertSame(1, $pruned);
        $this->assertNull($this->cache->get('expired-key'));
        $this->assertNotNull($this->cache->get('valid-key'));
    }

    public function test_put_overwrites_existing_key(): void
    {
        $check = SemanticCheckRegistry::all()[0];
        $key = 'overwrite-key';

        $result1 = new SemanticResult(check: $check, passed: false, message: 'fail');
        $this->cache->put($key, $result1, 'TestEntity');

        $cached = $this->cache->get($key);
        $this->assertFalse($cached->passed);

        $result2 = new SemanticResult(check: $check, passed: true, message: 'pass');
        $this->cache->put($key, $result2, 'TestEntity');

        $cached = $this->cache->get($key);
        $this->assertTrue($cached->passed);
        $this->assertSame('pass', $cached->message);
    }
}
