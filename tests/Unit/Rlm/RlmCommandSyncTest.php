<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\ProjectIdentity;
use Tests\TestCase;

class RlmCommandSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'aicl.rlm.hub.enabled' => true,
            'aicl.rlm.hub.url' => 'https://hub.example.com',
            'aicl.rlm.hub.token' => 'test-token-123',
            'aicl.rlm.hub.timeout' => 10,
        ]);

        $identity = new ProjectIdentity;
        $this->app->instance(ProjectIdentity::class, $identity);
    }

    public function test_sync_without_flags_shows_error(): void
    {
        $this->artisan('aicl:rlm', ['action' => 'sync'])
            ->assertFailed()
            ->expectsOutputToContain('--push or --pull');
    }

    public function test_sync_push_fails_when_hub_disabled(): void
    {
        config(['aicl.rlm.hub.enabled' => false]);
        $this->app->instance(ProjectIdentity::class, new ProjectIdentity);

        $this->artisan('aicl:rlm', ['action' => 'sync', '--push' => true])
            ->assertFailed()
            ->expectsOutputToContain('not enabled');
    }
}
