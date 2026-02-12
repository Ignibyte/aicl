<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\ProjectIdentity;
use Tests\TestCase;

class RlmCommandSyncPullTest extends TestCase
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

    public function test_sync_pull_fails_when_hub_disabled(): void
    {
        config(['aicl.rlm.hub.enabled' => false]);
        $this->app->instance(ProjectIdentity::class, new ProjectIdentity);

        $this->artisan('aicl:rlm', ['action' => 'sync', '--pull' => true])
            ->assertFailed()
            ->expectsOutputToContain('not enabled');
    }
}
