<?php

namespace Aicl\Tests\Unit\Traits;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityCreating;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityDeleting;
use Aicl\Events\EntityUpdated;
use Aicl\Events\EntityUpdating;
use Aicl\Jobs\GenerateEmbeddingJob;
use Aicl\Models\FailureReport;
use Aicl\Models\RlmFailure;
use Aicl\Traits\PaginatesApiRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Tests\TestCase;

class TraitGapCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Hub models hardcode owner_id => 1
        $this->owner = User::factory()->create(['id' => 1]);

        // Prevent embedding jobs from dispatching during setup
        Queue::fake();
    }

    // ========================================================================
    // HasEntityEvents — dispatches typed entity lifecycle events (RlmFailure)
    // ========================================================================

    public function test_creating_rlm_failure_dispatches_entity_creating_event(): void
    {
        Event::fake([EntityCreating::class, EntityCreated::class]);

        RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        Event::assertDispatched(EntityCreating::class, function ($event) {
            return $event->entity instanceof RlmFailure;
        });
    }

    public function test_creating_rlm_failure_dispatches_entity_created_event(): void
    {
        Event::fake([EntityCreating::class, EntityCreated::class]);

        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        Event::assertDispatched(EntityCreated::class, function ($event) use ($failure) {
            return $event->entity instanceof RlmFailure
                && $event->entity->getKey() === $failure->getKey();
        });
    }

    public function test_updating_rlm_failure_dispatches_entity_updating_event(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        Event::fake([EntityUpdating::class, EntityUpdated::class]);

        $failure->update(['title' => 'Updated Failure Title']);

        Event::assertDispatched(EntityUpdating::class, function ($event) use ($failure) {
            return $event->entity->getKey() === $failure->getKey();
        });
    }

    public function test_updating_rlm_failure_dispatches_entity_updated_event(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        Event::fake([EntityUpdating::class, EntityUpdated::class]);

        $failure->update(['title' => 'Updated Failure Title']);

        Event::assertDispatched(EntityUpdated::class, function ($event) use ($failure) {
            return $event->entity->getKey() === $failure->getKey();
        });
    }

    public function test_deleting_rlm_failure_dispatches_entity_deleting_event(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        Event::fake([EntityDeleting::class, EntityDeleted::class]);

        $failure->delete();

        Event::assertDispatched(EntityDeleting::class, function ($event) use ($failure) {
            return $event->entity->getKey() === $failure->getKey();
        });
    }

    public function test_deleting_rlm_failure_dispatches_entity_deleted_event(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        Event::fake([EntityDeleting::class, EntityDeleted::class]);

        $failure->delete();

        Event::assertDispatched(EntityDeleted::class);
    }

    public function test_creating_failure_report_dispatches_entity_creating_event(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        Event::fake([EntityCreating::class, EntityCreated::class]);

        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
        ]);

        Event::assertDispatched(EntityCreating::class, function ($event) {
            return $event->entity instanceof FailureReport;
        });
    }

    public function test_creating_failure_report_dispatches_entity_created_event(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        Event::fake([EntityCreating::class, EntityCreated::class]);

        $report = FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
        ]);

        Event::assertDispatched(EntityCreated::class, function ($event) use ($report) {
            return $event->entity instanceof FailureReport
                && $event->entity->getKey() === $report->getKey();
        });
    }

    // ========================================================================
    // HasStandardScopes — active, inactive, recent, byUser, search (FailureReport)
    // ========================================================================

    public function test_scope_active_on_failure_report(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'is_active' => true,
        ]);
        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'is_active' => false,
        ]);

        $active = FailureReport::active()->get();

        $this->assertCount(1, $active);
        $this->assertTrue($active->first()->is_active);
    }

    public function test_scope_inactive_on_failure_report(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'is_active' => true,
        ]);
        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'is_active' => false,
        ]);

        $inactive = FailureReport::inactive()->get();

        $this->assertCount(1, $inactive);
        $this->assertFalse($inactive->first()->is_active);
    }

    public function test_scope_recent_on_failure_report_with_default_days(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'created_at' => now()->subDays(10),
        ]);
        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'created_at' => now()->subDays(45),
        ]);

        $recent = FailureReport::recent()->get();

        $this->assertCount(1, $recent);
    }

    public function test_scope_recent_on_failure_report_with_custom_days(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'created_at' => now()->subDays(5),
        ]);
        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'created_at' => now()->subDays(15),
        ]);

        $recent = FailureReport::recent(7)->get();

        $this->assertCount(1, $recent);
    }

    public function test_scope_by_user_with_owner_id_on_failure_report(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);
        $otherUser = User::factory()->create(['id' => 2]);

        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
        ]);
        FailureReport::factory()->create([
            'owner_id' => $otherUser->id,
            'rlm_failure_id' => $failure->id,
        ]);

        // FailureReport does not have 'created_by' fillable, so byUser falls back to user_id
        // We verify the SQL references user_id (owner_id is stored via owner_id column, not user_id)
        $query = FailureReport::byUser($this->owner->id)->toRawSql();

        $this->assertStringContainsString('"user_id"', $query);
    }

    public function test_scope_by_user_accepts_model_instance(): void
    {
        $query = FailureReport::byUser($this->owner)->toRawSql();

        $this->assertStringContainsString('"user_id"', $query);
        $this->assertStringContainsString((string) $this->owner->id, $query);
    }

    public function test_scope_search_on_failure_report_by_entity_name(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'entity_name' => 'UniqueInvoiceEntity',
        ]);
        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'entity_name' => 'OtherEntity',
        ]);

        $results = FailureReport::search('UniqueInvoice')->get();

        $this->assertCount(1, $results);
        $this->assertSame('UniqueInvoiceEntity', $results->first()->entity_name);
    }

    public function test_scope_search_on_failure_report_is_case_insensitive(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'entity_name' => 'CamelCaseEntity',
        ]);

        $results = FailureReport::search('camelcaseentity')->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_search_on_failure_report_by_phase(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'phase' => 'Phase 99: UniquePhase',
        ]);
        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'phase' => 'Phase 1: Plan',
        ]);

        $results = FailureReport::search('UniquePhase')->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_search_on_failure_report_returns_empty_when_no_match(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'entity_name' => 'SomeEntity',
        ]);

        $results = FailureReport::search('nonexistent_xyz_999')->get();

        $this->assertCount(0, $results);
    }

    public function test_failure_report_searchable_columns_override(): void
    {
        $report = new FailureReport;

        $reflection = new \ReflectionMethod($report, 'searchableColumns');
        $reflection->setAccessible(true);

        $columns = $reflection->invoke($report);

        $this->assertIsArray($columns);
        $this->assertContains('entity_name', $columns);
        $this->assertContains('project_hash', $columns);
        $this->assertContains('phase', $columns);
        $this->assertContains('agent', $columns);
        // Should NOT have the defaults since it's overridden
        $this->assertNotContains('name', $columns);
        $this->assertNotContains('title', $columns);
    }

    public function test_scope_active_combined_with_recent_on_failure_report(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        // Active and recent
        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'is_active' => true,
            'created_at' => now()->subDays(5),
        ]);

        // Active but old
        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'is_active' => true,
            'created_at' => now()->subDays(60),
        ]);

        // Inactive and recent
        FailureReport::factory()->create([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
            'is_active' => false,
            'created_at' => now()->subDays(5),
        ]);

        $results = FailureReport::active()->recent(30)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is_active);
    }

    public function test_scope_active_on_rlm_failure(): void
    {
        RlmFailure::factory()->create(['owner_id' => $this->owner->id, 'is_active' => true]);
        RlmFailure::factory()->create(['owner_id' => $this->owner->id, 'is_active' => false]);

        $active = RlmFailure::active()->get();

        $this->assertCount(1, $active);
        $this->assertTrue($active->first()->is_active);
    }

    public function test_scope_inactive_on_rlm_failure(): void
    {
        RlmFailure::factory()->create(['owner_id' => $this->owner->id, 'is_active' => true]);
        RlmFailure::factory()->create(['owner_id' => $this->owner->id, 'is_active' => false]);

        $inactive = RlmFailure::inactive()->get();

        $this->assertCount(1, $inactive);
        $this->assertFalse($inactive->first()->is_active);
    }

    public function test_scope_search_on_rlm_failure_by_title(): void
    {
        RlmFailure::factory()->create([
            'owner_id' => $this->owner->id,
            'title' => 'UniqueXyzFailureTitle',
        ]);
        RlmFailure::factory()->create([
            'owner_id' => $this->owner->id,
            'title' => 'Other Failure',
        ]);

        $results = RlmFailure::search('UniqueXyzFailure')->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_search_on_rlm_failure_by_description(): void
    {
        RlmFailure::factory()->create([
            'owner_id' => $this->owner->id,
            'title' => 'Plain Title',
            'description' => 'UniqueSearchableDesc789',
        ]);

        $results = RlmFailure::search('UniqueSearchableDesc789')->get();

        $this->assertCount(1, $results);
    }

    public function test_rlm_failure_searchable_columns_override(): void
    {
        $failure = new RlmFailure;

        $reflection = new \ReflectionMethod($failure, 'searchableColumns');
        $reflection->setAccessible(true);

        $columns = $reflection->invoke($failure);

        $this->assertContains('title', $columns);
        $this->assertContains('description', $columns);
        $this->assertContains('failure_code', $columns);
        $this->assertContains('category', $columns);
    }

    // ========================================================================
    // PaginatesApiRequests — clamped per_page values
    // ========================================================================

    public function test_get_per_page_returns_default_when_no_param(): void
    {
        $consumer = $this->createPaginationConsumer();
        $request = Request::create('/api/test', 'GET');

        $result = $consumer->test_get_per_page($request);

        $this->assertSame(15, $result);
    }

    public function test_get_per_page_uses_explicit_value(): void
    {
        $consumer = $this->createPaginationConsumer();
        $request = Request::create('/api/test', 'GET', ['per_page' => 25]);

        $result = $consumer->test_get_per_page($request);

        $this->assertSame(25, $result);
    }

    public function test_get_per_page_caps_at_max(): void
    {
        $consumer = $this->createPaginationConsumer();
        $request = Request::create('/api/test', 'GET', ['per_page' => 500]);

        $result = $consumer->test_get_per_page($request);

        $this->assertSame(100, $result);
    }

    public function test_get_per_page_clamps_below_one_to_one(): void
    {
        $consumer = $this->createPaginationConsumer();
        $request = Request::create('/api/test', 'GET', ['per_page' => 0]);

        $result = $consumer->test_get_per_page($request);

        $this->assertSame(1, $result);
    }

    public function test_get_per_page_clamps_negative_to_one(): void
    {
        $consumer = $this->createPaginationConsumer();
        $request = Request::create('/api/test', 'GET', ['per_page' => -10]);

        $result = $consumer->test_get_per_page($request);

        $this->assertSame(1, $result);
    }

    public function test_get_per_page_with_custom_default(): void
    {
        $consumer = $this->createPaginationConsumer();
        $request = Request::create('/api/test', 'GET');

        $result = $consumer->test_get_per_page($request, 50);

        $this->assertSame(50, $result);
    }

    public function test_get_per_page_with_custom_max(): void
    {
        $consumer = $this->createPaginationConsumer();
        $request = Request::create('/api/test', 'GET', ['per_page' => 75]);

        $result = $consumer->test_get_per_page($request, 15, 50);

        $this->assertSame(50, $result);
    }

    public function test_get_per_page_value_equals_max(): void
    {
        $consumer = $this->createPaginationConsumer();
        $request = Request::create('/api/test', 'GET', ['per_page' => 100]);

        $result = $consumer->test_get_per_page($request);

        $this->assertSame(100, $result);
    }

    public function test_get_per_page_value_equals_one(): void
    {
        $consumer = $this->createPaginationConsumer();
        $request = Request::create('/api/test', 'GET', ['per_page' => 1]);

        $result = $consumer->test_get_per_page($request);

        $this->assertSame(1, $result);
    }

    public function test_get_per_page_with_non_numeric_value(): void
    {
        $consumer = $this->createPaginationConsumer();
        $request = Request::create('/api/test', 'GET', ['per_page' => 'abc']);

        // Request::integer('per_page', 15) returns 0 for non-numeric, clamped to 1
        $result = $consumer->test_get_per_page($request);

        $this->assertSame(1, $result);
    }

    // ========================================================================
    // HasAuditTrail — LogOptions via RlmFailure
    // ========================================================================

    public function test_rlm_failure_uses_logs_activity_trait(): void
    {
        $this->assertContains(
            LogsActivity::class,
            class_uses_recursive(RlmFailure::class),
        );
    }

    public function test_failure_report_uses_logs_activity_trait(): void
    {
        $this->assertContains(
            LogsActivity::class,
            class_uses_recursive(FailureReport::class),
        );
    }

    public function test_rlm_failure_get_activity_log_options_returns_log_options(): void
    {
        $failure = RlmFailure::factory()->make(['owner_id' => $this->owner->id]);

        $options = $failure->getActivitylogOptions();

        $this->assertInstanceOf(LogOptions::class, $options);
    }

    public function test_rlm_failure_activity_log_options_logs_all_attributes(): void
    {
        $failure = RlmFailure::factory()->make(['owner_id' => $this->owner->id]);

        $options = $failure->getActivitylogOptions();

        $this->assertSame(['*'], $options->logAttributes);
    }

    public function test_rlm_failure_activity_log_options_logs_only_dirty(): void
    {
        $failure = RlmFailure::factory()->make(['owner_id' => $this->owner->id]);

        $options = $failure->getActivitylogOptions();

        $this->assertTrue($options->logOnlyDirty);
    }

    public function test_rlm_failure_activity_log_options_skips_empty_logs(): void
    {
        $failure = RlmFailure::factory()->make(['owner_id' => $this->owner->id]);

        $options = $failure->getActivitylogOptions();

        $this->assertFalse($options->submitEmptyLogs);
    }

    public function test_rlm_failure_activity_log_description_format(): void
    {
        $failure = RlmFailure::factory()->make(['owner_id' => $this->owner->id]);

        $options = $failure->getActivitylogOptions();

        $description = $options->descriptionForEvent;
        $this->assertNotNull($description);

        $this->assertSame('RlmFailure was created', $description('created'));
        $this->assertSame('RlmFailure was updated', $description('updated'));
        $this->assertSame('RlmFailure was deleted', $description('deleted'));
    }

    public function test_failure_report_activity_log_description_format(): void
    {
        $failure = RlmFailure::factory()->make(['owner_id' => $this->owner->id]);
        $report = FailureReport::factory()->make([
            'owner_id' => $this->owner->id,
            'rlm_failure_id' => $failure->id,
        ]);

        $options = $report->getActivitylogOptions();

        $description = $options->descriptionForEvent;
        $this->assertNotNull($description);

        $this->assertSame('FailureReport was created', $description('created'));
    }

    // ========================================================================
    // HasEmbeddings — via RlmFailure (dispatch, cache, feature flag)
    // ========================================================================

    public function test_rlm_failure_embedding_text_concatenates_fields(): void
    {
        $failure = RlmFailure::factory()->make([
            'owner_id' => $this->owner->id,
            'title' => 'Test Failure Title',
            'description' => 'Detailed failure description',
            'root_cause' => 'Root cause analysis',
            'preventive_rule' => 'Always validate inputs',
        ]);

        $text = $failure->embeddingText();

        $this->assertStringContainsString('Test Failure Title', $text);
        $this->assertStringContainsString('Detailed failure description', $text);
        $this->assertStringContainsString('Root cause analysis', $text);
        $this->assertStringContainsString('Always validate inputs', $text);
    }

    public function test_rlm_failure_embedding_text_filters_null_fields(): void
    {
        $failure = RlmFailure::factory()->make([
            'owner_id' => $this->owner->id,
            'title' => 'Only Title',
            'description' => 'Only Description',
            'root_cause' => null,
            'preventive_rule' => null,
        ]);

        $text = $failure->embeddingText();

        $this->assertStringContainsString('Only Title', $text);
        $this->assertStringContainsString('Only Description', $text);
        // Null fields should be filtered out, no extra whitespace issues
        $this->assertNotEmpty($text);
    }

    public function test_dispatch_embedding_job_on_rlm_failure(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        config(['aicl.features.rlm_search' => true]);

        Queue::fake();

        $failure->dispatchEmbeddingJob();

        Queue::assertPushed(GenerateEmbeddingJob::class, function ($job) use ($failure) {
            return $job->model->is($failure);
        });
    }

    public function test_dispatch_embedding_job_skips_when_feature_disabled(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        config(['aicl.features.rlm_search' => false]);

        Queue::fake();

        $failure->dispatchEmbeddingJob();

        Queue::assertNotPushed(GenerateEmbeddingJob::class);
    }

    public function test_cache_embedding_stores_vector_for_rlm_failure(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        $embedding = [0.1, 0.2, 0.3, 0.4, 0.5];

        $failure->cacheEmbedding($embedding);

        $cacheKey = 'embedding:'.RlmFailure::class.':'.$failure->getKey();
        $this->assertSame($embedding, Cache::get($cacheKey));
    }

    public function test_get_cached_embedding_returns_stored_vector_for_rlm_failure(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        $embedding = [0.5, 0.6, 0.7];
        $failure->cacheEmbedding($embedding);

        $result = $failure->getCachedEmbedding();

        $this->assertSame($embedding, $result);
    }

    public function test_get_cached_embedding_returns_null_when_not_cached_for_rlm_failure(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        $result = $failure->getCachedEmbedding();

        $this->assertNull($result);
    }

    public function test_clear_cached_embedding_removes_from_cache_for_rlm_failure(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        $failure->cacheEmbedding([0.1, 0.2]);
        $this->assertNotNull($failure->getCachedEmbedding());

        $failure->clearCachedEmbedding();

        $this->assertNull($failure->getCachedEmbedding());
    }

    public function test_cache_embedding_uses_correct_key_format(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        $embedding = [1.0, 2.0, 3.0];
        $failure->cacheEmbedding($embedding);

        $expectedKey = 'embedding:'.RlmFailure::class.':'.$failure->getKey();

        $this->assertTrue(Cache::has($expectedKey));
    }

    public function test_cache_embedding_overwrites_previous_value(): void
    {
        $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);

        $failure->cacheEmbedding([0.1, 0.2]);
        $failure->cacheEmbedding([0.9, 0.8]);

        $result = $failure->getCachedEmbedding();

        $this->assertSame([0.9, 0.8], $result);
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    /**
     * Create an anonymous class that uses PaginatesApiRequests and
     * exposes the protected getPerPage method for testing.
     */
    private function createPaginationConsumer(): object
    {
        return new class
        {
            use PaginatesApiRequests;

            public function test_get_per_page(Request $request, int $default = 15, int $max = 100): int
            {
                return $this->getPerPage($request, $default, $max);
            }
        };
    }
}
