<?php

namespace Aicl\Tests\Hub;

use Aicl\Models\RlmPattern;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RlmPatternTest extends TestCase
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
            'ViewAny:RlmPattern', 'View:RlmPattern', 'Create:RlmPattern',
            'Update:RlmPattern', 'Delete:RlmPattern',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());
    }

    public function test_rlm_pattern_can_be_created(): void
    {
        $owner = User::factory()->create();
        $record = RlmPattern::factory()->create(['owner_id' => $owner->id]);

        $this->assertDatabaseHas('rlm_patterns', ['id' => $record->id]);
    }

    public function test_rlm_pattern_uses_uuid_primary_key(): void
    {
        $record = RlmPattern::factory()->create();

        $this->assertIsString($record->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $record->id
        );
    }

    public function test_rlm_pattern_belongs_to_owner(): void
    {
        $owner = User::factory()->create();
        $record = RlmPattern::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($record->owner->is($owner));
    }

    public function test_rlm_pattern_soft_deletes(): void
    {
        $record = RlmPattern::factory()->create();
        $record->delete();

        $this->assertSoftDeleted('rlm_patterns', ['id' => $record->id]);
    }

    public function test_pass_rate_computed_correctly(): void
    {
        $record = RlmPattern::factory()->create([
            'pass_count' => 7,
            'fail_count' => 3,
        ]);

        $this->assertEquals(0.7, $record->pass_rate);
    }

    public function test_pass_rate_returns_zero_when_never_evaluated(): void
    {
        $record = RlmPattern::factory()->neverEvaluated()->create();

        $this->assertEquals(0.0, $record->pass_rate);
    }

    public function test_pass_rate_perfect_when_no_failures(): void
    {
        $record = RlmPattern::factory()->perfectPassRate()->create();

        $this->assertEquals(1.0, $record->pass_rate);
    }

    public function test_scope_for_target_filters_correctly(): void
    {
        RlmPattern::factory()->create(['target' => 'model']);
        RlmPattern::factory()->create(['target' => 'migration']);
        RlmPattern::factory()->create(['target' => 'model']);

        $this->assertCount(2, RlmPattern::forTarget('model')->get());
    }

    public function test_scope_by_category_filters_correctly(): void
    {
        RlmPattern::factory()->create(['category' => 'structural']);
        RlmPattern::factory()->create(['category' => 'naming']);

        $this->assertCount(1, RlmPattern::byCategory('structural')->get());
    }

    public function test_scope_by_source_filters_correctly(): void
    {
        RlmPattern::factory()->create(['source' => 'base']);
        RlmPattern::factory()->create(['source' => 'discovered']);

        $this->assertCount(1, RlmPattern::bySource('base')->get());
    }

    public function test_owner_can_view_own_rlm_pattern(): void
    {
        $owner = User::factory()->create();
        $record = RlmPattern::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($owner->can('view', $record));
    }

    public function test_admin_can_manage_any_rlm_pattern(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $record = RlmPattern::factory()->create();

        $this->assertTrue($admin->can('view', $record));
        $this->assertTrue($admin->can('update', $record));
        $this->assertTrue($admin->can('delete', $record));
    }

    public function test_rlm_pattern_searchable_columns(): void
    {
        $expected = ['name', 'description', 'target', 'category'];
        $reflection = new \ReflectionMethod(RlmPattern::class, 'searchableColumns');
        $actual = $reflection->invoke(new RlmPattern);

        $this->assertEquals($expected, $actual);
    }

    public function test_search_scope_finds_matching_records(): void
    {
        RlmPattern::factory()->create(['name' => 'Alpha Unique Value']);
        RlmPattern::factory()->create(['name' => 'Beta Unique Value']);

        $this->assertCount(1, RlmPattern::search('Alpha')->get());
    }

    public function test_rlm_pattern_creation_is_logged(): void
    {
        $record = RlmPattern::factory()->create();

        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', RlmPattern::class)
            ->where('subject_id', $record->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
    }

    public function test_entity_events_are_dispatched(): void
    {
        \Illuminate\Support\Facades\Event::fake([\Aicl\Events\EntityCreated::class]);

        RlmPattern::factory()->create();

        \Illuminate\Support\Facades\Event::assertDispatched(\Aicl\Events\EntityCreated::class);
    }

    public function test_active_scope_filters_correctly(): void
    {
        RlmPattern::factory()->create(['is_active' => true]);
        RlmPattern::factory()->create(['is_active' => false]);

        $this->assertCount(1, RlmPattern::active()->get());
    }

    public function test_applies_when_casts_to_array(): void
    {
        $record = RlmPattern::factory()->create([
            'applies_when' => ['has_states' => true, 'has_media' => false],
        ]);

        $record->refresh();

        $this->assertIsArray($record->applies_when);
        $this->assertTrue($record->applies_when['has_states']);
        $this->assertFalse($record->applies_when['has_media']);
    }

    public function test_inactive_factory_state(): void
    {
        $record = RlmPattern::factory()->inactive()->create();

        $this->assertFalse($record->is_active);
    }
}
