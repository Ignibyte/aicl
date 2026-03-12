<?php

namespace Aicl\Tests\Unit\Horizon;

use Aicl\Horizon\Horizon;
use PHPUnit\Framework\TestCase;

class HorizonTest extends TestCase
{
    protected function tearDown(): void
    {
        Horizon::$authUsing = null;
        parent::tearDown();
    }

    public function test_databases_array_contains_expected_keys(): void
    {
        $expected = ['Jobs', 'Supervisors', 'CommandQueue', 'Tags', 'Metrics', 'Locks', 'Processes'];

        $this->assertSame($expected, Horizon::$databases);
    }

    public function test_auth_sets_callback(): void
    {
        $this->assertNull(Horizon::$authUsing);

        Horizon::auth(function () {
            return true;
        });

        $this->assertNotNull(Horizon::$authUsing);
        $this->assertInstanceOf(\Closure::class, Horizon::$authUsing);
    }

    public function test_auth_returns_horizon_instance(): void
    {
        $result = Horizon::auth(function () {
            return true;
        });

        $this->assertInstanceOf(Horizon::class, $result);
    }
}
