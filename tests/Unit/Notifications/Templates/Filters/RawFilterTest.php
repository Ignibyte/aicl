<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Aicl\Notifications\Templates\Filters\RawFilter;
use PHPUnit\Framework\TestCase;

class RawFilterTest extends TestCase
{
    private RawFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new RawFilter;
    }

    public function test_implements_template_filter(): void
    {
        $this->assertInstanceOf(TemplateFilter::class, $this->filter);
    }

    public function test_passes_value_through_unchanged(): void
    {
        $this->assertSame('hello world', $this->filter->apply('hello world', null, []));
    }

    public function test_passes_html_through_unchanged(): void
    {
        $html = '<b>bold</b> <script>alert("xss")</script>';
        $this->assertSame($html, $this->filter->apply($html, null, []));
    }

    public function test_handles_empty_string(): void
    {
        $this->assertSame('', $this->filter->apply('', null, []));
    }

    public function test_ignores_argument(): void
    {
        $this->assertSame('test', $this->filter->apply('test', 'ignored', []));
    }
}
