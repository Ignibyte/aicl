<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\SemanticCheck;
use Aicl\Rlm\SemanticResult;
use PHPUnit\Framework\TestCase;

class SemanticCheckTest extends TestCase
{
    public function test_check_has_required_properties(): void
    {
        $check = new SemanticCheck(
            name: 'semantic.factory_types',
            description: 'Verify factory generates correct types',
            targets: ['migration', 'factory'],
            prompt: 'Check factory type consistency',
            severity: 'error',
            weight: 2.0,
        );

        $this->assertSame('semantic.factory_types', $check->name);
        $this->assertSame('Verify factory generates correct types', $check->description);
        $this->assertSame(['migration', 'factory'], $check->targets);
        $this->assertSame('Check factory type consistency', $check->prompt);
        $this->assertSame('error', $check->severity);
        $this->assertSame(2.0, $check->weight);
        $this->assertNull($check->appliesWhen);
    }

    public function test_is_error(): void
    {
        $check = new SemanticCheck(
            name: 'test',
            description: 'test',
            targets: [],
            prompt: 'test',
            severity: 'error',
        );

        $this->assertTrue($check->isError());
        $this->assertFalse($check->isWarning());
    }

    public function test_is_warning(): void
    {
        $check = new SemanticCheck(
            name: 'test',
            description: 'test',
            targets: [],
            prompt: 'test',
            severity: 'warning',
        );

        $this->assertFalse($check->isError());
        $this->assertTrue($check->isWarning());
    }

    public function test_always_applicable_when_no_condition(): void
    {
        $check = new SemanticCheck(
            name: 'test',
            description: 'test',
            targets: [],
            prompt: 'test',
        );

        $this->assertTrue($check->isApplicable());
        $this->assertTrue($check->isApplicable(['has_states' => false]));
        $this->assertTrue($check->isApplicable(['has_widgets' => true]));
    }

    public function test_applicable_with_condition_met(): void
    {
        $check = new SemanticCheck(
            name: 'test',
            description: 'test',
            targets: [],
            prompt: 'test',
            appliesWhen: 'has_states',
        );

        $this->assertTrue($check->isApplicable(['has_states' => true]));
    }

    public function test_not_applicable_with_condition_unmet(): void
    {
        $check = new SemanticCheck(
            name: 'test',
            description: 'test',
            targets: [],
            prompt: 'test',
            appliesWhen: 'has_states',
        );

        $this->assertFalse($check->isApplicable());
        $this->assertFalse($check->isApplicable(['has_states' => false]));
        $this->assertFalse($check->isApplicable(['has_widgets' => true]));
    }

    public function test_default_severity_is_warning(): void
    {
        $check = new SemanticCheck(
            name: 'test',
            description: 'test',
            targets: [],
            prompt: 'test',
        );

        $this->assertSame('warning', $check->severity);
    }

    public function test_default_weight_is_1_5(): void
    {
        $check = new SemanticCheck(
            name: 'test',
            description: 'test',
            targets: [],
            prompt: 'test',
        );

        $this->assertSame(1.5, $check->weight);
    }

    public function test_result_has_required_properties(): void
    {
        $check = new SemanticCheck(
            name: 'test',
            description: 'test',
            targets: [],
            prompt: 'test',
        );

        $result = new SemanticResult(
            check: $check,
            passed: true,
            message: 'All types match',
            confidence: 0.95,
            files: ['migration.php', 'factory.php'],
        );

        $this->assertSame($check, $result->check);
        $this->assertTrue($result->passed);
        $this->assertSame('All types match', $result->message);
        $this->assertSame(0.95, $result->confidence);
        $this->assertSame(['migration.php', 'factory.php'], $result->files);
        $this->assertFalse($result->skipped);
    }

    public function test_result_skipped_state(): void
    {
        $check = new SemanticCheck(
            name: 'test',
            description: 'test',
            targets: [],
            prompt: 'test',
        );

        $result = new SemanticResult(
            check: $check,
            passed: false,
            message: 'API unavailable',
            skipped: true,
        );

        $this->assertFalse($result->passed);
        $this->assertTrue($result->skipped);
    }

    public function test_result_defaults(): void
    {
        $check = new SemanticCheck(
            name: 'test',
            description: 'test',
            targets: [],
            prompt: 'test',
        );

        $result = new SemanticResult(
            check: $check,
            passed: true,
        );

        $this->assertSame('', $result->message);
        $this->assertSame(1.0, $result->confidence);
        $this->assertSame([], $result->files);
        $this->assertFalse($result->skipped);
    }
}
