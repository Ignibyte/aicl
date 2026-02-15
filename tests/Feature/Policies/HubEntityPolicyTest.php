<?php

namespace Aicl\Tests\Feature\Policies;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Models\FailureReport;
use Aicl\Models\GenerationTrace;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\Models\RlmScore;
use Aicl\Policies\FailureReportPolicy;
use Aicl\Policies\GenerationTracePolicy;
use Aicl\Policies\PreventionRulePolicy;
use Aicl\Policies\RlmFailurePolicy;
use Aicl\Policies\RlmLessonPolicy;
use Aicl\Policies\RlmPatternPolicy;
use Aicl\Policies\RlmScorePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tests all 7 hub entity policies which follow an identical pattern:
 * - Owner access: view/update/delete granted when owner_id matches user id
 * - Permission access: Shield permission checks via BasePolicy
 * - Denied access: no owner match and no permission returns false
 */
class HubEntityPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $permitted;

    protected User $unauthorized;

    /** @var list<string> */
    private static array $entityNames = [
        'RlmPattern',
        'RlmFailure',
        'RlmLesson',
        'FailureReport',
        'GenerationTrace',
        'PreventionRule',
        'RlmScore',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Fake entity lifecycle events to prevent DomainEventSubscriber from
        // writing to domain_events/activity_log tables during factory creates
        Event::fake([
            EntityCreated::class,
            EntityUpdated::class,
            EntityDeleted::class,
        ]);

        $this->owner = User::factory()->create();
        $this->permitted = User::factory()->create();
        $this->unauthorized = User::factory()->create();

        // Create all View/ViewAny/Create/Update/Delete permissions for each entity
        foreach (self::$entityNames as $entity) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $action) {
                Permission::create([
                    'name' => "{$action}:{$entity}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // Clear cached permissions so Spatie picks up the new ones
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Assign all permissions to the permitted user
        foreach (self::$entityNames as $entity) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $action) {
                $this->permitted->givePermissionTo("{$action}:{$entity}");
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @return array<string, array{0: class-string, 1: class-string, 2: string}>
     */
    public static function policyProvider(): array
    {
        return [
            'RlmPattern' => [
                RlmPatternPolicy::class,
                RlmPattern::class,
                'RlmPattern',
            ],
            'RlmFailure' => [
                RlmFailurePolicy::class,
                RlmFailure::class,
                'RlmFailure',
            ],
            'RlmLesson' => [
                RlmLessonPolicy::class,
                RlmLesson::class,
                'RlmLesson',
            ],
            'FailureReport' => [
                FailureReportPolicy::class,
                FailureReport::class,
                'FailureReport',
            ],
            'GenerationTrace' => [
                GenerationTracePolicy::class,
                GenerationTrace::class,
                'GenerationTrace',
            ],
            'PreventionRule' => [
                PreventionRulePolicy::class,
                PreventionRule::class,
                'PreventionRule',
            ],
            'RlmScore' => [
                RlmScorePolicy::class,
                RlmScore::class,
                'RlmScore',
            ],
        ];
    }

    /**
     * Create a model record owned by the given user.
     *
     * Some factories have nested relationships (FailureReport -> RlmFailure,
     * PreventionRule -> RlmFailure) that would auto-create User records and
     * collide with id=1. This helper ensures all nested owners use $this->owner.
     *
     * @param  class-string  $modelClass
     */
    private function createRecord(string $modelClass, User $recordOwner): Model
    {
        $attributes = ['owner_id' => $recordOwner->id];

        // FailureReport belongs to RlmFailure — pre-create to avoid nested User factory
        if ($modelClass === FailureReport::class) {
            $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);
            $attributes['rlm_failure_id'] = $failure->id;
        }

        // PreventionRule optionally belongs to RlmFailure — set null to avoid nested creation
        if ($modelClass === PreventionRule::class) {
            $attributes['rlm_failure_id'] = null;
        }

        return $modelClass::factory()->create($attributes);
    }

    // -------------------------------------------------------
    // Owner Access Tests
    // -------------------------------------------------------

    #[DataProvider('policyProvider')]
    public function test_owner_can_view_own_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->view($this->owner, $record));
    }

    #[DataProvider('policyProvider')]
    public function test_owner_can_update_own_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->update($this->owner, $record));
    }

    #[DataProvider('policyProvider')]
    public function test_owner_can_delete_own_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->delete($this->owner, $record));
    }

    // -------------------------------------------------------
    // Permission-Based Access Tests
    // -------------------------------------------------------

    #[DataProvider('policyProvider')]
    public function test_permitted_user_can_view_any(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;

        $this->assertTrue($policy->viewAny($this->permitted));
    }

    #[DataProvider('policyProvider')]
    public function test_permitted_user_can_view_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        // Permitted user is NOT the owner, but has View permission
        $this->assertTrue($policy->view($this->permitted, $record));
    }

    #[DataProvider('policyProvider')]
    public function test_permitted_user_can_create(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;

        $this->assertTrue($policy->create($this->permitted));
    }

    #[DataProvider('policyProvider')]
    public function test_permitted_user_can_update_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        // Permitted user is NOT the owner, but has Update permission
        $this->assertTrue($policy->update($this->permitted, $record));
    }

    #[DataProvider('policyProvider')]
    public function test_permitted_user_can_delete_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        // Permitted user is NOT the owner, but has Delete permission
        $this->assertTrue($policy->delete($this->permitted, $record));
    }

    // -------------------------------------------------------
    // Denied Access Tests
    // -------------------------------------------------------

    #[DataProvider('policyProvider')]
    public function test_unauthorized_user_cannot_view_any(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;

        $this->assertFalse($policy->viewAny($this->unauthorized));
    }

    #[DataProvider('policyProvider')]
    public function test_unauthorized_user_cannot_view_others_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        // Unauthorized user is NOT the owner and has no permissions
        $this->assertFalse($policy->view($this->unauthorized, $record));
    }

    #[DataProvider('policyProvider')]
    public function test_unauthorized_user_cannot_create(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;

        $this->assertFalse($policy->create($this->unauthorized));
    }

    #[DataProvider('policyProvider')]
    public function test_unauthorized_user_cannot_update_others_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        // Unauthorized user is NOT the owner and has no permissions
        $this->assertFalse($policy->update($this->unauthorized, $record));
    }

    #[DataProvider('policyProvider')]
    public function test_unauthorized_user_cannot_delete_others_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        // Unauthorized user is NOT the owner and has no permissions
        $this->assertFalse($policy->delete($this->unauthorized, $record));
    }
}
