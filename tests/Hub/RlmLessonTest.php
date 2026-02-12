<?php

namespace Aicl\Tests\Hub;

use Aicl\Models\RlmLesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RlmLessonTest extends TestCase
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
            'ViewAny:RlmLesson', 'View:RlmLesson', 'Create:RlmLesson',
            'Update:RlmLesson', 'Delete:RlmLesson',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());
    }

    public function test_rlm_lesson_uses_uuid_primary_key(): void
    {
        $record = RlmLesson::factory()->create();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $record->id
        );
    }

    public function test_rlm_lesson_can_be_created(): void
    {
        $owner = User::factory()->create();
        $record = RlmLesson::factory()->create(['owner_id' => $owner->id]);

        $this->assertDatabaseHas('rlm_lessons', ['id' => $record->id]);
    }

    public function test_rlm_lesson_belongs_to_owner(): void
    {
        $owner = User::factory()->create();
        $record = RlmLesson::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($record->owner->is($owner));
    }

    public function test_rlm_lesson_soft_deletes(): void
    {
        $record = RlmLesson::factory()->create();
        $record->delete();

        $this->assertSoftDeleted('rlm_lessons', ['id' => $record->id]);
    }

    public function test_verified_scope_filters_verified_records(): void
    {
        RlmLesson::factory()->verified()->create();
        RlmLesson::factory()->create(['is_verified' => false]);

        $this->assertCount(1, RlmLesson::verified()->get());
    }

    public function test_unverified_scope_filters_unverified_records(): void
    {
        RlmLesson::factory()->verified()->create();
        RlmLesson::factory()->create(['is_verified' => false]);

        $this->assertCount(1, RlmLesson::unverified()->get());
    }

    public function test_by_topic_scope(): void
    {
        RlmLesson::factory()->create(['topic' => 'Testing']);
        RlmLesson::factory()->create(['topic' => 'Filament']);

        $this->assertCount(1, RlmLesson::byTopic('Testing')->get());
    }

    public function test_by_context_tag_scope(): void
    {
        RlmLesson::factory()->create(['context_tags' => ['entity', 'entity:states']]);
        RlmLesson::factory()->create(['context_tags' => ['service', 'middleware']]);

        $this->assertCount(1, RlmLesson::byContextTag('entity')->get());
    }

    public function test_active_scope_filters_correctly(): void
    {
        RlmLesson::factory()->create(['is_active' => true]);
        RlmLesson::factory()->create(['is_active' => false]);

        $this->assertCount(1, RlmLesson::active()->get());
    }

    public function test_searchable_columns_returns_correct_fields(): void
    {
        $method = new ReflectionMethod(RlmLesson::class, 'searchableColumns');
        $columns = $method->invoke(new RlmLesson);

        $this->assertEquals(['topic', 'summary', 'detail', 'tags'], $columns);
    }

    public function test_search_scope_finds_matching_records(): void
    {
        RlmLesson::factory()->create(['topic' => 'UniqueTestTopic']);
        RlmLesson::factory()->create(['topic' => 'DifferentTopic']);

        $this->assertCount(1, RlmLesson::search('UniqueTestTopic')->get());
    }

    public function test_owner_can_view_own_rlm_lesson(): void
    {
        $owner = User::factory()->create();
        $record = RlmLesson::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($owner->can('view', $record));
    }

    public function test_admin_can_manage_any_rlm_lesson(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $record = RlmLesson::factory()->create();

        $this->assertTrue($admin->can('view', $record));
        $this->assertTrue($admin->can('update', $record));
        $this->assertTrue($admin->can('delete', $record));
    }

    public function test_rlm_lesson_creation_is_logged(): void
    {
        $record = RlmLesson::factory()->create();

        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', RlmLesson::class)
            ->where('subject_id', $record->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
    }

    public function test_entity_events_are_dispatched(): void
    {
        \Illuminate\Support\Facades\Event::fake([\Aicl\Events\EntityCreated::class]);

        RlmLesson::factory()->create();

        \Illuminate\Support\Facades\Event::assertDispatched(\Aicl\Events\EntityCreated::class);
    }

    public function test_factory_verified_state(): void
    {
        $record = RlmLesson::factory()->verified()->create();

        $this->assertTrue($record->is_verified);
        $this->assertGreaterThanOrEqual(0.8, (float) $record->confidence);
    }

    public function test_factory_popular_state(): void
    {
        $record = RlmLesson::factory()->popular()->create();

        $this->assertGreaterThanOrEqual(50, $record->view_count);
    }

    public function test_context_tags_casts_to_array(): void
    {
        $record = RlmLesson::factory()->create([
            'context_tags' => ['entity', 'filament:form'],
        ]);

        $this->assertIsArray($record->context_tags);
        $this->assertContains('entity', $record->context_tags);
    }

    public function test_casts_are_correct(): void
    {
        $record = RlmLesson::factory()->create([
            'confidence' => 0.95,
            'view_count' => 42,
        ]);

        $this->assertIsBool($record->is_verified);
        $this->assertIsBool($record->is_active);
        $this->assertIsInt($record->view_count);
    }
}
