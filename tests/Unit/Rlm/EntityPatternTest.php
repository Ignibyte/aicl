<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\EntityPattern;
use PHPUnit\Framework\TestCase;

class EntityPatternTest extends TestCase
{
    public function test_can_be_constructed_with_all_properties(): void
    {
        $pattern = new EntityPattern(
            name: 'model.namespace',
            description: 'Model must be in Aicl\\Models namespace',
            target: 'model',
            check: 'namespace Aicl\\\\Models;',
            severity: 'error',
            weight: 2.0,
        );

        $this->assertEquals('model.namespace', $pattern->name);
        $this->assertEquals('Model must be in Aicl\\Models namespace', $pattern->description);
        $this->assertEquals('model', $pattern->target);
        $this->assertEquals('namespace Aicl\\\\Models;', $pattern->check);
        $this->assertEquals('error', $pattern->severity);
        $this->assertEquals(2.0, $pattern->weight);
    }

    public function test_default_severity_is_error(): void
    {
        $pattern = new EntityPattern(
            name: 'test',
            description: 'test desc',
            target: 'model',
            check: 'something',
        );

        $this->assertEquals('error', $pattern->severity);
    }

    public function test_default_weight_is_one(): void
    {
        $pattern = new EntityPattern(
            name: 'test',
            description: 'test desc',
            target: 'model',
            check: 'something',
        );

        $this->assertEquals(1.0, $pattern->weight);
    }

    public function test_is_error_returns_true_for_error_severity(): void
    {
        $pattern = new EntityPattern(
            name: 'test',
            description: 'test desc',
            target: 'model',
            check: 'something',
            severity: 'error',
        );

        $this->assertTrue($pattern->isError());
        $this->assertFalse($pattern->isWarning());
    }

    public function test_is_warning_returns_true_for_warning_severity(): void
    {
        $pattern = new EntityPattern(
            name: 'test',
            description: 'test desc',
            target: 'model',
            check: 'something',
            severity: 'warning',
        );

        $this->assertTrue($pattern->isWarning());
        $this->assertFalse($pattern->isError());
    }

    public function test_is_error_returns_false_for_unknown_severity(): void
    {
        $pattern = new EntityPattern(
            name: 'test',
            description: 'test desc',
            target: 'model',
            check: 'something',
            severity: 'info',
        );

        $this->assertFalse($pattern->isError());
        $this->assertFalse($pattern->isWarning());
    }
}
