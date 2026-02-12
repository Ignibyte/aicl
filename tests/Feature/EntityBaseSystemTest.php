<?php

namespace Aicl\Tests\Feature;

use Aicl\Contracts\Auditable;
use Aicl\Contracts\HasEntityLifecycle;
use Aicl\Contracts\Searchable;
use Aicl\Contracts\Taggable;
use Aicl\Events\EntityCreated;
use Aicl\Events\EntityCreating;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityDeleting;
use Aicl\Events\EntityUpdated;
use Aicl\Events\EntityUpdating;
use Aicl\Observers\BaseObserver;
use Aicl\Traits\HasAuditTrail;
use Aicl\Traits\HasEntityEvents;
use Aicl\Traits\HasStandardScopes;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EntityBaseSystemTest extends TestCase
{
    use RefreshDatabase;

    // ─── HasEntityEvents trait tests ───────────────────────────

    public function test_entity_creating_event_is_dispatched(): void
    {
        Event::fake([EntityCreating::class]);

        User::factory()->create();

        Event::assertDispatched(EntityCreating::class);
    }

    public function test_entity_created_event_is_dispatched(): void
    {
        Event::fake([EntityCreated::class]);

        User::factory()->create();

        Event::assertDispatched(EntityCreated::class);
    }

    public function test_entity_updating_event_is_dispatched(): void
    {
        $user = User::factory()->create();

        Event::fake([EntityUpdating::class]);

        $user->update(['name' => 'Updated Name']);

        Event::assertDispatched(EntityUpdating::class);
    }

    public function test_entity_updated_event_is_dispatched(): void
    {
        $user = User::factory()->create();

        Event::fake([EntityUpdated::class]);

        $user->update(['name' => 'Updated Name']);

        Event::assertDispatched(EntityUpdated::class);
    }

    public function test_entity_deleting_event_is_dispatched(): void
    {
        $user = User::factory()->create();

        Event::fake([EntityDeleting::class]);

        $user->delete();

        Event::assertDispatched(EntityDeleting::class);
    }

    public function test_entity_deleted_event_is_dispatched(): void
    {
        $user = User::factory()->create();

        Event::fake([EntityDeleted::class]);

        $user->delete();

        Event::assertDispatched(EntityDeleted::class);
    }

    public function test_entity_events_carry_the_model_instance(): void
    {
        Event::fake([EntityCreated::class]);

        $user = User::factory()->create();

        Event::assertDispatched(EntityCreated::class, function (EntityCreated $event) use ($user): bool {
            return $event->entity->is($user);
        });
    }

    // ─── HasAuditTrail trait tests ─────────────────────────────

    public function test_audit_trail_logs_creation(): void
    {
        $user = User::factory()->create();

        $activity = Activity::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('User was created', $activity->description);
    }

    public function test_audit_trail_logs_update_with_changes(): void
    {
        $user = User::factory()->create(['name' => 'Original']);

        $user->update(['name' => 'Updated']);

        $activity = Activity::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('User was updated', $activity->description);
    }

    public function test_audit_trail_skips_empty_changelog(): void
    {
        $user = User::factory()->create(['name' => 'Same']);

        // Touch the model without actual changes
        $user->name = 'Same';
        $user->save();

        $updateActivities = Activity::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'updated')
            ->count();

        $this->assertEquals(0, $updateActivities);
    }

    public function test_audit_trail_logs_deletion(): void
    {
        $user = User::factory()->create();
        $userId = $user->id;

        $user->delete();

        $activity = Activity::where('subject_type', User::class)
            ->where('subject_id', $userId)
            ->where('event', 'deleted')
            ->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('User was deleted', $activity->description);
    }

    // ─── HasStandardScopes trait tests ─────────────────────────

    public function test_recent_scope_returns_recent_records(): void
    {
        $recent = User::factory()->create(['created_at' => now()]);
        $old = User::factory()->create(['created_at' => now()->subDays(60)]);

        $results = User::recent(30)->get();

        $this->assertTrue($results->contains($recent));
        $this->assertFalse($results->contains($old));
    }

    public function test_recent_scope_defaults_to_30_days(): void
    {
        $recent = User::factory()->create(['created_at' => now()->subDays(15)]);
        $old = User::factory()->create(['created_at' => now()->subDays(45)]);

        $results = User::recent()->get();

        $this->assertTrue($results->contains($recent));
        $this->assertFalse($results->contains($old));
    }

    public function test_search_scope_finds_matching_records(): void
    {
        $match = User::factory()->create(['name' => 'Zyxwvut Unique']);
        $noMatch = User::factory()->create(['name' => 'Abcdefg Other']);

        $results = User::search('Zyxwvut')->get();

        $this->assertTrue($results->contains($match));
        $this->assertFalse($results->contains($noMatch));
    }

    public function test_search_scope_is_case_insensitive_with_like(): void
    {
        $user = User::factory()->create(['name' => 'UPPERCASE NAME']);

        $results = User::search('uppercase')->get();

        $this->assertTrue($results->contains($user));
    }

    // ─── BaseObserver tests ────────────────────────────────────

    public function test_base_observer_can_be_extended(): void
    {
        $observer = new class extends BaseObserver
        {
            public bool $createdCalled = false;

            public function created(Model $model): void
            {
                $this->createdCalled = true;
            }
        };

        $user = User::factory()->make();
        $observer->created($user);

        $this->assertTrue($observer->createdCalled);
    }

    public function test_base_observer_has_all_lifecycle_methods(): void
    {
        $observer = new class extends BaseObserver {};

        $methods = [
            'creating', 'created',
            'updating', 'updated',
            'saving', 'saved',
            'deleting', 'deleted',
            'restoring', 'restored',
            'forceDeleting', 'forceDeleted',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($observer, $method),
                "BaseObserver is missing method: {$method}"
            );
        }
    }

    // ─── Contract interface tests ──────────────────────────────

    public function test_contracts_are_properly_defined(): void
    {
        $this->assertTrue(interface_exists(HasEntityLifecycle::class));
        $this->assertTrue(interface_exists(Auditable::class));
        $this->assertTrue(interface_exists(Searchable::class));
        $this->assertTrue(interface_exists(Taggable::class));
    }

    public function test_searchable_contract_requires_to_searchable_array(): void
    {
        $reflection = new \ReflectionClass(Searchable::class);
        $this->assertTrue($reflection->hasMethod('toSearchableArray'));
    }

    // ─── Install command test ──────────────────────────────────

    public function test_install_command_is_registered(): void
    {
        $this->artisan('aicl:install', ['--help' => true])
            ->assertSuccessful();
    }
}
