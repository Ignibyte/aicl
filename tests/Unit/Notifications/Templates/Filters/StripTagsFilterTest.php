<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Aicl\Notifications\Templates\Filters\StripTagsFilter;
use PHPUnit\Framework\TestCase;

class StripTagsFilterTest extends TestCase
{
    private StripTagsFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new StripTagsFilter;
    }

    public function test_implements_template_filter(): void
    {
        $this->assertInstanceOf(TemplateFilter::class, $this->filter);
    }

    public function test_strips_html_tags(): void
    {
        $result = $this->filter->apply('<b>bold</b> text', null, []);

        $this->assertSame('bold text', $result);
    }

    public function test_strips_script_tags(): void
    {
        $result = $this->filter->apply('<script>alert("xss")</script>safe', null, []);

        $this->assertSame('alert("xss")safe', $result);
    }

    public function test_strips_nested_tags(): void
    {
        $result = $this->filter->apply('<div><p>paragraph</p></div>', null, []);

        $this->assertSame('paragraph', $result);
    }

    public function test_handles_empty_string(): void
    {
        $this->assertSame('', $this->filter->apply('', null, []));
    }

    public function test_no_tags_passes_through(): void
    {
        $this->assertSame('plain text', $this->filter->apply('plain text', null, []));
    }

    public function test_ignores_argument(): void
    {
        $result = $this->filter->apply('<b>bold</b>', 'ignored', []);

        $this->assertSame('bold', $result);
    }
}
