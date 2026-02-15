<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Aicl\Notifications\Templates\Filters\LowerFilter;
use PHPUnit\Framework\TestCase;

class LowerFilterTest extends TestCase
{
    private LowerFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new LowerFilter;
    }

    public function test_implements_template_filter(): void
    {
        $this->assertInstanceOf(TemplateFilter::class, $this->filter);
    }

    public function test_converts_to_lowercase(): void
    {
        $this->assertSame('hello world', $this->filter->apply('HELLO WORLD', null, []));
    }

    public function test_handles_empty_string(): void
    {
        $this->assertSame('', $this->filter->apply('', null, []));
    }

    public function test_handles_multibyte_characters(): void
    {
        $result = $this->filter->apply('ÜBER', null, []);
        $this->assertSame('über', $result);
    }

    public function test_already_lowercase_stays_same(): void
    {
        $this->assertSame('abc', $this->filter->apply('abc', null, []));
    }

    public function test_ignores_argument(): void
    {
        $this->assertSame('test', $this->filter->apply('TEST', 'ignored', []));
    }
}
