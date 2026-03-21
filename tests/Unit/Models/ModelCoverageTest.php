<?php

namespace Aicl\Tests\Unit\Models;

use Aicl\Events\Enums\ActorType;
use Aicl\Models\DomainEventRecord;
use App\Models\User;
use Carbon\Carbon;
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
        /** @phpstan-ignore-next-line */
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
        /** @phpstan-ignore-next-line */
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
        /** @phpstan-ignore-next-line */
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
        /** @phpstan-ignore-next-line */
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
}
