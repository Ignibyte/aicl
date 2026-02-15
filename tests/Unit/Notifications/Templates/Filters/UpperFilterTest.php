<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Aicl\Notifications\Templates\Filters\UpperFilter;
use PHPUnit\Framework\TestCase;

class UpperFilterTest extends TestCase
{
    private UpperFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new UpperFilter;
    }

    public function test_implements_template_filter(): void
    {
        $this->assertInstanceOf(TemplateFilter::class, $this->filter);
    }

    public function test_converts_to_uppercase(): void
    {
        $this->assertSame('HELLO WORLD', $this->filter->apply('hello world', null, []));
    }

    public function test_handles_empty_string(): void
    {
        $this->assertSame('', $this->filter->apply('', null, []));
    }

    public function test_handles_multibyte_characters(): void
    {
        $this->assertSame('STRASSE', $this->filter->apply('straße', null, []));
    }

    public function test_already_uppercase_stays_same(): void
    {
        $this->assertSame('ABC', $this->filter->apply('ABC', null, []));
    }

    public function test_ignores_argument(): void
    {
        $this->assertSame('TEST', $this->filter->apply('test', 'ignored', []));
    }
}
