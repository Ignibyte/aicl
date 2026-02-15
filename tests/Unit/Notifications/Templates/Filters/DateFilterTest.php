<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Aicl\Notifications\Templates\Filters\DateFilter;
use PHPUnit\Framework\TestCase;

class DateFilterTest extends TestCase
{
    private DateFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new DateFilter;
    }

    public function test_implements_template_filter(): void
    {
        $this->assertInstanceOf(TemplateFilter::class, $this->filter);
    }

    public function test_formats_date_with_default_format(): void
    {
        $result = $this->filter->apply('2025-06-15 14:30:00', null, []);

        $this->assertSame('2025-06-15', $result);
    }

    public function test_formats_date_with_custom_format(): void
    {
        $result = $this->filter->apply('2025-06-15 14:30:00', 'M d, Y', []);

        $this->assertSame('Jun 15, 2025', $result);
    }

    public function test_formats_date_with_time_format(): void
    {
        $result = $this->filter->apply('2025-06-15 14:30:00', 'H:i', []);

        $this->assertSame('14:30', $result);
    }

    public function test_returns_empty_for_empty_string(): void
    {
        $result = $this->filter->apply('', null, []);

        $this->assertSame('', $result);
    }

    public function test_returns_original_value_for_invalid_date(): void
    {
        $result = $this->filter->apply('not-a-date', null, []);

        $this->assertSame('not-a-date', $result);
    }

    public function test_handles_iso8601_format(): void
    {
        $result = $this->filter->apply('2025-06-15T14:30:00+00:00', 'Y-m-d', []);

        $this->assertSame('2025-06-15', $result);
    }
}
