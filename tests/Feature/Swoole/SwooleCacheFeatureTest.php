<?php

namespace Aicl\Tests\Feature\Swoole;

use Aicl\Swoole\SwooleCache;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;
use Swoole\Table;

class SwooleCacheFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension not available.');
        }

        SwooleCache::reset();
    }

    protected function tearDown(): void
    {
        SwooleCache::reset();

        parent::tearDown();
    }

    public function test_set_and_get_with_real_swoole_table(): void
    {
        $table = $this->createTable(100, 5000);

        SwooleCache::register('test', rows: 100, ttl: 60, valueSize: 5000);
        SwooleCache::useResolver(fn (): Table => $table);

        $stored = SwooleCache::set('test', 'key1', ['name' => 'John']);
        $result = SwooleCache::get('test', 'key1');

        $this->assertTrue($stored);
        $this->assertSame(['name' => 'John'], $result);
    }

    public function test_ttl_expiration_with_real_swoole_table(): void
    {
        $table = $this->createTable(100, 5000);

        SwooleCache::register('test', rows: 100, ttl: 60, valueSize: 5000);
        SwooleCache::useResolver(fn (): Table => $table);

        // Write a row directly with an already-expired timestamp
        $table->set('expiring', [
            'value' => json_encode('data'),
            'expires_at' => time() - 10,
        ]);

        // SwooleCache::get() should detect expiration and return null
        $result = SwooleCache::get('test', 'expiring');

        $this->assertNull($result);
        $this->assertSame(0, $table->count(), 'Expired row should be lazily deleted');
    }

    public function test_cross_coroutine_visibility(): void
    {
        $table = $this->createTable(100, 5000);

        SwooleCache::register('test', rows: 100, ttl: 60, valueSize: 5000);
        SwooleCache::useResolver(fn (): Table => $table);

        $readResult = null;

        Coroutine\run(function () use (&$readResult): void {
            // Write in one coroutine
            Coroutine::create(function (): void {
                SwooleCache::set('test', 'shared_key', 'from_writer');
            });

            // Small delay to ensure write completes
            Coroutine::sleep(0.01);

            // Read in another coroutine
            Coroutine::create(function () use (&$readResult): void {
                $readResult = SwooleCache::get('test', 'shared_key');
            });

            Coroutine::sleep(0.01);
        });

        $this->assertSame('from_writer', $readResult);
    }

    public function test_flush_with_real_swoole_table(): void
    {
        $table = $this->createTable(100, 5000);

        SwooleCache::register('test', rows: 100, ttl: 60, valueSize: 5000);
        SwooleCache::useResolver(fn (): Table => $table);

        SwooleCache::set('test', 'a', 1);
        SwooleCache::set('test', 'b', 2);
        SwooleCache::set('test', 'c', 3);

        $this->assertSame(3, SwooleCache::count('test'));

        SwooleCache::flush('test');

        $this->assertSame(0, SwooleCache::count('test'));
    }

    public function test_forget_with_real_swoole_table(): void
    {
        $table = $this->createTable(100, 5000);

        SwooleCache::register('test', rows: 100, ttl: 60, valueSize: 5000);
        SwooleCache::useResolver(fn (): Table => $table);

        SwooleCache::set('test', 'key1', 'data');

        $this->assertTrue(SwooleCache::forget('test', 'key1'));
        $this->assertNull(SwooleCache::get('test', 'key1'));
    }

    public function test_is_available_with_resolver(): void
    {
        $table = $this->createTable(10, 1000);

        SwooleCache::register('test', rows: 10, ttl: 60, valueSize: 1000);
        SwooleCache::useResolver(fn (): Table => $table);

        $this->assertTrue(SwooleCache::isAvailable());
    }

    /**
     * Create a real Swoole Table with the SwooleCache schema.
     */
    private function createTable(int $rows, int $valueSize): Table
    {
        $table = new Table($rows);
        $table->column('value', Table::TYPE_STRING, $valueSize);
        $table->column('expires_at', Table::TYPE_INT, 8);
        $table->create();

        return $table;
    }
}
