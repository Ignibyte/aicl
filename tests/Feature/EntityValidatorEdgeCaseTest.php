<?php

namespace Aicl\Tests\Feature;

use Aicl\Rlm\EntityPattern;
use Aicl\Rlm\EntityValidator;
use Aicl\Rlm\PatternRegistry;
use Aicl\Rlm\ValidationResult;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EntityValidatorEdgeCaseTest extends TestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = storage_path('app/test-validator');
        File::ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    protected function createTempFile(string $name, string $content): string
    {
        $path = $this->tempDir.'/'.$name;
        File::put($path, $content);

        return $path;
    }

    public function test_validate_returns_empty_for_no_matching_targets(): void
    {
        $validator = new EntityValidator('TestEntity');
        // Don't add any files — no targets will match
        $results = $validator->validate();

        // Results will be empty because no files match any pattern targets
        $this->assertIsArray($results);
    }

    public function test_score_returns_100_when_no_results(): void
    {
        $validator = new EntityValidator('TestEntity');
        // No files added, so no patterns match
        $score = $validator->score();

        $this->assertEquals(100.0, $score);
    }

    public function test_validate_file_not_found(): void
    {
        $validator = new EntityValidator('TestEntity');
        $validator->addFile('model', '/nonexistent/path/Model.php');

        $results = $validator->validate();
        $failures = $validator->failures();

        $this->assertNotEmpty($failures);
        foreach ($failures as $failure) {
            if ($failure->file === '/nonexistent/path/Model.php') {
                $this->assertStringContainsString('File not found', $failure->message);
            }
        }
    }

    public function test_validate_with_matching_content(): void
    {
        $content = "<?php\nnamespace Aicl\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass TestEntity extends Model\n{\n}";
        $path = $this->createTempFile('TestEntity.php', $content);

        $validator = new EntityValidator('TestEntity');
        $validator->addFile('model', $path);

        $results = $validator->validate();

        // Should have some passing results for model patterns
        $passed = array_filter($results, fn (ValidationResult $r) => $r->passed);
        $this->assertNotEmpty($passed);
    }

    public function test_validate_with_failing_content(): void
    {
        $content = "<?php\nnamespace App\\Models;\n\nclass WrongNamespace\n{\n}";
        $path = $this->createTempFile('Wrong.php', $content);

        $validator = new EntityValidator('TestEntity');
        $validator->addFile('model', $path);

        $results = $validator->validate();
        $failures = $validator->failures();

        $this->assertNotEmpty($failures);
    }

    public function test_score_calculation_with_mixed_results(): void
    {
        $content = "<?php\nnamespace Aicl\\Models;\n\nclass TestEntity\n{\n}";
        $path = $this->createTempFile('Mixed.php', $content);

        $validator = new EntityValidator('TestEntity');
        $validator->addFile('model', $path);
        $validator->validate();

        $score = $validator->score();

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(100.0, $score);
    }

    public function test_has_errors_with_error_severity(): void
    {
        $content = "<?php\nnamespace Wrong;\nclass Wrong {}";
        $path = $this->createTempFile('Error.php', $content);

        $validator = new EntityValidator('TestEntity');
        $validator->addFile('model', $path);
        $validator->validate();

        // Model patterns have error severity, so missing namespace should be an error
        $this->assertTrue($validator->hasErrors());
    }

    public function test_errors_returns_only_error_severity(): void
    {
        $content = "<?php\nnamespace Wrong;\nclass Wrong {}";
        $path = $this->createTempFile('ErrorOnly.php', $content);

        $validator = new EntityValidator('TestEntity');
        $validator->addFile('model', $path);
        $validator->validate();

        $errors = $validator->errors();
        foreach ($errors as $error) {
            $this->assertTrue($error->pattern->isError());
        }
    }

    public function test_warnings_returns_only_warning_severity(): void
    {
        $content = "<?php\nnamespace Wrong;\nclass Wrong {}";
        $path = $this->createTempFile('WarnOnly.php', $content);

        $validator = new EntityValidator('TestEntity');
        $validator->addFile('model', $path);
        $validator->validate();

        $warnings = $validator->warnings();
        foreach ($warnings as $warning) {
            $this->assertTrue($warning->pattern->isWarning());
        }
    }

    public function test_results_returns_all_results(): void
    {
        $content = "<?php\nnamespace Aicl\\Models;\nuse Illuminate\\Database\\Eloquent\\Model;\nclass TestEntity extends Model {}";
        $path = $this->createTempFile('All.php', $content);

        $validator = new EntityValidator('TestEntity');
        $validator->addFile('model', $path);
        $validator->validate();

        $results = $validator->results();
        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertInstanceOf(ValidationResult::class, $result);
        }
    }

    public function test_add_file_is_fluent(): void
    {
        $validator = new EntityValidator('TestEntity');
        $result = $validator->addFile('model', '/some/path.php');

        $this->assertSame($validator, $result);
    }

    public function test_validate_resets_results_on_each_call(): void
    {
        $content = "<?php\nnamespace Aicl\\Models;\nclass TestEntity {}";
        $path = $this->createTempFile('Reset.php', $content);

        $validator = new EntityValidator('TestEntity');
        $validator->addFile('model', $path);

        $results1 = $validator->validate();
        $results2 = $validator->validate();

        $this->assertCount(count($results1), $results2);
    }

    public function test_score_auto_validates_if_not_yet_run(): void
    {
        $content = "<?php\nnamespace Aicl\\Models;\nuse Illuminate\\Database\\Eloquent\\Model;\nclass TestEntity extends Model {}";
        $path = $this->createTempFile('Auto.php', $content);

        $validator = new EntityValidator('TestEntity');
        $validator->addFile('model', $path);

        // Don't call validate() — score() should auto-validate
        $score = $validator->score();

        $this->assertIsFloat($score);
        $this->assertGreaterThan(0.0, $score);
    }

    public function test_multiple_file_targets(): void
    {
        $modelContent = "<?php\nnamespace Aicl\\Models;\nuse Illuminate\\Database\\Eloquent\\Model;\nclass Multi extends Model {}";
        $migrationContent = "<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nreturn new class extends Migration {}";

        $modelPath = $this->createTempFile('Multi.php', $modelContent);
        $migrationPath = $this->createTempFile('MultiMigration.php', $migrationContent);

        $validator = new EntityValidator('Multi');
        $validator->addFile('model', $modelPath);
        $validator->addFile('migration', $migrationPath);

        $results = $validator->validate();

        // Should have results from both model and migration patterns
        $files = array_unique(array_map(fn (ValidationResult $r) => $r->file, $results));
        $this->assertCount(2, $files);
    }

    public function test_entity_pattern_is_error(): void
    {
        $pattern = new EntityPattern(
            name: 'test.error',
            description: 'Error pattern',
            target: 'model',
            check: 'test',
            severity: 'error',
        );

        $this->assertTrue($pattern->isError());
        $this->assertFalse($pattern->isWarning());
    }

    public function test_entity_pattern_is_warning(): void
    {
        $pattern = new EntityPattern(
            name: 'test.warning',
            description: 'Warning pattern',
            target: 'model',
            check: 'test',
            severity: 'warning',
        );

        $this->assertFalse($pattern->isError());
        $this->assertTrue($pattern->isWarning());
    }

    public function test_entity_pattern_default_weight(): void
    {
        $pattern = new EntityPattern(
            name: 'test',
            description: 'Test',
            target: 'model',
            check: 'test',
        );

        $this->assertEquals(1.0, $pattern->weight);
        $this->assertEquals('error', $pattern->severity);
    }

    public function test_pattern_registry_returns_non_empty_patterns(): void
    {
        $patterns = PatternRegistry::all();

        $this->assertNotEmpty($patterns);
        foreach ($patterns as $pattern) {
            $this->assertInstanceOf(EntityPattern::class, $pattern);
        }
    }

    public function test_pattern_registry_covers_expected_targets(): void
    {
        $patterns = PatternRegistry::all();
        $targets = array_unique(array_map(fn (EntityPattern $p) => $p->target, $patterns));

        $this->assertContains('model', $targets);
        $this->assertContains('migration', $targets);
        $this->assertContains('factory', $targets);
        $this->assertContains('policy', $targets);
    }

    public function test_validation_result_properties(): void
    {
        $pattern = new EntityPattern(
            name: 'test',
            description: 'Test',
            target: 'model',
            check: 'test',
        );

        $result = new ValidationResult(
            pattern: $pattern,
            passed: true,
            message: 'OK',
            file: '/path/to/file.php',
        );

        $this->assertSame($pattern, $result->pattern);
        $this->assertTrue($result->passed);
        $this->assertEquals('OK', $result->message);
        $this->assertEquals('/path/to/file.php', $result->file);
    }
}
