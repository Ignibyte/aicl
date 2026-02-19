<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Enums\KnowledgeLinkRelationship;
use Aicl\Enums\LessonType;
use Aicl\Models\DistilledLesson;
use Aicl\Models\KnowledgeLink;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Rlm\DistillationService;
use Aicl\Rlm\EntityValidator;
use Aicl\Rlm\KnowledgeWriter;
use Aicl\Rlm\Mutators\Mutator;
use Aicl\Rlm\Mutators\MutatorConflictingFix;
use Aicl\Rlm\Mutators\MutatorFactoryTypeMismatch;
use Aicl\Rlm\Mutators\MutatorMissingSearchableColumns;
use Aicl\Rlm\Mutators\MutatorMissingTrait;
use Aicl\Rlm\Mutators\MutatorPolicyGap;
use Aicl\Rlm\Mutators\MutatorWrongNamespace;
use Aicl\Rlm\PatternRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * G.7: Full mutation suite test — 7-step protocol.
 *
 * 1. Generate baseline entity files (temp)
 * 2. Apply mutation → validate → assert failures detected
 * 3. Record failures to knowledge base
 * 4. Run distillation → assert lessons generated
 * 5. Assert recall surfaces relevant lessons
 * 6. Assert conflicting fix cannot promote without proof
 * 7. Assert proof-backed fix promotes correctly
 */
#[\PHPUnit\Framework\Attributes\Group('mutation')]
class MutationSuiteTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureDir;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id' => 1]);
        $this->fixtureDir = sys_get_temp_dir().'/aicl_mutation_test_'.uniqid();
        mkdir($this->fixtureDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $files = glob($this->fixtureDir.'/*');
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->fixtureDir);
        parent::tearDown();
    }

    // ─── Baseline source code fixtures ────────────────────────────

    private function baselineModel(): string
    {
        return <<<'PHP'
<?php

namespace App\Models;

use Aicl\Traits\HasAuditTrail;
use Aicl\Traits\HasEntityEvents;
use Aicl\Traits\HasStandardScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class MutationTarget extends Model
{
    use HasAuditTrail;
    use HasEntityEvents;
    use HasFactory;
    use HasStandardScopes;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    protected static function newFactory(): \Database\Factories\MutationTargetFactory
    {
        return \Database\Factories\MutationTargetFactory::new();
    }
}
PHP;
    }

    private function baselinePolicy(): string
    {
        return <<<'PHP'
<?php

namespace App\Policies;

use App\Models\MutationTarget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

class MutationTargetPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'MutationTarget';
    }

    public function view(User $user, Model $record): bool
    {
        if ($record->owner_id === $user->getKey()) {
            return true;
        }
        return parent::view($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        if ($record->owner_id === $user->getKey()) {
            return true;
        }
        return parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        if ($record->owner_id === $user->getKey()) {
            return true;
        }
        return parent::delete($user, $record);
    }
}
PHP;
    }

    private function baselineFactory(): string
    {
        return <<<'PHP'
<?php

namespace Database\Factories;

use App\Models\MutationTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MutationTarget>
 */
class MutationTargetFactory extends Factory
{
    protected $model = MutationTarget::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'description' => fake()->paragraph(),
            'owner_id' => User::factory(),
        ];
    }
}
PHP;
    }

    private function writeFixture(string $filename, string $content): string
    {
        $path = $this->fixtureDir.'/'.$filename;
        file_put_contents($path, $content);

        return $path;
    }

    // ─── Step 1: Baseline validates clean ─────────────────────────

    public function test_step1_baseline_validates_clean(): void
    {
        $modelPath = $this->writeFixture('model.php', $this->baselineModel());
        $policyPath = $this->writeFixture('policy.php', $this->baselinePolicy());
        $factoryPath = $this->writeFixture('factory.php', $this->baselineFactory());

        $validator = new EntityValidator('MutationTarget', PatternRegistry::VERSION);
        $validator->addFile('model', $modelPath);
        $validator->addFile('policy', $policyPath);
        $validator->addFile('factory', $factoryPath);
        $validator->validate();

        // Model + policy + factory patterns should all pass on baseline
        $modelFailures = collect($validator->failures())
            ->filter(fn ($r) => str_starts_with($r->pattern->name, 'model.') || str_starts_with($r->pattern->name, 'policy.') || str_starts_with($r->pattern->name, 'factory.'))
            ->values();

        $this->assertCount(0, $modelFailures, 'Baseline should have 0 model/policy/factory failures. Got: '.
            $modelFailures->map(fn ($r) => $r->pattern->name)->implode(', '));
    }

    // ─── Step 2: Each mutator is detected ─────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('mutatorProvider')]
    public function test_step2_mutator_detected(Mutator $mutator): void
    {
        // Get baseline for the target type
        $source = match ($mutator->target()) {
            'model' => $this->baselineModel(),
            'policy' => $this->baselinePolicy(),
            'factory' => $this->baselineFactory(),
        };

        // Apply mutation
        $mutated = $mutator->mutate($source);
        $this->assertNotSame($source, $mutated, "Mutator {$mutator->name()} should change the source");

        // Write mutated file and validate
        $path = $this->writeFixture($mutator->target().'_mutated.php', $mutated);

        $validator = new EntityValidator('MutationTarget', PatternRegistry::VERSION);
        $validator->addFile($mutator->target(), $path);
        $validator->validate();

        // Collect failure pattern names
        $failureNames = collect($validator->failures())
            ->map(fn ($r) => $r->pattern->name)
            ->toArray();

        foreach ($mutator->expectedFailures() as $expected) {
            $this->assertContains(
                $expected,
                $failureNames,
                "Mutator '{$mutator->name()}' should trigger failure '{$expected}'. Got: ".implode(', ', $failureNames),
            );
        }
    }

    /**
     * @return array<string, array{Mutator}>
     */
    public static function mutatorProvider(): array
    {
        return [
            'wrong_namespace' => [new MutatorWrongNamespace],
            'missing_trait' => [new MutatorMissingTrait],
            'policy_gap' => [new MutatorPolicyGap],
            'factory_type_mismatch' => [new MutatorFactoryTypeMismatch],
            'missing_fillable' => [new MutatorMissingSearchableColumns],
            'conflicting_fix' => [new MutatorConflictingFix],
        ];
    }

    // ─── Step 3: Failures recorded to knowledge base ──────────────

    public function test_step3_failures_recorded(): void
    {
        $mutator = new MutatorWrongNamespace;
        $mutated = $mutator->mutate($this->baselineModel());
        $path = $this->writeFixture('model_ns.php', $mutated);

        $validator = new EntityValidator('MutationTarget', PatternRegistry::VERSION);
        $validator->addFile('model', $path);
        $validator->validate();

        // Record each failure via KnowledgeWriter
        $writer = app(KnowledgeWriter::class);
        foreach ($validator->failures() as $result) {
            if (in_array($result->pattern->name, $mutator->expectedFailures())) {
                $writer->recordFailure([
                    'failure_code' => 'MUT-'.strtoupper($mutator->name()),
                    'title' => "Mutation detected: {$result->pattern->name}",
                    'description' => $result->message,
                    'category' => FailureCategory::Scaffolding->value,
                    'severity' => FailureSeverity::High->value,
                    'preventive_rule' => "Always verify {$result->pattern->name} pattern after scaffolding",
                    'entity_name' => 'MutationTarget',
                    'phase' => 'phase-4',
                    'owner_id' => $this->admin->id,
                ]);
            }
        }

        $this->assertDatabaseHas('rlm_failures', [
            'failure_code' => 'MUT-WRONG_NAMESPACE',
            'entity_name' => 'MutationTarget',
        ]);
    }

    // ─── Step 4: Distillation produces lessons ────────────────────

    public function test_step4_distillation_produces_lessons(): void
    {
        // Create multiple failures with same preventive_rule to trigger clustering
        $rule = 'Always verify model namespace pattern after scaffolding';
        RlmFailure::factory()->structured()->create([
            'failure_code' => 'BF-MUT-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'preventive_rule' => $rule,
            'entity_name' => 'MutationTarget',
            'phase' => 'phase-4',
            'owner_id' => $this->admin->id,
        ]);
        RlmFailure::factory()->structured()->create([
            'failure_code' => 'BF-MUT-002',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'preventive_rule' => $rule,
            'entity_name' => 'MutationTargetTwo',
            'phase' => 'phase-4',
            'owner_id' => $this->admin->id,
        ]);

        $service = app(DistillationService::class);
        $stats = $service->distill();

        $this->assertGreaterThanOrEqual(1, $stats['clusters']);
        $this->assertGreaterThanOrEqual(1, $stats['lessons']);

        // Verify at least one distilled lesson exists
        $this->assertTrue(DistilledLesson::query()->exists());
    }

    // ─── Step 5: Recall surfaces lessons for agents ───────────────

    public function test_step5_recall_surfaces_lessons(): void
    {
        // Create a distilled lesson targeted at architect, phase 3
        DistilledLesson::create([
            'lesson_code' => 'DL-MUT-001-A3',
            'target_agent' => 'architect',
            'target_phase' => 3,
            'title' => 'Build: verify model namespace after scaffolding',
            'guidance' => "WHEN: Scaffolding a new entity model\nTHEN: Verify namespace is App\\Models\nRULE: Always verify model.namespace pattern",
            'source_failure_codes' => ['MUT-NS-001', 'MUT-NS-002'],
            'trigger_context' => ['category' => 'convention'],
            'impact_score' => 10.0,
            'confidence' => 0.8,
            'is_active' => true,
            'generation' => 1,
            'last_distilled_at' => now(),
            'owner_id' => $this->admin->id,
        ]);

        $service = app(DistillationService::class);
        $lessons = $service->getTopLessons('architect', 3, 5);

        $this->assertTrue($lessons->isNotEmpty());
        $this->assertStringContainsString('namespace', $lessons->first()->guidance);
    }

    // ─── Step 6: Conflicting fix cannot promote without proof ─────

    public function test_step6_conflicting_fix_blocked_without_proof(): void
    {
        // Create an observation (not instruction) — no proof hook
        $lesson = RlmLesson::factory()->create([
            'topic' => 'mutation-test',
            'summary' => 'Use BaseModel instead of Model',
            'detail' => 'A plausible but wrong suggestion',
            'lesson_type' => LessonType::Observation,
            'is_verified' => false,
            'owner_id' => $this->admin->id,
        ]);

        $service = app(DistillationService::class);
        $result = $service->promoteObservation($lesson, clusterSize: 3);

        // Without proof (KnowledgeLink), promotion should flag needs_review
        $lesson->refresh();
        $this->assertFalse($result['promoted']);
        $this->assertTrue($lesson->needs_review);
    }

    // ─── Step 7: Proof-backed fix promotes correctly ──────────────

    public function test_step7_proof_backed_fix_promotes(): void
    {
        // Create an observation WITH a proof hook
        $lesson = RlmLesson::factory()->create([
            'topic' => 'mutation-test',
            'summary' => 'Always check model namespace',
            'detail' => 'Verified via test_step2_mutator_detected(wrong_namespace)',
            'lesson_type' => LessonType::Observation,
            'is_verified' => false,
            'owner_id' => $this->admin->id,
        ]);

        // Add proof link — source is the lesson (promoteObservation checks source_type=lesson)
        $failure = RlmFailure::factory()->create([
            'failure_code' => 'MUT-NS-PROOF',
            'owner_id' => $this->admin->id,
        ]);
        KnowledgeLink::create([
            'source_type' => RlmLesson::class,
            'source_id' => $lesson->id,
            'target_type' => RlmFailure::class,
            'target_id' => $failure->id,
            'relationship' => KnowledgeLinkRelationship::LearnedFrom,
            'link_type' => \Aicl\Enums\KnowledgeLinkType::TestCase,
            'reference' => 'MutationSuiteTest::test_step2_mutator_detected',
        ]);

        $service = app(DistillationService::class);
        $result = $service->promoteObservation($lesson, clusterSize: 3);

        $lesson->refresh();
        $this->assertTrue($result['promoted']);
        $this->assertSame(LessonType::Instruction, $lesson->lesson_type);
        $this->assertTrue($lesson->is_verified);
    }

    // ─── Mutator unit tests ───────────────────────────────────────

    public function test_all_mutators_implement_interface(): void
    {
        $mutators = [
            new MutatorWrongNamespace,
            new MutatorMissingTrait,
            new MutatorPolicyGap,
            new MutatorFactoryTypeMismatch,
            new MutatorMissingSearchableColumns,
            new MutatorConflictingFix,
        ];

        foreach ($mutators as $mutator) {
            $this->assertInstanceOf(Mutator::class, $mutator);
            $this->assertNotEmpty($mutator->name());
            $this->assertNotEmpty($mutator->target());
            $this->assertNotEmpty($mutator->expectedFailures());
        }
    }

    public function test_mutator_wrong_namespace_changes_namespace(): void
    {
        $mutator = new MutatorWrongNamespace;
        $result = $mutator->mutate($this->baselineModel());

        $this->assertStringNotContainsString('namespace App\\Models;', $result);
    }

    public function test_mutator_missing_trait_removes_has_factory(): void
    {
        $mutator = new MutatorMissingTrait;
        $result = $mutator->mutate($this->baselineModel());

        $this->assertStringNotContainsString('use HasFactory;', $result);
        // Other traits should remain
        $this->assertStringContainsString('use HasEntityEvents;', $result);
    }

    public function test_mutator_conflicting_fix_replaces_extends(): void
    {
        $mutator = new MutatorConflictingFix;
        $result = $mutator->mutate($this->baselineModel());

        $this->assertStringContainsString('extends BaseModel', $result);
        $this->assertStringNotContainsString('extends Model', $result);
    }
}
