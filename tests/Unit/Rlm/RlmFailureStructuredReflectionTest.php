<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Models\RlmFailure;
use Aicl\Rlm\RuleNormalizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RlmFailureStructuredReflectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id' => 1]);
    }

    public function test_model_mutator_computes_rule_norm_and_rule_hash(): void
    {
        $failure = RlmFailure::factory()->create([
            'preventive_rule' => 'Always override searchableColumns().',
            'owner_id' => $this->admin->id,
        ]);

        $this->assertNotNull($failure->rule_norm);
        $this->assertNotNull($failure->rule_hash);
        $this->assertSame(
            RuleNormalizer::normalize('Always override searchableColumns().'),
            $failure->rule_norm
        );
        $this->assertSame(
            RuleNormalizer::hash('Always override searchableColumns().'),
            $failure->rule_hash
        );
    }

    public function test_model_mutator_clears_hash_when_rule_is_null(): void
    {
        $failure = RlmFailure::factory()->create([
            'preventive_rule' => 'Some rule.',
            'owner_id' => $this->admin->id,
        ]);

        $this->assertNotNull($failure->rule_hash);

        $failure->preventive_rule = null;
        $failure->save();
        $failure->refresh();

        $this->assertNull($failure->rule_norm);
        $this->assertNull($failure->rule_hash);
    }

    public function test_model_mutator_clears_hash_when_rule_is_empty_string(): void
    {
        $failure = RlmFailure::factory()->create([
            'preventive_rule' => 'Some rule.',
            'owner_id' => $this->admin->id,
        ]);

        $failure->preventive_rule = '';
        $failure->save();
        $failure->refresh();

        $this->assertNull($failure->rule_norm);
        $this->assertNull($failure->rule_hash);
    }

    public function test_structured_factory_state_populates_reflection_fields(): void
    {
        $failure = RlmFailure::factory()->structured()->create([
            'owner_id' => $this->admin->id,
        ]);

        $this->assertNotNull($failure->attempt);
        $this->assertNotNull($failure->feedback);
        $this->assertNotNull($failure->fix);
        $this->assertNotNull($failure->preventive_rule);
        $this->assertNotNull($failure->validator_layer);
        $this->assertNotNull($failure->validator_id);
        $this->assertNotNull($failure->entity_name);
        $this->assertNotNull($failure->phase);
    }

    public function test_structured_factory_state_computes_rule_hash(): void
    {
        $failure = RlmFailure::factory()->structured()->create([
            'owner_id' => $this->admin->id,
        ]);

        $this->assertNotNull($failure->rule_hash);
        $this->assertSame(40, strlen($failure->rule_hash));
    }

    public function test_attempt_and_feedback_columns_are_fillable(): void
    {
        $failure = RlmFailure::factory()->create([
            'attempt' => 'Generated entity with default template.',
            'feedback' => 'PHPStan found type mismatch on line 42.',
            'owner_id' => $this->admin->id,
        ]);

        $this->assertSame('Generated entity with default template.', $failure->attempt);
        $this->assertSame('PHPStan found type mismatch on line 42.', $failure->feedback);
    }

    public function test_identity_metadata_columns_are_fillable(): void
    {
        $failure = RlmFailure::factory()->create([
            'validator_layer' => 'L1',
            'validator_id' => 'P-012',
            'entity_name' => 'Project',
            'phase' => 'phase-4',
            'file_path' => 'app/Models/Project.php',
            'owner_id' => $this->admin->id,
        ]);

        $this->assertSame('L1', $failure->validator_layer);
        $this->assertSame('P-012', $failure->validator_id);
        $this->assertSame('Project', $failure->entity_name);
        $this->assertSame('phase-4', $failure->phase);
        $this->assertSame('app/Models/Project.php', $failure->file_path);
    }

    public function test_trace_relationship_is_defined(): void
    {
        $failure = RlmFailure::factory()->create([
            'owner_id' => $this->admin->id,
            'trace_id' => null,
        ]);

        $this->assertNull($failure->trace);
        // Verifies the relationship method exists and returns null when no trace
    }

    public function test_embedding_text_includes_structured_fields(): void
    {
        $failure = RlmFailure::factory()->create([
            'title' => 'Test Title',
            'description' => 'Test Description',
            'attempt' => 'Test Attempt',
            'feedback' => 'Test Feedback',
            'root_cause' => 'Test Root Cause',
            'fix' => 'Test Fix',
            'preventive_rule' => 'Test Rule',
            'owner_id' => $this->admin->id,
        ]);

        $text = $failure->embeddingText();
        $this->assertStringContainsString('Test Attempt', $text);
        $this->assertStringContainsString('Test Feedback', $text);
    }

    public function test_searchable_array_includes_structured_fields(): void
    {
        $failure = RlmFailure::factory()->structured()->create([
            'owner_id' => $this->admin->id,
        ]);

        $array = $failure->toSearchableArray();

        $this->assertArrayHasKey('attempt', $array);
        $this->assertArrayHasKey('feedback', $array);
        $this->assertArrayHasKey('rule_hash', $array);
        $this->assertArrayHasKey('validator_layer', $array);
        $this->assertArrayHasKey('validator_id', $array);
        $this->assertArrayHasKey('entity_name', $array);
        $this->assertArrayHasKey('phase', $array);
    }
}
