<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Aicl\Notifications\Templates\Filters\NumberFilter;
use PHPUnit\Framework\TestCase;

class NumberFilterTest extends TestCase
{
    private NumberFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new NumberFilter;
    }

    public function test_implements_template_filter(): void
    {
        $this->assertInstanceOf(TemplateFilter::class, $this->filter);
    }

    public function test_formats_number_with_default_decimals(): void
    {
        $result = $this->filter->apply('1234567', null, []);

        $this->assertSame('1,234,567', $result);
    }

    public function test_formats_number_with_two_decimals(): void
    {
        $result = $this->filter->apply('1234.5', '2', []);

        $this->assertSame('1,234.50', $result);
    }

    public function test_formats_number_with_zero_decimals(): void
    {
        $result = $this->filter->apply('1234.56', '0', []);

        $this->assertSame('1,235', $result);
    }

    public function test_handles_zero(): void
    {
        $result = $this->filter->apply('0', '2', []);

        $this->assertSame('0.00', $result);
    }

    public function test_handles_negative_number(): void
    {
        $result = $this->filter->apply('-1234.5', '1', []);

        $this->assertSame('-1,234.5', $result);
    }

    public function test_handles_empty_string_as_zero(): void
    {
        $result = $this->filter->apply('', '2', []);

        $this->assertSame('0.00', $result);
    }

    public function test_handles_non_numeric_as_zero(): void
    {
        $result = $this->filter->apply('abc', null, []);

        $this->assertSame('0', $result);
    }
}
