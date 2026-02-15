<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Aicl\Notifications\Templates\Filters\RelativeFilter;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class RelativeFilterTest extends TestCase
{
    private RelativeFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new RelativeFilter;
    }

    public function test_implements_template_filter(): void
    {
        $this->assertInstanceOf(TemplateFilter::class, $this->filter);
    }

    public function test_converts_datetime_to_diff_for_humans(): void
    {
        $date = Carbon::now()->subHour()->toIso8601String();
        $result = $this->filter->apply($date, null, []);

        $this->assertSame('1 hour ago', $result);
    }

    public function test_handles_future_datetime(): void
    {
        $date = Carbon::now()->addDays(2)->toIso8601String();
        $result = $this->filter->apply($date, null, []);

        $this->assertStringContainsString('from now', $result);
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

    public function test_handles_standard_date_format(): void
    {
        $date = Carbon::now()->subMinutes(5)->format('Y-m-d H:i:s');
        $result = $this->filter->apply($date, null, []);

        $this->assertSame('5 minutes ago', $result);
    }
}
