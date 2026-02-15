<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Aicl\Notifications\Templates\Filters\TruncateFilter;
use PHPUnit\Framework\TestCase;

class TruncateFilterTest extends TestCase
{
    private TruncateFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new TruncateFilter;
    }

    public function test_implements_template_filter(): void
    {
        $this->assertInstanceOf(TemplateFilter::class, $this->filter);
    }

    public function test_truncates_long_string_with_default_limit(): void
    {
        $long = str_repeat('a', 150);
        $result = $this->filter->apply($long, null, []);

        // Default limit is 100, Str::limit adds '...'
        $this->assertSame(103, strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    public function test_truncates_with_custom_length(): void
    {
        $result = $this->filter->apply('Hello World ABCDEF', '10', []);

        $this->assertSame('Hello Worl...', $result);
    }

    public function test_does_not_truncate_short_string(): void
    {
        $result = $this->filter->apply('short', '100', []);

        $this->assertSame('short', $result);
    }

    public function test_handles_empty_string(): void
    {
        $result = $this->filter->apply('', '10', []);

        $this->assertSame('', $result);
    }

    public function test_exact_length_is_not_truncated(): void
    {
        $result = $this->filter->apply('12345', '5', []);

        $this->assertSame('12345', $result);
    }

    public function test_one_over_length_is_truncated(): void
    {
        $result = $this->filter->apply('123456', '5', []);

        $this->assertSame('12345...', $result);
    }
}
