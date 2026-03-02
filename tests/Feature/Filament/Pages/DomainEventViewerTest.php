<?php

namespace Aicl\Tests\Feature\Filament\Pages;

use Aicl\Events\Enums\ActorType;
use Aicl\Filament\Pages\ActivityLog;
use Aicl\Livewire\DomainEventTable;
use Aicl\Models\DomainEventRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DomainEventViewerTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');
    }

    public function test_activity_log_page_renders_successfully(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(ActivityLog::class)
            ->assertSuccessful();
    }

    public function test_domain_event_table_renders_successfully(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(DomainEventTable::class)
            ->assertSuccessful();
    }

    public function test_domain_event_table_displays_events(): void
    {
        DomainEventRecord::create([
            'event_type' => 'order.created',
            'actor_type' => ActorType::User->value,
            'actor_id' => $this->superAdmin->id,
            'entity_type' => 'App\\Models\\Order',
            'entity_id' => '1',
            'payload' => ['order_total' => 99.99],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(DomainEventTable::class)
            ->assertSuccessful()
            ->assertSee('order.created');
    }

    public function test_empty_state_shows_message(): void
    {
        // Clear auto-persisted entity lifecycle events from setUp
        DomainEventRecord::query()->delete();

        $this->actingAs($this->superAdmin);

        Livewire::test(DomainEventTable::class)
            ->assertSuccessful()
            ->assertSee('No domain events');
    }

    public function test_actor_type_filter(): void
    {
        DomainEventRecord::create([
            'event_type' => 'user.login',
            'actor_type' => ActorType::User->value,
            'actor_id' => $this->superAdmin->id,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        DomainEventRecord::create([
            'event_type' => 'system.cleanup',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(DomainEventTable::class)
            ->filterTable('actor_type', ActorType::User->value)
            ->assertCanSeeTableRecords(
                DomainEventRecord::where('actor_type', ActorType::User->value)->get()
            )
            ->assertCanNotSeeTableRecords(
                DomainEventRecord::where('actor_type', ActorType::System->value)->get()
            );
    }

    public function test_entity_type_filter(): void
    {
        DomainEventRecord::create([
            'event_type' => 'order.created',
            'actor_type' => ActorType::System->value,
            'entity_type' => 'App\\Models\\Order',
            'entity_id' => '1',
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        DomainEventRecord::create([
            'event_type' => 'user.updated',
            'actor_type' => ActorType::System->value,
            'entity_type' => 'App\\Models\\User',
            'entity_id' => '1',
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(DomainEventTable::class)
            ->filterTable('entity_type', 'App\\Models\\Order')
            ->assertCanSeeTableRecords(
                DomainEventRecord::where('entity_type', 'App\\Models\\Order')->get()
            )
            ->assertCanNotSeeTableRecords(
                DomainEventRecord::where('entity_type', 'App\\Models\\User')->get()
            );
    }

    public function test_table_sorts_by_occurred_at_desc_by_default(): void
    {
        $older = DomainEventRecord::create([
            'event_type' => 'old.event',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now()->subHour(),
        ]);

        $newer = DomainEventRecord::create([
            'event_type' => 'new.event',
            'actor_type' => ActorType::System->value,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(DomainEventTable::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }
}
