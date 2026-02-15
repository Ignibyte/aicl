<?php

namespace Aicl\Tests\Unit\Models;

use Aicl\Enums\AnnotationCategory;
use Aicl\Enums\KnowledgeLinkRelationship;
use Aicl\Enums\ScoreType;
use Aicl\Events\Enums\ActorType;
use Aicl\Models\DomainEventRecord;
use Aicl\Models\GoldenAnnotation;
use Aicl\Models\KnowledgeLink;
use Aicl\Models\RlmScore;
use Aicl\Models\RlmSemanticCache;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ModelCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->user = User::factory()->create(['id' => 1]);
    }

    // =========================================================================
    // DomainEventRecord
    // =========================================================================

    public function test_domain_event_record_can_be_created(): void
    {
        $record = DomainEventRecord::create([
            'event_type' => 'order.created',
            'actor_type' => ActorType::User->value,
            'actor_id' => $this->user->id,
            'entity_type' => 'App\\Models\\User',
            'entity_id' => (string) $this->user->id,
            'payload' => ['key' => 'value'],
            'metadata' => ['source' => 'test'],
            'occurred_at' => now(),
        ]);

        $this->assertDatabaseHas('domain_events', [
            'id' => $record->id,
            'event_type' => 'order.created',
        ]);
    }

    public function test_domain_event_record_has_correct_fillable(): void
    {
        $model = new DomainEventRecord;

        $expected = [
            'event_type',
            'actor_type',
            'actor_id',
            'entity_type',
            'entity_id',
            'payload',
            'metadata',
            'occurred_at',
        ];

        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_domain_event_record_has_correct_casts(): void
    {
        $model = new DomainEventRecord;
        $casts = $model->getCasts();

        $this->assertEquals('array', $casts['payload']);
        $this->assertEquals('array', $casts['metadata']);
        $this->assertEquals('datetime', $casts['occurred_at']);
        $this->assertEquals('datetime', $casts['created_at']);
    }

    public function test_domain_event_record_uses_domain_events_table(): void
    {
        $model = new DomainEventRecord;

        $this->assertEquals('domain_events', $model->getTable());
    }

    public function test_domain_event_record_has_timestamps_disabled(): void
    {
        $model = new DomainEventRecord;

        $this->assertFalse($model->timestamps);
    }

    public function test_domain_event_record_sets_created_at_on_creating(): void
    {
        $record = DomainEventRecord::create([
            'event_type' => 'test.event',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->assertNotNull($record->created_at);
    }

    public function test_domain_event_record_scope_for_entity(): void
    {
        // Clear any records created by DomainEventSubscriber during setUp
        DomainEventRecord::query()->delete();

        DomainEventRecord::create([
            'event_type' => 'user.updated',
            'actor_type' => ActorType::User->value,
            'entity_type' => $this->user->getMorphClass(),
            'entity_id' => (string) $this->user->getKey(),
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        DomainEventRecord::create([
            'event_type' => 'other.event',
            'actor_type' => ActorType::System->value,
            'entity_type' => 'App\\Models\\Other',
            'entity_id' => '999',
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $results = DomainEventRecord::forEntity($this->user)->get();

        $this->assertCount(1, $results);
        $this->assertEquals('user.updated', $results->first()->event_type);
    }

    public function test_domain_event_record_scope_of_type_exact(): void
    {
        DomainEventRecord::create([
            'event_type' => 'order.created',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        DomainEventRecord::create([
            'event_type' => 'order.fulfilled',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $results = DomainEventRecord::ofType('order.created')->get();

        $this->assertCount(1, $results);
    }

    public function test_domain_event_record_scope_of_type_wildcard(): void
    {
        DomainEventRecord::create([
            'event_type' => 'order.created',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        DomainEventRecord::create([
            'event_type' => 'order.fulfilled',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        DomainEventRecord::create([
            'event_type' => 'user.created',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $results = DomainEventRecord::ofType('order.*')->get();

        $this->assertCount(2, $results);
    }

    public function test_domain_event_record_scope_since(): void
    {
        DomainEventRecord::query()->delete();

        DomainEventRecord::create([
            'event_type' => 'old.event',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now()->subDays(10),
        ]);

        DomainEventRecord::create([
            'event_type' => 'new.event',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now(),
        ]);

        $results = DomainEventRecord::since(Carbon::now()->subDays(5))->get();

        $this->assertCount(1, $results);
        $this->assertEquals('new.event', $results->first()->event_type);
    }

    public function test_domain_event_record_scope_between(): void
    {
        DomainEventRecord::query()->delete();

        DomainEventRecord::create([
            'event_type' => 'outside.event',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now()->subDays(20),
        ]);

        DomainEventRecord::create([
            'event_type' => 'inside.event',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now()->subDays(5),
        ]);

        $results = DomainEventRecord::between(
            Carbon::now()->subDays(10),
            Carbon::now()
        )->get();

        $this->assertCount(1, $results);
        $this->assertEquals('inside.event', $results->first()->event_type);
    }

    public function test_domain_event_record_scope_by_actor(): void
    {
        DomainEventRecord::create([
            'event_type' => 'user.action',
            'actor_type' => ActorType::User->value,
            'actor_id' => $this->user->id,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        DomainEventRecord::create([
            'event_type' => 'system.action',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $results = DomainEventRecord::byActor(ActorType::User)->get();
        $this->assertCount(1, $results);

        $results = DomainEventRecord::byActor(ActorType::User, $this->user->id)->get();
        $this->assertCount(1, $results);

        $results = DomainEventRecord::byActor(ActorType::User, 999)->get();
        $this->assertCount(0, $results);
    }

    public function test_domain_event_record_scope_timeline(): void
    {
        DomainEventRecord::query()->delete();

        DomainEventRecord::create([
            'event_type' => 'first.event',
            'actor_type' => ActorType::System->value,
            'entity_type' => $this->user->getMorphClass(),
            'entity_id' => (string) $this->user->getKey(),
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now()->subHour(),
        ]);

        DomainEventRecord::create([
            'event_type' => 'second.event',
            'actor_type' => ActorType::System->value,
            'entity_type' => $this->user->getMorphClass(),
            'entity_id' => (string) $this->user->getKey(),
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now(),
        ]);

        $results = DomainEventRecord::timeline($this->user)->get();

        $this->assertCount(2, $results);
        $this->assertEquals('second.event', $results->first()->event_type);
    }

    public function test_domain_event_record_prune_deletes_old_events(): void
    {
        DomainEventRecord::create([
            'event_type' => 'old.event',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now()->subDays(30),
        ]);

        DomainEventRecord::create([
            'event_type' => 'recent.event',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => Carbon::now(),
        ]);

        $deleted = DomainEventRecord::prune(Carbon::now()->subDays(7));

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseMissing('domain_events', ['event_type' => 'old.event']);
        $this->assertDatabaseHas('domain_events', ['event_type' => 'recent.event']);
    }

    public function test_domain_event_record_actor_type_enum_attribute(): void
    {
        $record = DomainEventRecord::create([
            'event_type' => 'test.event',
            'actor_type' => ActorType::Agent->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->assertEquals(ActorType::Agent, $record->actor_type_enum);
    }

    // =========================================================================
    // GoldenAnnotation
    // =========================================================================

    public function test_golden_annotation_factory_creates_valid_record(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('golden_annotations', [
            'id' => $annotation->id,
        ]);
    }

    public function test_golden_annotation_has_correct_fillable(): void
    {
        $model = new GoldenAnnotation;

        $expected = [
            'annotation_key',
            'file_path',
            'line_number',
            'annotation_text',
            'rationale',
            'feature_tags',
            'pattern_name',
            'category',
            'is_active',
            'owner_id',
        ];

        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_golden_annotation_has_correct_casts(): void
    {
        $model = new GoldenAnnotation;
        $casts = $model->getCasts();

        $this->assertEquals('integer', $casts['line_number']);
        $this->assertEquals('array', $casts['feature_tags']);
        $this->assertEquals(AnnotationCategory::class, $casts['category']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    public function test_golden_annotation_owner_relationship(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        $this->assertInstanceOf(BelongsTo::class, $annotation->owner());
        $this->assertEquals($this->user->id, $annotation->owner->id);
    }

    public function test_golden_annotation_source_links_relationship(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        $this->assertInstanceOf(MorphMany::class, $annotation->sourceLinks());
    }

    public function test_golden_annotation_target_links_relationship(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => $this->user->id]);

        $this->assertInstanceOf(MorphMany::class, $annotation->targetLinks());
    }

    public function test_golden_annotation_scope_active(): void
    {
        GoldenAnnotation::factory()->create(['owner_id' => 1, 'is_active' => true]);
        GoldenAnnotation::factory()->create(['owner_id' => 1, 'is_active' => false]);

        $results = GoldenAnnotation::active()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is_active);
    }

    public function test_golden_annotation_scope_for_file(): void
    {
        GoldenAnnotation::factory()->create(['owner_id' => 1, 'file_path' => 'app/Models/User.php']);
        GoldenAnnotation::factory()->create(['owner_id' => 1, 'file_path' => 'app/Models/Post.php']);

        $results = GoldenAnnotation::forFile('app/Models/User.php')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('app/Models/User.php', $results->first()->file_path);
    }

    public function test_golden_annotation_scope_in_category_with_enum(): void
    {
        GoldenAnnotation::factory()->create(['owner_id' => 1, 'category' => AnnotationCategory::Model]);
        GoldenAnnotation::factory()->create(['owner_id' => 1, 'category' => AnnotationCategory::Migration]);

        $results = GoldenAnnotation::inCategory(AnnotationCategory::Model)->get();

        $this->assertCount(1, $results);
        $this->assertEquals(AnnotationCategory::Model, $results->first()->category);
    }

    public function test_golden_annotation_scope_in_category_with_string(): void
    {
        GoldenAnnotation::factory()->create(['owner_id' => 1, 'category' => AnnotationCategory::Api]);

        $results = GoldenAnnotation::inCategory('api')->get();

        $this->assertCount(1, $results);
    }

    public function test_golden_annotation_scope_with_feature_tag(): void
    {
        GoldenAnnotation::factory()->withFeatureTags(['media', 'search'])->create(['owner_id' => 1]);
        GoldenAnnotation::factory()->withFeatureTags(['audit'])->create(['owner_id' => 1]);

        $results = GoldenAnnotation::withFeatureTag('media')->get();

        $this->assertCount(1, $results);
    }

    public function test_golden_annotation_scope_with_any_feature_tag(): void
    {
        GoldenAnnotation::factory()->withFeatureTags(['media'])->create(['owner_id' => 1]);
        GoldenAnnotation::factory()->withFeatureTags(['search'])->create(['owner_id' => 1]);
        GoldenAnnotation::factory()->withFeatureTags(['audit'])->create(['owner_id' => 1]);

        $results = GoldenAnnotation::withAnyFeatureTag(['media', 'search'])->get();

        $this->assertCount(2, $results);
    }

    public function test_golden_annotation_scope_for_pattern(): void
    {
        GoldenAnnotation::factory()->forPattern('model-fillable')->create(['owner_id' => 1]);
        GoldenAnnotation::factory()->forPattern('other-pattern')->create(['owner_id' => 1]);

        $results = GoldenAnnotation::forPattern('model-fillable')->get();

        $this->assertCount(1, $results);
    }

    public function test_golden_annotation_soft_deletes(): void
    {
        $annotation = GoldenAnnotation::factory()->create(['owner_id' => 1]);
        $annotation->delete();

        $this->assertSoftDeleted('golden_annotations', ['id' => $annotation->id]);
        $this->assertDatabaseHas('golden_annotations', ['id' => $annotation->id]);
    }

    // =========================================================================
    // KnowledgeLink
    // =========================================================================

    public function test_knowledge_link_can_be_created(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => 1]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        $link = KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::RelatedTo,
            'confidence' => 0.85,
        ]);

        $this->assertDatabaseHas('knowledge_links', [
            'id' => $link->id,
            'relationship' => 'related_to',
        ]);
    }

    public function test_knowledge_link_has_correct_fillable(): void
    {
        $model = new KnowledgeLink;

        $expected = [
            'source_type',
            'source_id',
            'target_type',
            'target_id',
            'relationship',
            'confidence',
        ];

        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_knowledge_link_has_correct_casts(): void
    {
        $model = new KnowledgeLink;
        $casts = $model->getCasts();

        $this->assertEquals(KnowledgeLinkRelationship::class, $casts['relationship']);
        $this->assertStringContainsString('decimal', $casts['confidence']);
    }

    public function test_knowledge_link_source_relationship(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => 1]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        $link = KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::LearnedFrom,
            'confidence' => 0.90,
        ]);

        $this->assertInstanceOf(MorphTo::class, $link->source());
        $this->assertEquals($source->id, $link->source->id);
    }

    public function test_knowledge_link_target_relationship(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => 1]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        $link = KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::Prevents,
            'confidence' => 0.75,
        ]);

        $this->assertInstanceOf(MorphTo::class, $link->target());
        $this->assertEquals($target->id, $link->target->id);
    }

    public function test_knowledge_link_scope_of_relationship(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => 1]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::ViolatedBy,
            'confidence' => 0.80,
        ]);

        KnowledgeLink::create([
            'source_type' => $target->getMorphClass(),
            'source_id' => $target->id,
            'target_type' => $source->getMorphClass(),
            'target_id' => $source->id,
            'relationship' => KnowledgeLinkRelationship::Prevents,
            'confidence' => 0.70,
        ]);

        $results = KnowledgeLink::ofRelationship(KnowledgeLinkRelationship::ViolatedBy)->get();
        $this->assertCount(1, $results);
    }

    public function test_knowledge_link_scope_high_confidence(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => 1]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::RelatedTo,
            'confidence' => 0.90,
        ]);

        KnowledgeLink::create([
            'source_type' => $target->getMorphClass(),
            'source_id' => $target->id,
            'target_type' => $source->getMorphClass(),
            'target_id' => $source->id,
            'relationship' => KnowledgeLinkRelationship::RelatedTo,
            'confidence' => 0.50,
        ]);

        $results = KnowledgeLink::highConfidence(0.7)->get();
        $this->assertCount(1, $results);

        $allAboveHalf = KnowledgeLink::highConfidence(0.5)->get();
        $this->assertCount(2, $allAboveHalf);
    }

    public function test_knowledge_link_scope_for_source(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => 1]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::RelatedTo,
            'confidence' => 0.80,
        ]);

        $results = KnowledgeLink::forSource($source)->get();
        $this->assertCount(1, $results);

        $results = KnowledgeLink::forSource($target)->get();
        $this->assertCount(0, $results);
    }

    public function test_knowledge_link_scope_for_target(): void
    {
        $source = GoldenAnnotation::factory()->create(['owner_id' => 1]);
        $target = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        KnowledgeLink::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->id,
            'relationship' => KnowledgeLinkRelationship::DerivedFrom,
            'confidence' => 0.80,
        ]);

        $results = KnowledgeLink::forTarget($target)->get();
        $this->assertCount(1, $results);

        $results = KnowledgeLink::forTarget($source)->get();
        $this->assertCount(0, $results);
    }

    public function test_knowledge_link_scope_involving(): void
    {
        $a = GoldenAnnotation::factory()->create(['owner_id' => 1]);
        $b = GoldenAnnotation::factory()->create(['owner_id' => 1]);
        $c = GoldenAnnotation::factory()->create(['owner_id' => 1]);

        KnowledgeLink::create([
            'source_type' => $a->getMorphClass(),
            'source_id' => $a->id,
            'target_type' => $b->getMorphClass(),
            'target_id' => $b->id,
            'relationship' => KnowledgeLinkRelationship::RelatedTo,
            'confidence' => 0.80,
        ]);

        KnowledgeLink::create([
            'source_type' => $b->getMorphClass(),
            'source_id' => $b->id,
            'target_type' => $c->getMorphClass(),
            'target_id' => $c->id,
            'relationship' => KnowledgeLinkRelationship::RelatedTo,
            'confidence' => 0.70,
        ]);

        // Model B is involved in both links (as source and target)
        $results = KnowledgeLink::involving($b)->get();
        $this->assertCount(2, $results);

        // Model A is only in one link (as source)
        $results = KnowledgeLink::involving($a)->get();
        $this->assertCount(1, $results);
    }

    // =========================================================================
    // RlmScore
    // =========================================================================

    public function test_rlm_score_factory_creates_valid_record(): void
    {
        $score = RlmScore::factory()->create(['owner_id' => 1]);

        $this->assertDatabaseHas('rlm_scores', [
            'id' => $score->id,
        ]);
    }

    public function test_rlm_score_has_correct_fillable(): void
    {
        $model = new RlmScore;

        $expected = [
            'entity_name',
            'score_type',
            'passed',
            'total',
            'percentage',
            'errors',
            'warnings',
            'details',
            'owner_id',
        ];

        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_rlm_score_has_correct_casts(): void
    {
        $model = new RlmScore;
        $casts = $model->getCasts();

        $this->assertEquals(ScoreType::class, $casts['score_type']);
        $this->assertEquals('integer', $casts['passed']);
        $this->assertEquals('integer', $casts['total']);
        $this->assertStringContainsString('decimal', $casts['percentage']);
        $this->assertEquals('integer', $casts['errors']);
        $this->assertEquals('integer', $casts['warnings']);
        $this->assertEquals('array', $casts['details']);
    }

    public function test_rlm_score_owner_relationship(): void
    {
        $score = RlmScore::factory()->create(['owner_id' => $this->user->id]);

        $this->assertInstanceOf(BelongsTo::class, $score->owner());
        $this->assertEquals($this->user->id, $score->owner->id);
    }

    public function test_rlm_score_scope_for_entity(): void
    {
        RlmScore::factory()->forEntity('Invoice')->create(['owner_id' => 1]);
        RlmScore::factory()->forEntity('Order')->create(['owner_id' => 1]);

        $results = RlmScore::forEntity('Invoice')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Invoice', $results->first()->entity_name);
    }

    public function test_rlm_score_scope_of_type_with_enum(): void
    {
        RlmScore::factory()->structural()->create(['owner_id' => 1]);
        RlmScore::factory()->semantic()->create(['owner_id' => 1]);

        $results = RlmScore::ofType(ScoreType::Structural)->get();

        $this->assertCount(1, $results);
        $this->assertEquals(ScoreType::Structural, $results->first()->score_type);
    }

    public function test_rlm_score_scope_of_type_with_string(): void
    {
        RlmScore::factory()->combined()->create(['owner_id' => 1]);
        RlmScore::factory()->structural()->create(['owner_id' => 1]);

        $results = RlmScore::ofType('combined')->get();

        $this->assertCount(1, $results);
    }

    public function test_rlm_score_scope_perfect(): void
    {
        RlmScore::factory()->create([
            'owner_id' => 1,
            'passed' => 42,
            'total' => 42,
            'percentage' => 100.00,
        ]);

        RlmScore::factory()->create([
            'owner_id' => 1,
            'passed' => 35,
            'total' => 42,
            'percentage' => 83.33,
        ]);

        $results = RlmScore::perfect()->get();

        $this->assertCount(1, $results);
        $this->assertEquals(42, $results->first()->passed);
        $this->assertEquals(42, $results->first()->total);
    }

    public function test_rlm_score_soft_deletes(): void
    {
        $score = RlmScore::factory()->create(['owner_id' => 1]);
        $score->delete();

        $this->assertSoftDeleted('rlm_scores', ['id' => $score->id]);
        $this->assertDatabaseHas('rlm_scores', ['id' => $score->id]);
    }

    public function test_rlm_score_with_details_cast(): void
    {
        $score = RlmScore::factory()->withDetails()->create(['owner_id' => 1]);

        $this->assertIsArray($score->details);
        $this->assertArrayHasKey('pattern', $score->details[0]);
    }

    // =========================================================================
    // RlmSemanticCache
    // =========================================================================

    public function test_rlm_semantic_cache_can_be_created(): void
    {
        $cache = RlmSemanticCache::create([
            'cache_key' => 'test-cache-key-'.uniqid(),
            'check_name' => 'model.fillable',
            'entity_name' => 'Invoice',
            'passed' => true,
            'message' => 'All checks passed',
            'confidence' => 0.95,
            'files_hash' => hash('sha256', 'test-content'),
            'expires_at' => now()->addHour(),
        ]);

        $this->assertDatabaseHas('rlm_semantic_cache', [
            'id' => $cache->id,
            'entity_name' => 'Invoice',
        ]);
    }

    public function test_rlm_semantic_cache_has_correct_fillable(): void
    {
        $model = new RlmSemanticCache;

        $expected = [
            'cache_key',
            'check_name',
            'entity_name',
            'passed',
            'message',
            'confidence',
            'files_hash',
            'expires_at',
        ];

        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_rlm_semantic_cache_has_correct_casts(): void
    {
        $model = new RlmSemanticCache;
        $casts = $model->getCasts();

        $this->assertEquals('boolean', $casts['passed']);
        $this->assertStringContainsString('decimal', $casts['confidence']);
        $this->assertEquals('datetime', $casts['expires_at']);
    }

    public function test_rlm_semantic_cache_uses_correct_table(): void
    {
        $model = new RlmSemanticCache;

        $this->assertEquals('rlm_semantic_cache', $model->getTable());
    }

    public function test_rlm_semantic_cache_boolean_cast_works(): void
    {
        $cache = RlmSemanticCache::create([
            'cache_key' => 'bool-test-'.uniqid(),
            'check_name' => 'model.uuid',
            'entity_name' => 'Order',
            'passed' => false,
            'message' => 'Check failed',
            'confidence' => 0.60,
            'files_hash' => hash('sha256', 'content'),
            'expires_at' => now()->addHour(),
        ]);

        $fresh = $cache->fresh();
        $this->assertFalse($fresh->passed);
    }

    public function test_rlm_semantic_cache_datetime_cast_for_expires_at(): void
    {
        $expiresAt = now()->addHours(2);

        $cache = RlmSemanticCache::create([
            'cache_key' => 'datetime-test-'.uniqid(),
            'check_name' => 'model.traits',
            'entity_name' => 'Product',
            'passed' => true,
            'message' => 'OK',
            'confidence' => 1.00,
            'files_hash' => hash('sha256', 'test'),
            'expires_at' => $expiresAt,
        ]);

        $fresh = $cache->fresh();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->expires_at);
    }
}
