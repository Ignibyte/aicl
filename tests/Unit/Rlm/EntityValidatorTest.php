<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\EntityValidator;
use Aicl\Rlm\ValidationResult;
use PHPUnit\Framework\TestCase;

class EntityValidatorTest extends TestCase
{
    protected string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = sys_get_temp_dir().'/aicl_validator_test_'.uniqid();
        mkdir($this->fixtureDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up fixture files
        if (is_dir($this->fixtureDir)) {
            array_map('unlink', glob("{$this->fixtureDir}/*"));
            rmdir($this->fixtureDir);
        }

        parent::tearDown();
    }

    protected function createFixture(string $filename, string $content): string
    {
        $path = "{$this->fixtureDir}/{$filename}";
        file_put_contents($path, $content);

        return $path;
    }

    public function test_add_file_returns_self_for_chaining(): void
    {
        $validator = new EntityValidator('TestEntity');

        $result = $validator->addFile('model', '/tmp/fake.php');

        $this->assertSame($validator, $result);
    }

    public function test_validate_returns_array_of_validation_results(): void
    {
        $modelFile = $this->createFixture('TestEntity.php', <<<'PHP'
        <?php
        namespace Aicl\Models;
        use Illuminate\Database\Eloquent\Model;
        use Illuminate\Database\Eloquent\Factories\HasFactory;
        use Illuminate\Database\Eloquent\SoftDeletes;
        use Aicl\Traits\HasEntityEvents;
        use Aicl\Traits\HasAuditTrail;
        use Aicl\Traits\HasStandardScopes;
        class TestEntity extends Model {
            use HasFactory;
            use HasEntityEvents;
            use HasAuditTrail;
            use HasStandardScopes;
            use SoftDeletes;
            protected $fillable = ['name'];
            protected function casts(): array { return []; }
            protected static function newFactory() {}
            public function owner(): BelongsTo { }
        }
        PHP);

        $validator = new EntityValidator('TestEntity');
        $validator->addFile('model', $modelFile);
        $results = $validator->validate();

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);

        foreach ($results as $result) {
            $this->assertInstanceOf(ValidationResult::class, $result);
        }
    }

    public function test_validate_skips_patterns_without_matching_file(): void
    {
        $validator = new EntityValidator('TestEntity');
        // Don't add any files — all patterns should be skipped
        $results = $validator->validate();

        $this->assertEmpty($results);
    }

    public function test_validate_reports_file_not_found(): void
    {
        $validator = new EntityValidator('TestEntity');
        $validator->addFile('model', '/nonexistent/path/model.php');
        $results = $validator->validate();

        $this->assertNotEmpty($results);

        $fileNotFoundResults = array_filter(
            $results,
            fn (ValidationResult $r) => str_contains($r->message, 'File not found'),
        );
        $this->assertNotEmpty($fileNotFoundResults);
    }

    public function test_score_returns_100_when_no_results(): void
    {
        $validator = new EntityValidator('TestEntity');
        // No files means no results
        $score = $validator->score();

        $this->assertEquals(100.0, $score);
    }

    public function test_score_returns_percentage_based_on_weight(): void
    {
        $modelFile = $this->createFixture('Model.php', <<<'PHP'
        <?php
        namespace Aicl\Models;
        class Something extends Model {
            use HasFactory;
            protected $fillable = [];
            protected static function newFactory() {}
        }
        PHP);

        $validator = new EntityValidator('Something');
        $validator->addFile('model', $modelFile);
        $score = $validator->score();

        // Some patterns should pass, some should fail
        $this->assertGreaterThan(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_has_errors_returns_false_when_all_pass(): void
    {
        $validator = new EntityValidator('TestEntity');
        // No files = no results = no errors
        $validator->validate();

        $this->assertFalse($validator->hasErrors());
    }

    public function test_has_errors_returns_true_when_error_severity_fails(): void
    {
        $modelFile = $this->createFixture('Bad.php', '<?php // empty model');

        $validator = new EntityValidator('Bad');
        $validator->addFile('model', $modelFile);
        $validator->validate();

        $this->assertTrue($validator->hasErrors());
    }

    public function test_failures_returns_only_failed_results(): void
    {
        $modelFile = $this->createFixture('Partial.php', <<<'PHP'
        <?php
        namespace Aicl\Models;
        class Partial extends Model {
            use HasFactory;
        }
        PHP);

        $validator = new EntityValidator('Partial');
        $validator->addFile('model', $modelFile);
        $validator->validate();

        $failures = $validator->failures();

        foreach ($failures as $failure) {
            $this->assertFalse($failure->passed);
        }
    }

    public function test_errors_returns_only_error_severity_failures(): void
    {
        $modelFile = $this->createFixture('Errors.php', '<?php // empty');

        $validator = new EntityValidator('Errors');
        $validator->addFile('model', $modelFile);
        $validator->validate();

        $errors = $validator->errors();

        foreach ($errors as $error) {
            $this->assertFalse($error->passed);
            $this->assertTrue($error->pattern->isError());
        }
    }

    public function test_warnings_returns_only_warning_severity_failures(): void
    {
        $modelFile = $this->createFixture('Warnings.php', <<<'PHP'
        <?php
        namespace Aicl\Models;
        class Warnings extends Model {
            use HasFactory;
            protected $fillable = ['name'];
            protected static function newFactory() {}
        }
        PHP);

        $validator = new EntityValidator('Warnings');
        $validator->addFile('model', $modelFile);
        $validator->validate();

        $warnings = $validator->warnings();

        foreach ($warnings as $warning) {
            $this->assertFalse($warning->passed);
            $this->assertTrue($warning->pattern->isWarning());
        }
    }

    public function test_results_returns_all_validation_results(): void
    {
        $modelFile = $this->createFixture('All.php', '<?php class All {}');

        $validator = new EntityValidator('All');
        $validator->addFile('model', $modelFile);
        $validator->validate();

        $results = $validator->results();
        $this->assertNotEmpty($results);
        $this->assertCount(count($results), $validator->results());
    }

    public function test_validate_can_check_multiple_file_targets(): void
    {
        $modelFile = $this->createFixture('Multi.php', <<<'PHP'
        <?php
        namespace Aicl\Models;
        class Multi extends Model {
            use HasFactory;
            protected $fillable = [];
            protected static function newFactory() {}
        }
        PHP);

        $migrationFile = $this->createFixture('migration.php', <<<'PHP'
        <?php
        return new class extends Migration {
            public function up(): void {
                $table->id();
                $table->timestamps();
                $table->softDeletes();
            }
            public function down(): void {}
        };
        PHP);

        $validator = new EntityValidator('Multi');
        $validator->addFile('model', $modelFile);
        $validator->addFile('migration', $migrationFile);
        $results = $validator->validate();

        $targets = array_unique(array_map(fn (ValidationResult $r) => $r->pattern->target, $results));
        $this->assertContains('model', $targets);
        $this->assertContains('migration', $targets);
    }

    public function test_score_auto_validates_if_not_already_done(): void
    {
        $modelFile = $this->createFixture('Auto.php', '<?php class Auto {}');

        $validator = new EntityValidator('Auto');
        $validator->addFile('model', $modelFile);

        // Don't call validate() — score() should call it automatically
        $score = $validator->score();

        $this->assertIsFloat($score);
        $this->assertNotEmpty($validator->results());
    }
}
