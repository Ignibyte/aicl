<?php

namespace Aicl\Tests\Unit\Seeders;

use Aicl\Database\Seeders\PreventionRuleSeeder;
use Aicl\Database\Seeders\RlmLessonSeeder;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuratedSeederTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id' => 1]);
    }

    // ─── RlmLessonSeeder ─────────────────────────────────────

    public function test_lesson_seeder_creates_curated_lessons(): void
    {
        $this->seed(RlmLessonSeeder::class);

        $count = RlmLesson::query()->count();

        $this->assertGreaterThanOrEqual(15, $count, 'Should seed at least 15 curated lessons');
        $this->assertLessThanOrEqual(20, $count, 'Should seed no more than 20 curated lessons');
    }

    public function test_lesson_seeder_sets_base_seeder_source(): void
    {
        $this->seed(RlmLessonSeeder::class);

        $allHaveSource = RlmLesson::query()
            ->where('source', 'base-seeder')
            ->count();

        $this->assertSame(
            RlmLesson::query()->count(),
            $allHaveSource,
            'All seeded lessons should have source=base-seeder'
        );
    }

    public function test_lesson_seeder_sets_verified_and_active(): void
    {
        $this->seed(RlmLessonSeeder::class);

        $allVerified = RlmLesson::query()
            ->where('is_verified', true)
            ->where('is_active', true)
            ->count();

        $this->assertSame(
            RlmLesson::query()->count(),
            $allVerified,
            'All seeded lessons should be verified and active'
        );
    }

    public function test_lesson_seeder_covers_key_topics(): void
    {
        $this->seed(RlmLessonSeeder::class);

        $topics = RlmLesson::query()
            ->pluck('topic')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $requiredTopics = ['auth', 'database', 'filament', 'laravel', 'process', 'scaffolder', 'tailwind', 'testing'];

        foreach ($requiredTopics as $topic) {
            $this->assertContains($topic, $topics, "Missing required topic: {$topic}");
        }
    }

    public function test_lesson_seeder_has_no_lorem_ipsum(): void
    {
        $this->seed(RlmLessonSeeder::class);

        $lessons = RlmLesson::query()->get();

        foreach ($lessons as $lesson) {
            $this->assertStringNotContainsString('lorem', strtolower($lesson->summary), "Lesson [{$lesson->topic}>{$lesson->subtopic}] summary contains lorem ipsum");
            $this->assertStringNotContainsString('ipsum', strtolower($lesson->summary), "Lesson [{$lesson->topic}>{$lesson->subtopic}] summary contains lorem ipsum");
            $this->assertStringNotContainsString('lorem', strtolower($lesson->detail), "Lesson [{$lesson->topic}>{$lesson->subtopic}] detail contains lorem ipsum");
        }
    }

    public function test_lesson_seeder_is_idempotent(): void
    {
        $this->seed(RlmLessonSeeder::class);
        $countAfterFirst = RlmLesson::query()->count();

        $this->seed(RlmLessonSeeder::class);
        $countAfterSecond = RlmLesson::query()->count();

        $this->assertSame($countAfterFirst, $countAfterSecond, 'Running seeder twice should not create duplicates');
    }

    public function test_lesson_seeder_has_context_tags(): void
    {
        $this->seed(RlmLessonSeeder::class);

        $withContextTags = RlmLesson::query()
            ->whereNotNull('context_tags')
            ->count();

        $this->assertSame(
            RlmLesson::query()->count(),
            $withContextTags,
            'All seeded lessons should have context_tags'
        );
    }

    // ─── PreventionRuleSeeder ────────────────────────────────

    public function test_prevention_rule_seeder_creates_curated_rules(): void
    {
        $this->seedBaseFailures();
        $this->seed(PreventionRuleSeeder::class);

        $count = PreventionRule::query()->count();

        $this->assertGreaterThanOrEqual(10, $count, 'Should seed at least 10 curated rules');
    }

    public function test_prevention_rule_seeder_links_to_base_failures(): void
    {
        $this->seedBaseFailures();
        $this->seed(PreventionRuleSeeder::class);

        $linkedRules = PreventionRule::query()
            ->whereNotNull('rlm_failure_id')
            ->count();

        $this->assertGreaterThan(0, $linkedRules, 'At least some rules should be linked to base failures');
    }

    public function test_prevention_rule_seeder_has_no_lorem_ipsum(): void
    {
        $this->seedBaseFailures();
        $this->seed(PreventionRuleSeeder::class);

        $rules = PreventionRule::query()->get();

        foreach ($rules as $rule) {
            $this->assertStringNotContainsString('lorem', strtolower($rule->rule_text), "Rule [{$rule->id}] contains lorem ipsum");
            $this->assertStringNotContainsString('ipsum', strtolower($rule->rule_text), "Rule [{$rule->id}] contains lorem ipsum");
        }
    }

    public function test_prevention_rule_seeder_is_idempotent(): void
    {
        $this->seedBaseFailures();
        $this->seed(PreventionRuleSeeder::class);
        $countAfterFirst = PreventionRule::query()->count();

        $this->seed(PreventionRuleSeeder::class);
        $countAfterSecond = PreventionRule::query()->count();

        $this->assertSame($countAfterFirst, $countAfterSecond, 'Running seeder twice should not create duplicates');
    }

    public function test_prevention_rule_seeder_has_trigger_context(): void
    {
        $this->seedBaseFailures();
        $this->seed(PreventionRuleSeeder::class);

        $withContext = PreventionRule::query()
            ->whereNotNull('trigger_context')
            ->count();

        $this->assertSame(
            PreventionRule::query()->count(),
            $withContext,
            'All seeded rules should have trigger_context'
        );
    }

    public function test_prevention_rule_seeder_has_valid_confidence(): void
    {
        $this->seedBaseFailures();
        $this->seed(PreventionRuleSeeder::class);

        $rules = PreventionRule::query()->get();

        foreach ($rules as $rule) {
            $confidence = (float) $rule->confidence;
            $this->assertGreaterThanOrEqual(0.0, $confidence, 'Confidence must be >= 0.0');
            $this->assertLessThanOrEqual(1.0, $confidence, 'Confidence must be <= 1.0');
        }
    }

    private function seedBaseFailures(): void
    {
        $baseCodes = [
            'BF-001', 'BF-002', 'BF-003', 'BF-004', 'BF-006',
            'BF-007', 'BF-008', 'BF-009', 'BF-010', 'BF-012',
            'BF-013', 'BF-014', 'BF-015',
        ];

        foreach ($baseCodes as $code) {
            RlmFailure::factory()->create([
                'failure_code' => $code,
                'owner_id' => $this->admin->id,
            ]);
        }
    }
}
