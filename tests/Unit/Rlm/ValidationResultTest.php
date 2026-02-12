<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\EntityPattern;
use Aicl\Rlm\ValidationResult;
use PHPUnit\Framework\TestCase;

class ValidationResultTest extends TestCase
{
    public function test_can_be_constructed_with_passing_result(): void
    {
        $pattern = new EntityPattern(
            name: 'model.extends',
            description: 'Model must extend Model',
            target: 'model',
            check: 'extends Model',
        );

        $result = new ValidationResult(
            pattern: $pattern,
            passed: true,
            message: 'Pattern matched',
            file: '/tmp/test.php',
        );

        $this->assertSame($pattern, $result->pattern);
        $this->assertTrue($result->passed);
        $this->assertEquals('Pattern matched', $result->message);
        $this->assertEquals('/tmp/test.php', $result->file);
    }

    public function test_can_be_constructed_with_failing_result(): void
    {
        $pattern = new EntityPattern(
            name: 'model.extends',
            description: 'Model must extend Model',
            target: 'model',
            check: 'extends Model',
        );

        $result = new ValidationResult(
            pattern: $pattern,
            passed: false,
            message: 'Missing: Model must extend Model',
            file: '/tmp/test.php',
        );

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('Missing', $result->message);
    }

    public function test_default_message_is_empty_string(): void
    {
        $pattern = new EntityPattern(
            name: 'test',
            description: 'test',
            target: 'model',
            check: 'test',
        );

        $result = new ValidationResult(
            pattern: $pattern,
            passed: true,
        );

        $this->assertEquals('', $result->message);
    }

    public function test_default_file_is_null(): void
    {
        $pattern = new EntityPattern(
            name: 'test',
            description: 'test',
            target: 'model',
            check: 'test',
        );

        $result = new ValidationResult(
            pattern: $pattern,
            passed: true,
        );

        $this->assertNull($result->file);
    }
}
