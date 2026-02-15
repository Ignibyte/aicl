<?php

namespace Aicl\Tests\Unit\Notifications\Templates;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Aicl\Notifications\Templates\FilterRegistry;
use Aicl\Notifications\Templates\Filters\UpperFilter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FilterRegistryTest extends TestCase
{
    private FilterRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new FilterRegistry;
    }

    public function test_register_and_resolve_filter(): void
    {
        $filter = new UpperFilter;
        $this->registry->register('upper', $filter);

        $this->assertSame($filter, $this->registry->resolve('upper'));
    }

    public function test_has_returns_true_for_registered_filter(): void
    {
        $this->registry->register('upper', new UpperFilter);

        $this->assertTrue($this->registry->has('upper'));
    }

    public function test_has_returns_false_for_unregistered_filter(): void
    {
        $this->assertFalse($this->registry->has('nonexistent'));
    }

    public function test_all_returns_all_registered_filters(): void
    {
        $upper = new UpperFilter;
        $lower = new class implements TemplateFilter
        {
            public function apply(string $value, ?string $argument, array $context): string
            {
                return mb_strtolower($value);
            }
        };

        $this->registry->register('upper', $upper);
        $this->registry->register('lower', $lower);

        $all = $this->registry->all();

        $this->assertCount(2, $all);
        $this->assertSame($upper, $all['upper']);
        $this->assertSame($lower, $all['lower']);
    }

    public function test_all_returns_empty_when_no_filters(): void
    {
        $this->assertSame([], $this->registry->all());
    }

    public function test_resolve_throws_on_unknown_filter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Template filter [unknown] is not registered.');

        $this->registry->resolve('unknown');
    }

    public function test_register_overwrites_existing_filter(): void
    {
        $first = new UpperFilter;
        $second = new UpperFilter;

        $this->registry->register('upper', $first);
        $this->registry->register('upper', $second);

        $this->assertSame($second, $this->registry->resolve('upper'));
    }
}
