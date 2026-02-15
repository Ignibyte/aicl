<?php

namespace Aicl\Tests\Unit\Traits;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityCreating;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityDeleting;
use Aicl\Events\EntityUpdated;
use Aicl\Events\EntityUpdating;
use Aicl\Jobs\GenerateEmbeddingJob;
use Aicl\Models\RlmPattern;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Tests\TestCase;

class TraitCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Hub models hardcode owner_id => 1
        $this->owner = User::factory()->create(['id' => 1]);

        // Prevent embedding jobs from dispatching
        Queue::fake();
    }

    // ========================================================================
    // HasAuditTrail — uses LogsActivity with sensible defaults
    // ========================================================================

    public function test_rlm_pattern_uses_logs_activity_trait(): void
    {
        $this->assertContains(
            LogsActivity::class,
            class_uses_recursive(RlmPattern::class),
        );
    }

    public function test_get_activity_log_options_returns_log_options_instance(): void
    {
        $pattern = RlmPattern::factory()->make(['owner_id' => $this->owner->id]);

        $options = $pattern->getActivitylogOptions();

        $this->assertInstanceOf(LogOptions::class, $options);
    }

    public function test_activity_log_options_logs_all_attributes(): void
    {
        $pattern = RlmPattern::factory()->make(['owner_id' => $this->owner->id]);

        $options = $pattern->getActivitylogOptions();

        // LogOptions with logAll() sets logAttributes to ['*']
        $this->assertSame(['*'], $options->logAttributes);
    }

    public function test_activity_log_options_logs_only_dirty_attributes(): void
    {
        $pattern = RlmPattern::factory()->make(['owner_id' => $this->owner->id]);

        $options = $pattern->getActivitylogOptions();

        $this->assertTrue($options->logOnlyDirty);
    }

    public function test_activity_log_options_skips_empty_logs(): void
    {
        $pattern = RlmPattern::factory()->make(['owner_id' => $this->owner->id]);

        $options = $pattern->getActivitylogOptions();

        $this->assertTrue($options->submitEmptyLogs === false);
    }

    public function test_activity_log_description_includes_class_name_and_event(): void
    {
        $pattern = RlmPattern::factory()->make(['owner_id' => $this->owner->id]);

        $options = $pattern->getActivitylogOptions();

        // The descriptionForEvent closure produces "{ClassName} was {event}"
        $description = $options->descriptionForEvent;
        $this->assertNotNull($description);

        $result = $description('created');
        $this->assertSame('RlmPattern was created', $result);
    }

    // ========================================================================
    // HasEmbeddings — dispatches GenerateEmbeddingJob, cache methods
    // ========================================================================

    public function test_dispatch_embedding_job_dispatches_generate_embedding_job(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => $this->owner->id]);

        config(['aicl.features.rlm_search' => true]);

        // Clear the faked queue to isolate this assertion
        Queue::fake();

        $pattern->dispatchEmbeddingJob();

        Queue::assertPushed(GenerateEmbeddingJob::class, function ($job) use ($pattern) {
            return $job->model->is($pattern);
        });
    }

    public function test_dispatch_embedding_job_skips_when_rlm_search_disabled(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => $this->owner->id]);

        config(['aicl.features.rlm_search' => false]);

        Queue::fake();

        $pattern->dispatchEmbeddingJob();

        Queue::assertNotPushed(GenerateEmbeddingJob::class);
    }

    public function test_embedding_text_returns_concatenated_string(): void
    {
        $pattern = RlmPattern::factory()->make([
            'owner_id' => $this->owner->id,
            'name' => 'Test Pattern',
            'description' => 'A useful description',
            'target' => 'model',
        ]);

        $text = $pattern->embeddingText();

        $this->assertStringContainsString('Test Pattern', $text);
        $this->assertStringContainsString('A useful description', $text);
        $this->assertStringContainsString('model', $text);
    }

    public function test_cache_embedding_stores_vector_in_cache(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => $this->owner->id]);

        $embedding = [0.1, 0.2, 0.3];

        $pattern->cacheEmbedding($embedding);

        $cacheKey = 'embedding:'.RlmPattern::class.':'.$pattern->getKey();
        $this->assertSame($embedding, Cache::get($cacheKey));
    }

    public function test_get_cached_embedding_returns_stored_vector(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => $this->owner->id]);

        $embedding = [0.5, 0.6, 0.7];
        $pattern->cacheEmbedding($embedding);

        $result = $pattern->getCachedEmbedding();

        $this->assertSame($embedding, $result);
    }

    public function test_get_cached_embedding_returns_null_when_not_cached(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => $this->owner->id]);

        $result = $pattern->getCachedEmbedding();

        $this->assertNull($result);
    }

    public function test_clear_cached_embedding_removes_from_cache(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => $this->owner->id]);

        $pattern->cacheEmbedding([0.1, 0.2]);
        $this->assertNotNull($pattern->getCachedEmbedding());

        $pattern->clearCachedEmbedding();

        $this->assertNull($pattern->getCachedEmbedding());
    }

    // ========================================================================
    // HasEntityEvents — dispatches entity lifecycle events
    // ========================================================================

    public function test_creating_model_dispatches_entity_creating_event(): void
    {
        Event::fake([EntityCreating::class, EntityCreated::class]);

        RlmPattern::factory()->create(['owner_id' => $this->owner->id]);

        Event::assertDispatched(EntityCreating::class, function ($event) {
            return $event->entity instanceof RlmPattern;
        });
    }

    public function test_creating_model_dispatches_entity_created_event(): void
    {
        Event::fake([EntityCreating::class, EntityCreated::class]);

        $pattern = RlmPattern::factory()->create(['owner_id' => $this->owner->id]);

        Event::assertDispatched(EntityCreated::class, function ($event) use ($pattern) {
            return $event->entity instanceof RlmPattern
                && $event->entity->getKey() === $pattern->getKey();
        });
    }

    public function test_updating_model_dispatches_entity_updating_event(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => $this->owner->id]);

        Event::fake([EntityUpdating::class, EntityUpdated::class]);

        $pattern->update(['name' => 'Updated Name']);

        Event::assertDispatched(EntityUpdating::class, function ($event) use ($pattern) {
            return $event->entity->getKey() === $pattern->getKey();
        });
    }

    public function test_updating_model_dispatches_entity_updated_event(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => $this->owner->id]);

        Event::fake([EntityUpdating::class, EntityUpdated::class]);

        $pattern->update(['name' => 'Updated Name']);

        Event::assertDispatched(EntityUpdated::class, function ($event) use ($pattern) {
            return $event->entity->getKey() === $pattern->getKey();
        });
    }

    public function test_deleting_model_dispatches_entity_deleting_event(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => $this->owner->id]);

        Event::fake([EntityDeleting::class, EntityDeleted::class]);

        $pattern->delete();

        Event::assertDispatched(EntityDeleting::class, function ($event) use ($pattern) {
            return $event->entity->getKey() === $pattern->getKey();
        });
    }

    public function test_deleting_model_dispatches_entity_deleted_event(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => $this->owner->id]);

        Event::fake([EntityDeleting::class, EntityDeleted::class]);

        $pattern->delete();

        Event::assertDispatched(EntityDeleted::class);
    }

    // ========================================================================
    // HasStandardScopes — active, inactive, search, byUser, recent
    // ========================================================================

    public function test_scope_active_returns_only_active_records(): void
    {
        RlmPattern::factory()->create(['owner_id' => $this->owner->id, 'is_active' => true]);
        RlmPattern::factory()->create(['owner_id' => $this->owner->id, 'is_active' => false]);

        $active = RlmPattern::active()->get();

        $this->assertCount(1, $active);
        $this->assertTrue($active->first()->is_active);
    }

    public function test_scope_inactive_returns_only_inactive_records(): void
    {
        RlmPattern::factory()->create(['owner_id' => $this->owner->id, 'is_active' => true]);
        RlmPattern::factory()->create(['owner_id' => $this->owner->id, 'is_active' => false]);

        $inactive = RlmPattern::inactive()->get();

        $this->assertCount(1, $inactive);
        $this->assertFalse($inactive->first()->is_active);
    }

    public function test_scope_search_filters_by_name(): void
    {
        RlmPattern::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Unique Searchable Name',
        ]);
        RlmPattern::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Other Pattern',
        ]);

        $results = RlmPattern::search('Unique Searchable')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Unique Searchable Name', $results->first()->name);
    }

    public function test_scope_search_is_case_insensitive(): void
    {
        RlmPattern::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'CamelCase Pattern',
        ]);

        $results = RlmPattern::search('camelcase')->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_search_searches_multiple_columns(): void
    {
        RlmPattern::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Plain Name',
            'description' => 'XyzUniqueDescription',
            'target' => 'model',
            'category' => 'structural',
        ]);

        // Search by description column
        $results = RlmPattern::search('XyzUniqueDescription')->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_search_returns_empty_when_no_match(): void
    {
        RlmPattern::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Some Pattern',
        ]);

        $results = RlmPattern::search('nonexistent_xyz_999')->get();

        $this->assertCount(0, $results);
    }

    public function test_scope_by_user_builds_correct_query_with_user_id_fallback(): void
    {
        // RlmPattern does not have 'created_by' as fillable, so byUser
        // falls back to 'user_id'. We verify the SQL contains the correct column.
        $query = RlmPattern::byUser($this->owner->id)->toRawSql();

        $this->assertStringContainsString('"user_id"', $query);
    }

    public function test_scope_by_user_accepts_model_instance(): void
    {
        $query = RlmPattern::byUser($this->owner)->toRawSql();

        // Should use the user's key (1) in the query
        $this->assertStringContainsString('"user_id"', $query);
        $this->assertStringContainsString((string) $this->owner->id, $query);
    }

    public function test_scope_recent_filters_by_days(): void
    {
        RlmPattern::factory()->create([
            'owner_id' => $this->owner->id,
            'created_at' => now()->subDays(5),
        ]);
        RlmPattern::factory()->create([
            'owner_id' => $this->owner->id,
            'created_at' => now()->subDays(60),
        ]);

        $recent = RlmPattern::recent(30)->get();

        $this->assertCount(1, $recent);
    }

    public function test_scope_recent_uses_default_of_30_days(): void
    {
        RlmPattern::factory()->create([
            'owner_id' => $this->owner->id,
            'created_at' => now()->subDays(10),
        ]);
        RlmPattern::factory()->create([
            'owner_id' => $this->owner->id,
            'created_at' => now()->subDays(45),
        ]);

        $recent = RlmPattern::recent()->get();

        $this->assertCount(1, $recent);
    }

    public function test_scope_active_combined_with_search(): void
    {
        RlmPattern::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Active Combo Pattern',
            'is_active' => true,
        ]);
        RlmPattern::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Inactive Combo Pattern',
            'is_active' => false,
        ]);

        $results = RlmPattern::active()->search('Combo Pattern')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Active Combo Pattern', $results->first()->name);
    }

    public function test_searchable_columns_returns_expected_columns_for_rlm_pattern(): void
    {
        $pattern = new RlmPattern;

        // Use reflection to access the protected method
        $reflection = new \ReflectionMethod($pattern, 'searchableColumns');
        $reflection->setAccessible(true);

        $columns = $reflection->invoke($pattern);

        $this->assertIsArray($columns);
        $this->assertContains('name', $columns);
        $this->assertContains('description', $columns);
        $this->assertContains('target', $columns);
        $this->assertContains('category', $columns);
    }
}
