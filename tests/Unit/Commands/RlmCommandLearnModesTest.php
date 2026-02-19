<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Models\RlmFailure;
use Aicl\Rlm\RuleNormalizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RlmCommandLearnModesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id' => 1]);
    }

    // ─── Mode 1: Free-text (backward compatibility) ────────────

    public function test_free_text_learn_creates_lesson(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            'query' => 'Dusk cookies cause timeout',
            '--topic' => 'dusk',
            '--tags' => 'dusk,timeout',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('recorded');

        $this->assertDatabaseHas('rlm_lessons', [
            'topic' => 'dusk',
            'summary' => 'Dusk cookies cause timeout',
        ]);
    }

    public function test_free_text_learn_requires_topic(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            'query' => 'Some lesson without topic',
        ])
            ->assertFailed();
    }

    public function test_free_text_learn_does_not_create_failure(): void
    {
        $countBefore = RlmFailure::query()->count();

        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            'query' => 'Free text lesson only',
            '--topic' => 'testing',
        ])
            ->assertSuccessful();

        $this->assertSame($countBefore, RlmFailure::query()->count());
    }

    // ─── Mode 2: Structured flags ──────────────────────────────

    public function test_structured_learn_creates_failure(): void
    {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('aicl:rlm', [
            'action' => 'learn',
            '--attempt' => 'Scaffolded entity with default searchableColumns.',
            '--feedback' => 'QueryException: Unknown column title.',
            '--fix' => 'Override searchableColumns() to list real columns.',
            '--rule' => 'Always override searchableColumns() for models without title.',
            '--validator-layer' => 'L1',
            '--validator-id' => 'P-012',
            '--entity' => 'Project',
            '--phase' => 'phase-4',
            '--file' => 'app/Models/Project.php',
        ]);

        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Failure', $output);
        $this->assertStringContainsString('recorded', $output);
        $this->assertStringContainsString('Rule hash:', $output);

        // Verify failure was created with structured fields
        $failure = RlmFailure::query()
            ->where('validator_id', 'P-012')
            ->where('entity_name', 'Project')
            ->first();

        $this->assertNotNull($failure);
        $this->assertSame('Scaffolded entity with default searchableColumns.', $failure->attempt);
        $this->assertSame('QueryException: Unknown column title.', $failure->feedback);
        $this->assertSame('Override searchableColumns() to list real columns.', $failure->fix);
        $this->assertSame('Always override searchableColumns() for models without title.', $failure->preventive_rule);
        $this->assertSame('L1', $failure->validator_layer);
        $this->assertSame('P-012', $failure->validator_id);
        $this->assertSame('Project', $failure->entity_name);
        $this->assertSame('phase-4', $failure->phase);
        $this->assertSame('app/Models/Project.php', $failure->file_path);
    }

    public function test_structured_learn_computes_rule_hash(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--rule' => 'Always override searchableColumns.',
            '--feedback' => 'Test feedback',
        ])
            ->assertSuccessful();

        $failure = RlmFailure::query()
            ->whereNotNull('rule_hash')
            ->latest()
            ->first();

        $this->assertNotNull($failure);
        $this->assertSame(
            RuleNormalizer::hash('Always override searchableColumns.'),
            $failure->rule_hash
        );
    }

    public function test_structured_learn_auto_generates_description(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--feedback' => 'PHPStan found type mismatch.',
            '--fix' => 'Added return type hint.',
            '--rule' => 'Always add return types.',
        ])
            ->assertSuccessful();

        $failure = RlmFailure::query()->latest()->first();

        $this->assertNotNull($failure);
        // Auto-generated description should contain feedback, rule, and fix
        $this->assertStringContainsString('PHPStan found type mismatch.', $failure->description);
        $this->assertStringContainsString('Always add return types.', $failure->description);
        $this->assertStringContainsString('Added return type hint.', $failure->description);
    }

    public function test_structured_learn_auto_generates_title_from_rule(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--rule' => 'Always use Schemas namespace for Section.',
            '--feedback' => 'Test feedback',
        ])
            ->assertSuccessful();

        $failure = RlmFailure::query()->latest()->first();

        $this->assertNotNull($failure);
        $this->assertStringContainsString('Always use Schemas namespace for Section.', $failure->title);
    }

    public function test_structured_learn_auto_generates_title_from_feedback_when_no_rule(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--feedback' => 'QueryException: Unknown column.',
            '--attempt' => 'Scaffolded entity.',
        ])
            ->assertSuccessful();

        $failure = RlmFailure::query()->latest()->first();

        $this->assertNotNull($failure);
        $this->assertStringContainsString('QueryException: Unknown column.', $failure->title);
    }

    public function test_structured_learn_generates_failure_code_from_validator_id(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--feedback' => 'Test failure',
            '--validator-id' => 'P-012',
        ])
            ->assertSuccessful();

        $failure = RlmFailure::query()->latest()->first();

        $this->assertNotNull($failure);
        $this->assertStringStartsWith('SF-', $failure->failure_code);
        $this->assertStringContainsString('P012', $failure->failure_code);
    }

    public function test_structured_learn_auto_increments_failure_code_without_validator_id(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--feedback' => 'First failure without validator',
        ])
            ->assertSuccessful();

        $failure = RlmFailure::query()->latest()->first();

        $this->assertNotNull($failure);
        $this->assertMatchesRegularExpression('/^SF-\d{3}$/', $failure->failure_code);
    }

    public function test_structured_learn_with_topic_creates_dual_records(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--feedback' => 'Test failure',
            '--rule' => 'Test rule',
            '--topic' => 'testing',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Failure')
            ->expectsOutputToContain('Lesson');

        // Both failure and lesson should exist
        $this->assertDatabaseHas('rlm_failures', [
            'preventive_rule' => 'Test rule',
        ]);
        $this->assertDatabaseHas('rlm_lessons', [
            'topic' => 'testing',
        ]);
    }

    public function test_structured_learn_infers_category_from_validator_layer(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--feedback' => 'L1 pattern failure',
            '--validator-layer' => 'L1',
        ])
            ->assertSuccessful();

        $failure = RlmFailure::query()->latest()->first();
        $this->assertSame('scaffolding', $failure->category->value);
    }

    // ─── Mode 3: JSON input ───────────────────────────────────

    public function test_json_learn_creates_failure_from_inline_json(): void
    {
        $json = json_encode([
            'attempt' => 'Generated default template.',
            'feedback' => 'Missing trait.',
            'fix' => 'Added HasAuditTrail trait.',
            'rule' => 'Always include HasAuditTrail.',
            'validator_layer' => 'L1',
            'validator_id' => 'P-005',
            'entity' => 'Invoice',
            'phase' => 'phase-3',
        ]);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('aicl:rlm', [
            'action' => 'learn',
            '--json' => $json,
        ]);

        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Failure', $output);
        $this->assertStringContainsString('recorded', $output);

        $failure = RlmFailure::query()
            ->where('entity_name', 'Invoice')
            ->first();

        $this->assertNotNull($failure);
        $this->assertSame('Generated default template.', $failure->attempt);
        $this->assertSame('Missing trait.', $failure->feedback);
        $this->assertSame('Added HasAuditTrail trait.', $failure->fix);
        $this->assertSame('Always include HasAuditTrail.', $failure->preventive_rule);
        $this->assertSame('L1', $failure->validator_layer);
        $this->assertSame('P-005', $failure->validator_id);
        $this->assertSame('phase-3', $failure->phase);
    }

    public function test_json_learn_accepts_preventive_rule_key(): void
    {
        $json = json_encode([
            'feedback' => 'Test feedback',
            'preventive_rule' => 'Use preventive_rule key instead of rule.',
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--json' => $json,
        ])
            ->assertSuccessful();

        $failure = RlmFailure::query()->latest()->first();
        $this->assertSame('Use preventive_rule key instead of rule.', $failure->preventive_rule);
    }

    public function test_json_learn_allows_description_override(): void
    {
        $json = json_encode([
            'feedback' => 'Test feedback',
            'description' => 'Custom description override.',
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--json' => $json,
        ])
            ->assertSuccessful();

        $failure = RlmFailure::query()->latest()->first();
        $this->assertSame('Custom description override.', $failure->description);
    }

    public function test_json_learn_with_array_tags(): void
    {
        $json = json_encode([
            'feedback' => 'Test failure',
            'tags' => ['filament', 'section', 'namespace'],
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--json' => $json,
            '--topic' => 'testing',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Tags:');
    }

    public function test_json_learn_fails_on_invalid_json(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--json' => 'not valid json {{{',
        ])
            ->assertFailed()
            ->expectsOutputToContain('Invalid JSON');
    }

    public function test_json_learn_from_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'rlm_test_');
        file_put_contents($tempFile, json_encode([
            'attempt' => 'File-based input test.',
            'feedback' => 'Loaded from file.',
            'entity' => 'FileTest',
        ]));

        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--json' => '@'.$tempFile,
        ])
            ->assertSuccessful();

        $failure = RlmFailure::query()
            ->where('entity_name', 'FileTest')
            ->first();

        $this->assertNotNull($failure);
        $this->assertSame('File-based input test.', $failure->attempt);

        unlink($tempFile);
    }

    public function test_json_learn_from_nonexistent_file_fails(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--json' => '@/nonexistent/path/file.json',
        ])
            ->assertFailed()
            ->expectsOutputToContain('Failed to read JSON input');
    }

    // ─── Rule hash stability across modes ──────────────────────

    public function test_rule_hash_is_identical_across_structured_and_json_modes(): void
    {
        $rule = 'Always override searchableColumns() to list real columns.';

        // Create via structured mode
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--rule' => $rule,
            '--feedback' => 'Structured mode test',
        ])
            ->assertSuccessful();

        $structuredFailure = RlmFailure::query()->latest()->first();

        // Create via JSON mode
        $json = json_encode([
            'rule' => $rule,
            'feedback' => 'JSON mode test',
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            '--json' => $json,
        ])
            ->assertSuccessful();

        $jsonFailure = RlmFailure::query()->latest()->first();

        $this->assertSame($structuredFailure->rule_hash, $jsonFailure->rule_hash);
    }
}
