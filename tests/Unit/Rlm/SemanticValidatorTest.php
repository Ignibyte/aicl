<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\SemanticCheck;
use Aicl\Rlm\SemanticResult;
use Aicl\Rlm\SemanticValidator;
use PHPUnit\Framework\TestCase;

class SemanticValidatorTest extends TestCase
{
    private function makeCheck(string $name = 'semantic.test', string $severity = 'warning', float $weight = 1.5): SemanticCheck
    {
        return new SemanticCheck(
            name: $name,
            description: 'Test check',
            targets: ['migration', 'factory'],
            prompt: 'Check something',
            severity: $severity,
            weight: $weight,
        );
    }

    public function test_build_prompt_includes_system_instruction(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $check = $this->makeCheck();
        $contents = ['migration' => '<?php // migration', 'factory' => '<?php // factory'];

        $prompt = $validator->buildPrompt($check, $contents);

        $this->assertStringContainsString('code review assistant', $prompt);
        $this->assertStringContainsString('JSON format', $prompt);
    }

    public function test_build_prompt_includes_file_contents(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $check = $this->makeCheck();
        $contents = [
            'migration' => '<?php return new class extends Migration {}',
            'factory' => '<?php class TestFactory extends Factory {}',
        ];

        $prompt = $validator->buildPrompt($check, $contents);

        $this->assertStringContainsString('### File: migration', $prompt);
        $this->assertStringContainsString('### File: factory', $prompt);
        $this->assertStringContainsString('class TestFactory', $prompt);
        $this->assertStringContainsString('extends Migration', $prompt);
    }

    public function test_build_prompt_includes_check_prompt(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $check = $this->makeCheck();

        $prompt = $validator->buildPrompt($check, ['migration' => 'x']);

        $this->assertStringContainsString('Check something', $prompt);
        $this->assertStringContainsString('### Question', $prompt);
    }

    public function test_build_prompt_includes_check_description(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $check = $this->makeCheck();

        $prompt = $validator->buildPrompt($check, ['migration' => 'x']);

        $this->assertStringContainsString('## Check: Test check', $prompt);
    }

    public function test_parse_response_valid_pass(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $check = $this->makeCheck();

        $json = [
            'content' => [
                ['type' => 'text', 'text' => '{"passed": true, "message": "All good", "confidence": 0.95}'],
            ],
        ];

        $result = $validator->parseResponse($json, $check);

        $this->assertTrue($result->passed);
        $this->assertSame('All good', $result->message);
        $this->assertSame(0.95, $result->confidence);
        $this->assertFalse($result->skipped);
    }

    public function test_parse_response_valid_fail(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $check = $this->makeCheck();

        $json = [
            'content' => [
                ['type' => 'text', 'text' => '{"passed": false, "message": "Type mismatch on column X", "confidence": 0.88}'],
            ],
        ];

        $result = $validator->parseResponse($json, $check);

        $this->assertFalse($result->passed);
        $this->assertSame('Type mismatch on column X', $result->message);
        $this->assertSame(0.88, $result->confidence);
        $this->assertFalse($result->skipped);
    }

    public function test_parse_response_null_json(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $check = $this->makeCheck();

        $result = $validator->parseResponse(null, $check);

        $this->assertFalse($result->passed);
        $this->assertTrue($result->skipped);
        $this->assertStringContainsString('null', $result->message);
    }

    public function test_parse_response_empty_content(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $check = $this->makeCheck();

        $result = $validator->parseResponse(['content' => []], $check);

        $this->assertFalse($result->passed);
        $this->assertTrue($result->skipped);
    }

    public function test_parse_response_invalid_json_text(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $check = $this->makeCheck();

        $json = [
            'content' => [
                ['type' => 'text', 'text' => 'This is not JSON at all, just plain text.'],
            ],
        ];

        $result = $validator->parseResponse($json, $check);

        $this->assertFalse($result->passed);
        $this->assertTrue($result->skipped);
        $this->assertStringContainsString('Could not parse JSON', $result->message);
    }

    public function test_parse_response_json_in_markdown_fence(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $check = $this->makeCheck();

        $json = [
            'content' => [
                ['type' => 'text', 'text' => "```json\n{\"passed\": true, \"message\": \"OK\", \"confidence\": 0.9}\n```"],
            ],
        ];

        $result = $validator->parseResponse($json, $check);

        $this->assertTrue($result->passed);
        $this->assertSame('OK', $result->message);
    }

    public function test_parse_response_json_with_surrounding_text(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $check = $this->makeCheck();

        $json = [
            'content' => [
                ['type' => 'text', 'text' => 'Here is the result: {"passed": false, "message": "Issue found", "confidence": 0.85} and some more text.'],
            ],
        ];

        $result = $validator->parseResponse($json, $check);

        $this->assertFalse($result->passed);
        $this->assertSame('Issue found', $result->message);
    }

    public function test_parse_response_low_confidence_is_skipped(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $check = $this->makeCheck();

        $json = [
            'content' => [
                ['type' => 'text', 'text' => '{"passed": false, "message": "Maybe wrong", "confidence": 0.1}'],
            ],
        ];

        $result = $validator->parseResponse($json, $check, [], 0.3);

        $this->assertFalse($result->passed);
        $this->assertTrue($result->skipped);
        $this->assertStringContainsString('Low confidence', $result->message);
    }

    public function test_parse_response_boundary_confidence_not_skipped(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $check = $this->makeCheck();

        $json = [
            'content' => [
                ['type' => 'text', 'text' => '{"passed": true, "message": "Looks good", "confidence": 0.3}'],
            ],
        ];

        $result = $validator->parseResponse($json, $check, [], 0.3);

        $this->assertTrue($result->passed);
        $this->assertFalse($result->skipped);
    }

    public function test_parse_response_preserves_files(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $check = $this->makeCheck();

        $json = [
            'content' => [
                ['type' => 'text', 'text' => '{"passed": true, "message": "OK", "confidence": 0.9}'],
            ],
        ];

        $result = $validator->parseResponse($json, $check, ['migration', 'factory']);

        $this->assertSame(['migration', 'factory'], $result->files);
    }

    public function test_score_with_no_results(): void
    {
        $validator = new SemanticValidator('TestEntity');

        $this->assertSame(100.0, $validator->score());
    }

    public function test_failures_excludes_passed_and_skipped(): void
    {
        $validator = new SemanticValidator('TestEntity');

        // Use reflection to set results directly
        $reflection = new \ReflectionClass($validator);
        $prop = $reflection->getProperty('results');
        $prop->setAccessible(true);

        $check1 = $this->makeCheck('semantic.a');
        $check2 = $this->makeCheck('semantic.b');
        $check3 = $this->makeCheck('semantic.c');

        $prop->setValue($validator, [
            new SemanticResult(check: $check1, passed: true, message: 'ok'),
            new SemanticResult(check: $check2, passed: false, message: 'fail'),
            new SemanticResult(check: $check3, passed: false, message: 'skip', skipped: true),
        ]);

        $failures = $validator->failures();
        $this->assertCount(1, $failures);
        $this->assertSame('semantic.b', $failures[0]->check->name);
    }

    public function test_errors_returns_only_error_severity(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $reflection = new \ReflectionClass($validator);
        $prop = $reflection->getProperty('results');
        $prop->setAccessible(true);

        $errorCheck = $this->makeCheck('semantic.a', 'error');
        $warningCheck = $this->makeCheck('semantic.b', 'warning');

        $prop->setValue($validator, [
            new SemanticResult(check: $errorCheck, passed: false, message: 'fail'),
            new SemanticResult(check: $warningCheck, passed: false, message: 'fail'),
        ]);

        $errors = $validator->errors();
        $this->assertCount(1, $errors);
        $this->assertSame('semantic.a', $errors[0]->check->name);
    }

    public function test_warnings_returns_only_warning_severity(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $reflection = new \ReflectionClass($validator);
        $prop = $reflection->getProperty('results');
        $prop->setAccessible(true);

        $errorCheck = $this->makeCheck('semantic.a', 'error');
        $warningCheck = $this->makeCheck('semantic.b', 'warning');

        $prop->setValue($validator, [
            new SemanticResult(check: $errorCheck, passed: false, message: 'fail'),
            new SemanticResult(check: $warningCheck, passed: false, message: 'fail'),
        ]);

        $warnings = $validator->warnings();
        $this->assertCount(1, $warnings);
        $this->assertSame('semantic.b', $warnings[0]->check->name);
    }

    public function test_skipped_returns_only_skipped(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $reflection = new \ReflectionClass($validator);
        $prop = $reflection->getProperty('results');
        $prop->setAccessible(true);

        $check1 = $this->makeCheck('semantic.a');
        $check2 = $this->makeCheck('semantic.b');

        $prop->setValue($validator, [
            new SemanticResult(check: $check1, passed: true, message: 'ok'),
            new SemanticResult(check: $check2, passed: false, message: 'skip', skipped: true),
        ]);

        $skipped = $validator->skipped();
        $this->assertCount(1, $skipped);
        $this->assertSame('semantic.b', $skipped[0]->check->name);
    }

    public function test_score_excludes_skipped_from_denominator(): void
    {
        $validator = new SemanticValidator('TestEntity');
        $reflection = new \ReflectionClass($validator);
        $prop = $reflection->getProperty('results');
        $prop->setAccessible(true);

        $check1 = $this->makeCheck('semantic.a', 'error', 2.0);
        $check2 = $this->makeCheck('semantic.b', 'warning', 1.0);
        $check3 = $this->makeCheck('semantic.c', 'error', 2.0);

        $prop->setValue($validator, [
            new SemanticResult(check: $check1, passed: true, message: 'ok'),       // 2.0 earned
            new SemanticResult(check: $check2, passed: false, message: 'fail'),    // 1.0 lost
            new SemanticResult(check: $check3, passed: false, message: 'skip', skipped: true), // excluded
        ]);

        // Score = 2.0 / 3.0 * 100 = 66.7
        $this->assertSame(66.7, $validator->score());
    }
}
