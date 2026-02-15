<?php

namespace Aicl\Tests\Unit\Health;

use Aicl\Health\Checks\PostgresCheck;
use Aicl\Health\ServiceStatus;
use Tests\TestCase;

class PostgresCheckTest extends TestCase
{
    private PostgresCheck $check;

    protected function setUp(): void
    {
        parent::setUp();

        $this->check = new PostgresCheck;
    }

    // ── Healthy ──────────────────────────────────────────────

    public function test_returns_healthy_when_database_is_available(): void
    {
        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
        $this->assertSame('PostgreSQL', $result->name);
        $this->assertSame('heroicon-o-circle-stack', $result->icon);
        $this->assertNull($result->error);
    }

    public function test_healthy_includes_version(): void
    {
        $result = $this->check->check();

        $this->assertArrayHasKey('Version', $result->details);
        $this->assertNotEmpty($result->details['Version']);
    }

    public function test_healthy_includes_active_connections(): void
    {
        $result = $this->check->check();

        $this->assertArrayHasKey('Active Connections', $result->details);
        $this->assertIsNumeric($result->details['Active Connections']);
    }

    public function test_healthy_includes_database_size(): void
    {
        $result = $this->check->check();

        $this->assertArrayHasKey('Database Size', $result->details);
        $this->assertMatchesRegularExpression('/[\d.]+ (B|KB|MB|GB|TB)/', $result->details['Database Size']);
    }

    public function test_healthy_includes_connection_name(): void
    {
        $result = $this->check->check();

        $this->assertArrayHasKey('Connection', $result->details);
        $this->assertSame(config('database.default'), $result->details['Connection']);
    }

    // ── Down ─────────────────────────────────────────────────

    public function test_returns_down_when_connection_fails(): void
    {
        // Temporarily set an invalid connection to force a failure
        config(['database.default' => 'invalid_connection']);
        config(['database.connections.invalid_connection' => [
            'driver' => 'pgsql',
            'host' => '255.255.255.255',
            'port' => 1,
            'database' => 'nonexistent',
            'username' => 'nobody',
            'password' => 'none',
        ]]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Down, $result->status);
        $this->assertSame('PostgreSQL', $result->name);
        $this->assertNotNull($result->error);
    }

    // ── order() ──────────────────────────────────────────────

    public function test_order_returns_20(): void
    {
        $this->assertSame(20, $this->check->order());
    }

    // ── parseVersion() ───────────────────────────────────────

    public function test_parse_version_extracts_postgres_version(): void
    {
        $method = new \ReflectionMethod(PostgresCheck::class, 'parseVersion');
        $method->setAccessible(true);

        $result = $method->invoke($this->check, 'PostgreSQL 17.2 (Debian 17.2-1.pgdg120+1) on x86_64');

        $this->assertSame('17.2', $result);
    }

    public function test_parse_version_returns_full_string_when_no_match(): void
    {
        $method = new \ReflectionMethod(PostgresCheck::class, 'parseVersion');
        $method->setAccessible(true);

        $result = $method->invoke($this->check, 'Unknown database 1.0');

        $this->assertSame('Unknown database 1.0', $result);
    }

    // ── formatBytes() ────────────────────────────────────────

    public function test_format_bytes_formats_bytes(): void
    {
        $method = new \ReflectionMethod(PostgresCheck::class, 'formatBytes');
        $method->setAccessible(true);

        $this->assertSame('0 B', $method->invoke($this->check, 0));
        $this->assertSame('500 B', $method->invoke($this->check, 500));
    }

    public function test_format_bytes_formats_kilobytes(): void
    {
        $method = new \ReflectionMethod(PostgresCheck::class, 'formatBytes');
        $method->setAccessible(true);

        $this->assertSame('1 KB', $method->invoke($this->check, 1024));
    }

    public function test_format_bytes_formats_megabytes(): void
    {
        $method = new \ReflectionMethod(PostgresCheck::class, 'formatBytes');
        $method->setAccessible(true);

        $this->assertSame('1 MB', $method->invoke($this->check, 1024 * 1024));
    }

    public function test_format_bytes_formats_gigabytes(): void
    {
        $method = new \ReflectionMethod(PostgresCheck::class, 'formatBytes');
        $method->setAccessible(true);

        $this->assertSame('1 GB', $method->invoke($this->check, 1024 * 1024 * 1024));
    }
}
