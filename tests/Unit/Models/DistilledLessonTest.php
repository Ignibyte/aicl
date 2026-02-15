<?php

namespace Aicl\Tests\Unit\Models;

use Aicl\Models\DistilledLesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistilledLessonTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id' => 1]);
    }

    public function test_factory_creates_valid_model(): void
    {
        $lesson = DistilledLesson::factory()->create(['owner_id' => $this->admin->id]);

        $this->assertNotNull($lesson->id);
        $this->assertNotNull($lesson->lesson_code);
        $this->assertNotNull($lesson->title);
        $this->assertNotNull($lesson->guidance);
        $this->assertNotNull($lesson->target_agent);
    }

    public function test_owner_relationship(): void
    {
        $lesson = DistilledLesson::factory()->create(['owner_id' => $this->admin->id]);

        $this->assertSame($this->admin->id, $lesson->owner->id);
    }

    public function test_scope_for_agent(): void
    {
        DistilledLesson::factory()->create([
            'target_agent' => 'architect',
            'owner_id' => $this->admin->id,
        ]);
        DistilledLesson::factory()->create([
            'target_agent' => 'tester',
            'owner_id' => $this->admin->id,
        ]);

        $this->assertSame(1, DistilledLesson::query()->forAgent('architect')->count());
        $this->assertSame(1, DistilledLesson::query()->forAgent('tester')->count());
    }

    public function test_scope_for_phase(): void
    {
        DistilledLesson::factory()->create([
            'target_phase' => 3,
            'owner_id' => $this->admin->id,
        ]);
        DistilledLesson::factory()->create([
            'target_phase' => 7,
            'owner_id' => $this->admin->id,
        ]);

        $this->assertSame(1, DistilledLesson::query()->forPhase(3)->count());
        $this->assertSame(1, DistilledLesson::query()->forPhase(7)->count());
    }

    public function test_scope_high_impact(): void
    {
        DistilledLesson::factory()->create([
            'impact_score' => 25.0,
            'owner_id' => $this->admin->id,
        ]);
        DistilledLesson::factory()->create([
            'impact_score' => 2.0,
            'owner_id' => $this->admin->id,
        ]);

        $this->assertSame(1, DistilledLesson::query()->highImpact(10.0)->count());
    }

    public function test_casts_arrays_correctly(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'source_failure_codes' => ['BF-001', 'BF-005'],
            'trigger_context' => ['has_states' => true],
            'owner_id' => $this->admin->id,
        ]);

        $lesson->refresh();

        $this->assertIsArray($lesson->source_failure_codes);
        $this->assertContains('BF-001', $lesson->source_failure_codes);
        $this->assertIsArray($lesson->trigger_context);
        $this->assertTrue($lesson->trigger_context['has_states']);
    }

    public function test_soft_deletes(): void
    {
        $lesson = DistilledLesson::factory()->create(['owner_id' => $this->admin->id]);
        $lesson->delete();

        $this->assertSame(0, DistilledLesson::query()->count());
        $this->assertSame(1, DistilledLesson::withTrashed()->count());
    }

    public function test_embedding_text(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'title' => 'Test Title',
            'guidance' => 'Test Guidance',
            'target_agent' => 'architect',
            'owner_id' => $this->admin->id,
        ]);

        $text = $lesson->embeddingText();

        $this->assertStringContainsString('Test Title', $text);
        $this->assertStringContainsString('Test Guidance', $text);
        $this->assertStringContainsString('architect', $text);
    }

    public function test_searchable_as(): void
    {
        $lesson = DistilledLesson::factory()->create(['owner_id' => $this->admin->id]);

        $this->assertSame('aicl_distilled_lessons', $lesson->searchableAs());
    }

    public function test_to_searchable_array(): void
    {
        $lesson = DistilledLesson::factory()->create([
            'lesson_code' => 'DL-001',
            'title' => 'Test',
            'target_agent' => 'architect',
            'owner_id' => $this->admin->id,
        ]);

        $array = $lesson->toSearchableArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('lesson_code', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('guidance', $array);
        $this->assertArrayHasKey('target_agent', $array);
        $this->assertArrayHasKey('impact_score', $array);
    }

    public function test_factory_for_agent_state(): void
    {
        $lesson = DistilledLesson::factory()->forAgent('tester')->create(['owner_id' => $this->admin->id]);

        $this->assertSame('tester', $lesson->target_agent);
    }

    public function test_factory_high_impact_state(): void
    {
        $lesson = DistilledLesson::factory()->highImpact()->create(['owner_id' => $this->admin->id]);

        $this->assertGreaterThanOrEqual(20.0, (float) $lesson->impact_score);
    }

    public function test_factory_inactive_state(): void
    {
        $lesson = DistilledLesson::factory()->inactive()->create(['owner_id' => $this->admin->id]);

        $this->assertFalse($lesson->is_active);
    }
}
