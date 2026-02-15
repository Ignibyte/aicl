<?php

namespace Aicl\Tests\Unit\Health;

use Aicl\Health\Contracts\ServiceHealthCheck;
use Aicl\Health\HealthCheckRegistry;
use Aicl\Health\ServiceCheckResult;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class HealthCheckRegistryTest extends TestCase
{
    private Container $container;

    private HealthCheckRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        $this->registry = new HealthCheckRegistry($this->container);
    }

    // ── register() ───────────────────────────────────────────

    public function test_register_adds_check_class(): void
    {
        $this->registry->register(StubCheckA::class);

        $this->assertContains(StubCheckA::class, $this->registry->registered());
    }

    public function test_register_does_not_add_duplicates(): void
    {
        $this->registry->register(StubCheckA::class);
        $this->registry->register(StubCheckA::class);
        $this->registry->register(StubCheckA::class);

        $this->assertCount(1, $this->registry->registered());
    }

    // ── registerMany() ───────────────────────────────────────

    public function test_register_many_adds_multiple(): void
    {
        $this->registry->registerMany([
            StubCheckA::class,
            StubCheckB::class,
        ]);

        $this->assertCount(2, $this->registry->registered());
        $this->assertContains(StubCheckA::class, $this->registry->registered());
        $this->assertContains(StubCheckB::class, $this->registry->registered());
    }

    public function test_register_many_skips_duplicates(): void
    {
        $this->registry->register(StubCheckA::class);
        $this->registry->registerMany([StubCheckA::class, StubCheckB::class]);

        $this->assertCount(2, $this->registry->registered());
    }

    // ── runAll() ─────────────────────────────────────────────

    public function test_run_all_returns_results_sorted_by_order(): void
    {
        // StubCheckB has order 10, StubCheckA has order 50
        $this->registry->registerMany([
            StubCheckA::class,
            StubCheckB::class,
        ]);

        $results = $this->registry->runAll();

        $this->assertCount(2, $results);
        $this->assertSame('StubB', $results[0]->name);
        $this->assertSame('StubA', $results[1]->name);
    }

    public function test_run_all_resolves_checks_from_container(): void
    {
        $this->container->bind(StubCheckA::class, fn () => new StubCheckA);
        $this->registry->register(StubCheckA::class);

        $results = $this->registry->runAll();

        $this->assertCount(1, $results);
        $this->assertInstanceOf(ServiceCheckResult::class, $results[0]);
    }

    public function test_run_all_returns_empty_when_no_checks_registered(): void
    {
        $results = $this->registry->runAll();

        $this->assertSame([], $results);
    }

    public function test_run_all_returns_service_check_result_instances(): void
    {
        $this->registry->register(StubCheckA::class);

        $results = $this->registry->runAll();

        foreach ($results as $result) {
            $this->assertInstanceOf(ServiceCheckResult::class, $result);
        }
    }

    // ── registered() ─────────────────────────────────────────

    public function test_registered_returns_class_names(): void
    {
        $this->registry->registerMany([StubCheckA::class, StubCheckB::class]);

        $registered = $this->registry->registered();

        $this->assertSame([StubCheckA::class, StubCheckB::class], $registered);
    }

    public function test_registered_returns_empty_array_initially(): void
    {
        $this->assertSame([], $this->registry->registered());
    }
}

// ── Stub Checks ──────────────────────────────────────────────

class StubCheckA implements ServiceHealthCheck
{
    public function check(): ServiceCheckResult
    {
        return ServiceCheckResult::healthy(
            name: 'StubA',
            icon: 'heroicon-o-check-circle',
            details: ['Test' => 'A'],
        );
    }

    public function order(): int
    {
        return 50;
    }
}

class StubCheckB implements ServiceHealthCheck
{
    public function check(): ServiceCheckResult
    {
        return ServiceCheckResult::healthy(
            name: 'StubB',
            icon: 'heroicon-o-check-circle',
            details: ['Test' => 'B'],
        );
    }

    public function order(): int
    {
        return 10;
    }
}
