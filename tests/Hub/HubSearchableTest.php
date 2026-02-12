<?php

namespace Aicl\Tests\Hub;

use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\Traits\HasSearchableFields;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HubSearchableTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    // --- Trait presence ---

    public function test_rlm_failure_uses_has_searchable_fields(): void
    {
        $this->assertContains(
            HasSearchableFields::class,
            class_uses_recursive(RlmFailure::class),
        );
    }

    public function test_rlm_lesson_uses_has_searchable_fields(): void
    {
        $this->assertContains(
            HasSearchableFields::class,
            class_uses_recursive(RlmLesson::class),
        );
    }

    public function test_rlm_pattern_uses_has_searchable_fields(): void
    {
        $this->assertContains(
            HasSearchableFields::class,
            class_uses_recursive(RlmPattern::class),
        );
    }

    // --- searchableFields() ---

    public function test_rlm_failure_searchable_fields(): void
    {
        $failure = RlmFailure::factory()->make(['owner_id' => $this->admin->id]);

        $reflection = new \ReflectionMethod($failure, 'searchableFields');
        $reflection->setAccessible(true);

        $fields = $reflection->invoke($failure);

        $this->assertEquals(['title', 'description', 'failure_code', 'root_cause', 'fix'], $fields);
    }

    public function test_rlm_lesson_searchable_fields(): void
    {
        $lesson = RlmLesson::factory()->make(['owner_id' => $this->admin->id]);

        $reflection = new \ReflectionMethod($lesson, 'searchableFields');
        $reflection->setAccessible(true);

        $fields = $reflection->invoke($lesson);

        $this->assertEquals(['topic', 'summary', 'detail', 'tags'], $fields);
    }

    public function test_rlm_pattern_searchable_fields(): void
    {
        $pattern = RlmPattern::factory()->make(['owner_id' => $this->admin->id]);

        $reflection = new \ReflectionMethod($pattern, 'searchableFields');
        $reflection->setAccessible(true);

        $fields = $reflection->invoke($pattern);

        $this->assertEquals(['name', 'description', 'target', 'category'], $fields);
    }

    // --- toSearchableArray() ---

    public function test_rlm_failure_to_searchable_array(): void
    {
        Queue::fake();

        $failure = RlmFailure::factory()->create([
            'title' => 'Test failure',
            'description' => 'Test description',
            'failure_code' => 'BF-001',
            'root_cause' => 'Missing validation',
            'fix' => 'Add validation',
            'owner_id' => $this->admin->id,
        ]);

        $array = $failure->toSearchableArray();

        $this->assertEquals($failure->getKey(), $array['id']);
        $this->assertEquals('Test failure', $array['title']);
        $this->assertEquals('Test description', $array['description']);
        $this->assertEquals('BF-001', $array['failure_code']);
        $this->assertEquals('Missing validation', $array['root_cause']);
        $this->assertEquals('Add validation', $array['fix']);
        $this->assertArrayHasKey('category', $array);
        $this->assertArrayHasKey('severity', $array);
        $this->assertArrayNotHasKey('embedding', $array); // Only present when cached
    }

    public function test_rlm_lesson_to_searchable_array(): void
    {
        Queue::fake();

        $lesson = RlmLesson::factory()->create([
            'topic' => 'testing',
            'summary' => 'Always use factories',
            'detail' => 'Detailed explanation',
            'tags' => 'testing,factories',
            'owner_id' => $this->admin->id,
        ]);

        $array = $lesson->toSearchableArray();

        $this->assertEquals($lesson->getKey(), $array['id']);
        $this->assertEquals('testing', $array['topic']);
        $this->assertEquals('Always use factories', $array['summary']);
        $this->assertArrayHasKey('confidence', $array);
        $this->assertArrayNotHasKey('embedding', $array); // Only present when cached
    }

    public function test_rlm_pattern_to_searchable_array(): void
    {
        Queue::fake();

        $pattern = RlmPattern::factory()->create([
            'name' => 'HasTimestamps',
            'description' => 'Check for timestamps',
            'target' => 'migration',
            'category' => 'scaffolding',
            'owner_id' => $this->admin->id,
        ]);

        $array = $pattern->toSearchableArray();

        $this->assertEquals($pattern->getKey(), $array['id']);
        $this->assertEquals('HasTimestamps', $array['name']);
        $this->assertEquals('Check for timestamps', $array['description']);
        $this->assertEquals('migration', $array['target']);
        $this->assertEquals('scaffolding', $array['category']);
        $this->assertArrayNotHasKey('check_regex', $array); // Not in searchableFields
    }

    // --- shouldBeSearchable() with feature flag ---

    public function test_should_be_searchable_returns_false_when_rlm_search_disabled(): void
    {
        config(['aicl.features.rlm_search' => false]);

        $failure = RlmFailure::factory()->make(['owner_id' => $this->admin->id]);
        $lesson = RlmLesson::factory()->make(['owner_id' => $this->admin->id]);
        $pattern = RlmPattern::factory()->make(['owner_id' => $this->admin->id]);

        $this->assertFalse($failure->shouldBeSearchable());
        $this->assertFalse($lesson->shouldBeSearchable());
        $this->assertFalse($pattern->shouldBeSearchable());
    }

    public function test_should_be_searchable_returns_true_when_rlm_search_enabled(): void
    {
        config(['aicl.features.rlm_search' => true]);

        $failure = RlmFailure::factory()->make(['owner_id' => $this->admin->id]);
        $lesson = RlmLesson::factory()->make(['owner_id' => $this->admin->id]);
        $pattern = RlmPattern::factory()->make(['owner_id' => $this->admin->id]);

        $this->assertTrue($failure->shouldBeSearchable());
        $this->assertTrue($lesson->shouldBeSearchable());
        $this->assertTrue($pattern->shouldBeSearchable());
    }

    public function test_should_be_searchable_returns_false_for_trashed_even_when_enabled(): void
    {
        config(['aicl.features.rlm_search' => true]);

        $failure = RlmFailure::factory()->create(['owner_id' => $this->admin->id]);
        $failure->delete(); // soft delete

        $this->assertFalse($failure->shouldBeSearchable());
    }

    // --- searchableAs() (index naming) ---

    public function test_rlm_failure_searchable_as(): void
    {
        $failure = new RlmFailure;

        $this->assertEquals('aicl_rlm_failures', $failure->searchableAs());
    }

    public function test_rlm_lesson_searchable_as(): void
    {
        $lesson = new RlmLesson;

        $this->assertEquals('aicl_rlm_lessons', $lesson->searchableAs());
    }

    public function test_rlm_pattern_searchable_as(): void
    {
        $pattern = new RlmPattern;

        $this->assertEquals('aicl_rlm_patterns', $pattern->searchableAs());
    }

    // --- ScoutImportCommand discovers package models ---

    public function test_scout_import_discovers_package_models(): void
    {
        $command = new \Aicl\Console\Commands\ScoutImportCommand;

        $reflection = new \ReflectionMethod($command, 'discoverSearchableModels');
        $reflection->setAccessible(true);

        $models = $reflection->invoke($command);

        $this->assertContains(RlmFailure::class, $models->all());
        $this->assertContains(RlmLesson::class, $models->all());
        $this->assertContains(RlmPattern::class, $models->all());
    }
}
