<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Aicl\Notifications\Templates\Filters\DefaultFilter;
use PHPUnit\Framework\TestCase;

class DefaultFilterTest extends TestCase
{
    private DefaultFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new DefaultFilter;
    }

    public function test_implements_template_filter(): void
    {
        $this->assertInstanceOf(TemplateFilter::class, $this->filter);
    }

    public function test_returns_fallback_when_value_is_empty(): void
    {
        $result = $this->filter->apply('', 'fallback_value', []);

        $this->assertSame('fallback_value', $result);
    }

    public function test_returns_value_when_not_empty(): void
    {
        $result = $this->filter->apply('actual_value', 'fallback_value', []);

        $this->assertSame('actual_value', $result);
    }

    public function test_returns_empty_when_both_empty(): void
    {
        $result = $this->filter->apply('', null, []);

        $this->assertSame('', $result);
    }

    public function test_returns_empty_string_fallback_when_no_argument(): void
    {
        $result = $this->filter->apply('', null, []);

        $this->assertSame('', $result);
    }

    public function test_whitespace_is_not_empty(): void
    {
        $result = $this->filter->apply(' ', 'fallback', []);

        $this->assertSame(' ', $result);
    }
}
