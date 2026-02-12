<?php

namespace Aicl\Tests\Unit;

use Aicl\Traits\PaginatesApiRequests;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class PaginatesApiRequestsTest extends TestCase
{
    use PaginatesApiRequests;

    public function test_default_per_page_is_15(): void
    {
        $request = Request::create('/test');

        $this->assertEquals(15, $this->getPerPage($request));
    }

    public function test_respects_per_page_param(): void
    {
        $request = Request::create('/test', 'GET', ['per_page' => 25]);

        $this->assertEquals(25, $this->getPerPage($request));
    }

    public function test_caps_at_100(): void
    {
        $request = Request::create('/test', 'GET', ['per_page' => 999]);

        $this->assertEquals(100, $this->getPerPage($request));
    }

    public function test_custom_default(): void
    {
        $request = Request::create('/test');

        $this->assertEquals(30, $this->getPerPage($request, 30));
    }

    public function test_custom_max(): void
    {
        $request = Request::create('/test', 'GET', ['per_page' => 60]);

        $this->assertEquals(50, $this->getPerPage($request, 15, 50));
    }

    public function test_zero_per_page_clamps_to_1(): void
    {
        $request = Request::create('/test', 'GET', ['per_page' => 0]);

        $this->assertEquals(1, $this->getPerPage($request));
    }

    public function test_negative_per_page_clamps_to_1(): void
    {
        $request = Request::create('/test', 'GET', ['per_page' => -5]);

        $this->assertEquals(1, $this->getPerPage($request));
    }
}
