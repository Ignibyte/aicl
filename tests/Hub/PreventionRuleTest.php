<?php

namespace Aicl\Tests\Hub;

use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PreventionRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->seedPermissions();
    }

    protected function seedPermissions(): void
    {
        $permissions = [
            'ViewAny:PreventionRule', 'View:PreventionRule', 'Create:PreventionRule',
            'Update:PreventionRule', 'Delete:PreventionRule',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());
    }

    public function test_prevention_rule_uses_uuid_primary_key(): void
    {
        $record = PreventionRule::factory()->create();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $record->id
        );
    }

    public function test_prevention_rule_can_be_created(): void
    {
        $owner = User::factory()->create();
        $record = PreventionRule::factory()->create(['owner_id' => $owner->id]);

        $this->assertDatabaseHas('prevention_rules', ['id' => $record->id]);
    }

    public function test_prevention_rule_belongs_to_owner(): void
    {
        $owner = User::factory()->create();
        $record = PreventionRule::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($record->owner->is($owner));
    }

    public function test_prevention_rule_belongs_to_failure(): void
    {
        $failure = RlmFailure::factory()->create();
        $record = PreventionRule::factory()->create(['rlm_failure_id' => $failure->id]);

        $this->assertTrue($record->failure->is($failure));
    }

    public function test_prevention_rule_failure_is_nullable(): void
    {
        $record = PreventionRule::factory()->withoutFailure()->create();

        $this->assertNull($record->rlm_failure_id);
        $this->assertNull($record->failure);
    }

    public function test_rlm_failure_alias_works(): void
    {
        $failure = RlmFailure::factory()->create();
        $record = PreventionRule::factory()->create(['rlm_failure_id' => $failure->id]);

        $this->assertTrue($record->rlmFailure->is($failure));
    }

    public function test_prevention_rule_soft_deletes(): void
    {
        $record = PreventionRule::factory()->create();
        $record->delete();

        $this->assertSoftDeleted('prevention_rules', ['id' => $record->id]);
    }

    public function test_for_context_scope_filters_correctly(): void
    {
        PreventionRule::factory()->create([
            'trigger_context' => ['has_states' => true],
        ]);
        PreventionRule::factory()->create([
            'trigger_context' => ['has_enums' => true],
        ]);

        $results = PreventionRule::forContext(['has_states' => true])->get();

        $this->assertCount(1, $results);
    }

    public function test_high_confidence_scope(): void
    {
        PreventionRule::factory()->create(['confidence' => 0.9]);
        PreventionRule::factory()->create(['confidence' => 0.3]);

        $this->assertCount(1, PreventionRule::highConfidence(0.7)->get());
    }

    public function test_high_confidence_scope_custom_threshold(): void
    {
        PreventionRule::factory()->create(['confidence' => 0.95]);
        PreventionRule::factory()->create(['confidence' => 0.85]);
        PreventionRule::factory()->create(['confidence' => 0.5]);

        $this->assertCount(1, PreventionRule::highConfidence(0.9)->get());
    }

    public function test_active_scope_filters_correctly(): void
    {
        PreventionRule::factory()->create(['is_active' => true]);
        PreventionRule::factory()->inactive()->create();

        $this->assertCount(1, PreventionRule::active()->get());
    }

    public function test_searchable_columns_returns_correct_fields(): void
    {
        $method = new ReflectionMethod(PreventionRule::class, 'searchableColumns');
        $columns = $method->invoke(new PreventionRule);

        $this->assertEquals(['rule_text'], $columns);
    }

    public function test_search_scope_finds_matching_records(): void
    {
        PreventionRule::factory()->create(['rule_text' => 'Always check for XYZUNIQUE123 primary keys']);
        PreventionRule::factory()->create(['rule_text' => 'Validate observer field references']);

        $this->assertCount(1, PreventionRule::search('XYZUNIQUE123')->get());
    }

    public function test_owner_can_view_own_rule(): void
    {
        $owner = User::factory()->create();
        $record = PreventionRule::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($owner->can('view', $record));
    }

    public function test_admin_can_manage_any_rule(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $record = PreventionRule::factory()->create();

        $this->assertTrue($admin->can('view', $record));
        $this->assertTrue($admin->can('update', $record));
        $this->assertTrue($admin->can('delete', $record));
    }

    public function test_creation_is_logged(): void
    {
        $record = PreventionRule::factory()->create();

        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', PreventionRule::class)
            ->where('subject_id', $record->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
    }

    public function test_entity_events_are_dispatched(): void
    {
        \Illuminate\Support\Facades\Event::fake([\Aicl\Events\EntityCreated::class]);

        PreventionRule::factory()->create();

        \Illuminate\Support\Facades\Event::assertDispatched(\Aicl\Events\EntityCreated::class);
    }

    public function test_factory_high_confidence_state(): void
    {
        $record = PreventionRule::factory()->highConfidence()->create();

        $this->assertGreaterThanOrEqual(0.8, (float) $record->confidence);
        $this->assertGreaterThanOrEqual(10, $record->applied_count);
    }

    public function test_factory_without_failure_state(): void
    {
        $record = PreventionRule::factory()->withoutFailure()->create();

        $this->assertNull($record->rlm_failure_id);
    }

    public function test_factory_inactive_state(): void
    {
        $record = PreventionRule::factory()->inactive()->create();

        $this->assertFalse($record->is_active);
    }

    public function test_trigger_context_casts_to_array(): void
    {
        $context = ['has_states' => true, 'field_types' => ['enum']];
        $record = PreventionRule::factory()->create(['trigger_context' => $context]);

        $this->assertIsArray($record->trigger_context);
        $this->assertTrue($record->trigger_context['has_states']);
    }

    public function test_casts_are_correct(): void
    {
        $record = PreventionRule::factory()->create([
            'confidence' => 0.85,
            'priority' => 5,
            'applied_count' => 10,
            'is_active' => true,
        ]);

        $this->assertIsBool($record->is_active);
        $this->assertIsInt($record->priority);
        $this->assertIsInt($record->applied_count);
    }

    public function test_observer_logs_creation_with_rule_text(): void
    {
        $record = PreventionRule::factory()->create(['rule_text' => 'Test rule for observer logging']);

        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', PreventionRule::class)
            ->where('subject_id', $record->id)
            ->where('description', 'like', '%Test rule for observer%')
            ->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('Test rule for observer', $activity->description);
    }
}
