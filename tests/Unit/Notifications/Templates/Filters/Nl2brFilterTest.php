<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Aicl\Notifications\Templates\Filters\Nl2brFilter;
use PHPUnit\Framework\TestCase;

class Nl2brFilterTest extends TestCase
{
    private Nl2brFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new Nl2brFilter;
    }

    public function test_implements_template_filter(): void
    {
        $this->assertInstanceOf(TemplateFilter::class, $this->filter);
    }

    public function test_converts_newlines_to_br(): void
    {
        $result = $this->filter->apply("line1\nline2", null, []);

        $this->assertStringContainsString('<br />', $result);
        $this->assertStringContainsString('line1', $result);
        $this->assertStringContainsString('line2', $result);
    }

    public function test_handles_carriage_return_newlines(): void
    {
        $result = $this->filter->apply("line1\r\nline2", null, []);

        $this->assertStringContainsString('<br />', $result);
    }

    public function test_handles_empty_string(): void
    {
        $this->assertSame('', $this->filter->apply('', null, []));
    }

    public function test_no_newlines_passes_through(): void
    {
        $this->assertSame('no newlines here', $this->filter->apply('no newlines here', null, []));
    }

    public function test_multiple_newlines(): void
    {
        $result = $this->filter->apply("a\nb\nc", null, []);

        $this->assertSame(2, substr_count($result, '<br />'));
    }
}
