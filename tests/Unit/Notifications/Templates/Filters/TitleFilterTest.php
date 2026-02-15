<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Aicl\Notifications\Templates\Filters\TitleFilter;
use PHPUnit\Framework\TestCase;

class TitleFilterTest extends TestCase
{
    private TitleFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new TitleFilter;
    }

    public function test_implements_template_filter(): void
    {
        $this->assertInstanceOf(TemplateFilter::class, $this->filter);
    }

    public function test_converts_to_title_case(): void
    {
        $this->assertSame('Hello World', $this->filter->apply('hello world', null, []));
    }

    public function test_handles_empty_string(): void
    {
        $this->assertSame('', $this->filter->apply('', null, []));
    }

    public function test_handles_all_uppercase(): void
    {
        $this->assertSame('Hello World', $this->filter->apply('HELLO WORLD', null, []));
    }

    public function test_handles_single_word(): void
    {
        $this->assertSame('Hello', $this->filter->apply('hello', null, []));
    }

    public function test_ignores_argument(): void
    {
        $this->assertSame('Test', $this->filter->apply('test', 'ignored', []));
    }
}
