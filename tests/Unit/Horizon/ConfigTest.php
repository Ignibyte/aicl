<?php

namespace Aicl\Tests\Unit\Horizon;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = require __DIR__.'/../../../config/aicl-horizon.php';
    }

    public function test_config_has_use_key(): void
    {
        $this->assertArrayHasKey('use', $this->config);
        $this->assertSame('default', $this->config['use']);
    }

    public function test_config_has_prefix_key(): void
    {
        $this->assertArrayHasKey('prefix', $this->config);
        $this->assertIsString($this->config['prefix']);
    }

    public function test_config_has_waits_thresholds(): void
    {
        $this->assertArrayHasKey('waits', $this->config);
        $this->assertArrayHasKey('redis:default', $this->config['waits']);
        $this->assertSame(60, $this->config['waits']['redis:default']);
    }

    public function test_config_has_trim_settings(): void
    {
        $this->assertArrayHasKey('trim', $this->config);

        $trim = $this->config['trim'];
        $this->assertArrayHasKey('recent', $trim);
        $this->assertArrayHasKey('pending', $trim);
        $this->assertArrayHasKey('completed', $trim);
        $this->assertArrayHasKey('recent_failed', $trim);
        $this->assertArrayHasKey('failed', $trim);
        $this->assertArrayHasKey('monitored', $trim);
    }

    public function test_recent_trim_defaults_to_60_minutes(): void
    {
        $this->assertSame(60, $this->config['trim']['recent']);
    }

    public function test_failed_trim_defaults_to_one_week(): void
    {
        // 10080 minutes = 7 days
        $this->assertSame(10080, $this->config['trim']['failed']);
    }

    public function test_config_has_silenced_arrays(): void
    {
        $this->assertArrayHasKey('silenced', $this->config);
        $this->assertArrayHasKey('silenced_tags', $this->config);
        $this->assertIsArray($this->config['silenced']);
        $this->assertIsArray($this->config['silenced_tags']);
    }

    public function test_config_has_metrics_settings(): void
    {
        $this->assertArrayHasKey('metrics', $this->config);
        $this->assertArrayHasKey('trim_snapshots', $this->config['metrics']);
        $this->assertSame(24, $this->config['metrics']['trim_snapshots']['job']);
        $this->assertSame(24, $this->config['metrics']['trim_snapshots']['queue']);
    }

    public function test_fast_termination_defaults_to_false(): void
    {
        $this->assertFalse($this->config['fast_termination']);
    }

    public function test_memory_limit_defaults_to_64(): void
    {
        $this->assertSame(64, $this->config['memory_limit']);
    }

    public function test_config_has_defaults_section(): void
    {
        $this->assertArrayHasKey('defaults', $this->config);
        $this->assertArrayHasKey('supervisor-1', $this->config['defaults']);
    }

    public function test_default_supervisor_config(): void
    {
        $supervisor = $this->config['defaults']['supervisor-1'];

        $this->assertSame('redis', $supervisor['connection']);
        $this->assertSame(['default'], $supervisor['queue']);
        $this->assertSame('auto', $supervisor['balance']);
        $this->assertSame('time', $supervisor['autoScalingStrategy']);
        $this->assertSame(1, $supervisor['maxProcesses']);
        $this->assertSame(128, $supervisor['memory']);
        $this->assertSame(3, $supervisor['tries']);
        $this->assertSame(60, $supervisor['timeout']);
    }

    public function test_config_has_environments(): void
    {
        $this->assertArrayHasKey('environments', $this->config);
        $this->assertArrayHasKey('production', $this->config['environments']);
        $this->assertArrayHasKey('local', $this->config['environments']);
    }

    public function test_production_has_higher_max_processes(): void
    {
        $production = $this->config['environments']['production']['supervisor-1'];

        $this->assertSame(10, $production['maxProcesses']);
    }

    public function test_local_has_limited_processes(): void
    {
        $local = $this->config['environments']['local']['supervisor-1'];

        $this->assertSame(3, $local['maxProcesses']);
    }
}
