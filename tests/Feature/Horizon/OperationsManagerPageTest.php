<?php

declare(strict_types=1);

namespace Aicl\Tests\Feature\Horizon;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Events\SessionTerminated;
use Aicl\Filament\Pages\OperationsManager;
use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\Contracts\WorkloadRepository;
use Aicl\Services\PresenceRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

/**
 * Tests the OperationsManager Filament page including queue stats,
 * supervisors, scheduler, notifications, sessions, and access control.
 */
class OperationsManagerPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);

        Event::fake([
            EntityCreated::class,
            EntityUpdated::class,
            EntityDeleted::class,
        ]);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    // ── Page Structure ────────────────────────────────────

    /** Verifies the OperationsManager class exists and is loadable. */
    public function test_operations_manager_page_exists(): void
    {
        $this->assertTrue(class_exists(OperationsManager::class));
    }

    /** Verifies the slug is 'operations-manager'. */
    public function test_operations_manager_slug(): void
    {
        $this->assertSame('operations-manager', OperationsManager::getSlug());
    }

    /** Verifies the correct Blade view is assigned. */
    public function test_operations_manager_uses_correct_view(): void
    {
        $page = new OperationsManager;

        $reflection = new \ReflectionProperty($page, 'view');
        $this->assertSame('aicl::filament.pages.operations-manager', $reflection->getValue($page));
    }

    /** Verifies default active tab is 'overview'. */
    public function test_operations_manager_has_active_tab_property(): void
    {
        $page = new OperationsManager;

        $this->assertSame('overview', $page->activeTab);
    }

    /** Verifies default active section is 'queues'. */
    public function test_operations_manager_has_active_section_property(): void
    {
        $page = new OperationsManager;

        $this->assertSame('queues', $page->activeSection);
    }

    // ── Queue Stats ────────────────────────────────────

    /**
     * Verifies getQueueStats returns all expected keys
     * when Horizon repositories are mocked.
     */
    public function test_get_queue_stats_returns_expected_structure(): void
    {
        $this->mock(JobRepository::class, function ($mock): void {
            $mock->shouldReceive('countPending')->andReturn(5);
            $mock->shouldReceive('countFailed')->andReturn(2);
        });

        $this->mock(WorkloadRepository::class, function ($mock): void {
            $mock->shouldReceive('get')->andReturn([]);
        });

        $this->mock(MetricsRepository::class, function ($mock): void {
            $mock->shouldReceive('snapshotsForQueue')->with('default')->andReturn([]);
        });

        $page = new OperationsManager;
        $stats = $page->getQueueStats();

        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('last_failed', $stats);
        $this->assertArrayHasKey('jobs_per_minute', $stats);
        $this->assertArrayHasKey('total_processes', $stats);
        $this->assertArrayHasKey('workload', $stats);
    }

    /**
     * Verifies Horizon data values flow through to stats output
     * when Horizon is available.
     */
    public function test_get_queue_stats_uses_horizon_data_when_available(): void
    {
        $this->mock(JobRepository::class, function ($mock): void {
            $mock->shouldReceive('countPending')->andReturn(42);
            $mock->shouldReceive('countFailed')->andReturn(7);
        });

        $this->mock(WorkloadRepository::class, function ($mock): void {
            $mock->shouldReceive('get')->andReturn([
                ['name' => 'default', 'length' => 10, 'wait' => 5, 'processes' => 3],
            ]);
        });

        $this->mock(MetricsRepository::class, function ($mock): void {
            $mock->shouldReceive('snapshotsForQueue')->andReturn([]);
        });

        $page = new OperationsManager;
        $stats = $page->getQueueStats();

        $this->assertSame(42, $stats['pending']);
        $this->assertSame(3, $stats['total_processes']);
    }

    /**
     * Verifies throughput from metric snapshots is reflected
     * in the jobs_per_minute stat.
     */
    public function test_get_queue_stats_includes_jobs_per_minute(): void
    {
        $snapshot = (object) ['throughput' => 8.5];

        $this->mock(JobRepository::class, function ($mock): void {
            $mock->shouldReceive('countPending')->andReturn(0);
            $mock->shouldReceive('countFailed')->andReturn(0);
        });

        $this->mock(WorkloadRepository::class, function ($mock): void {
            $mock->shouldReceive('get')->andReturn([]);
        });

        $this->mock(MetricsRepository::class, function ($mock) use ($snapshot): void {
            $mock->shouldReceive('snapshotsForQueue')->with('default')->andReturn([$snapshot]);
        });

        $page = new OperationsManager;
        $stats = $page->getQueueStats();

        $this->assertSame(8.5, $stats['jobs_per_minute']);
    }

    // ── Supervisors ────────────────────────────────────

    /**
     * Verifies getSupervisors returns an empty array when
     * the supervisor repository returns nothing.
     */
    public function test_get_supervisors_returns_array(): void
    {
        $this->mock(SupervisorRepository::class, function ($mock): void {
            $mock->shouldReceive('all')->andReturn([]);
        });

        $page = new OperationsManager;
        $result = $page->getSupervisors();

        $this->assertSame([], $result);
    }

    /** Verifies supervisors returns empty when Horizon is disabled. */
    public function test_get_supervisors_returns_empty_when_horizon_disabled(): void
    {
        config(['aicl.features.horizon' => false]);

        $page = new OperationsManager;
        $result = $page->getSupervisors();

        $this->assertSame([], $result);
    }

    /** Verifies Horizon is detected as available when repo is bound and feature enabled. */
    public function test_is_horizon_available_returns_true_when_repo_bound(): void
    {
        $this->mock(JobRepository::class);
        config(['aicl.features.horizon' => true]);

        $page = new OperationsManager;
        $this->assertTrue($page->isHorizonAvailable());
    }

    /** Verifies Horizon is detected as unavailable when feature flag is disabled. */
    public function test_is_horizon_available_returns_false_when_disabled(): void
    {
        config(['aicl.features.horizon' => false]);

        $page = new OperationsManager;
        $this->assertFalse($page->isHorizonAvailable());
    }

    /** Verifies the queue driver is read from config. */
    public function test_get_queue_driver_returns_configured_driver(): void
    {
        config(['queue.default' => 'redis', 'queue.connections.redis.driver' => 'redis']);

        $page = new OperationsManager;
        $this->assertSame('redis', $page->getQueueDriver());
    }

    /**
     * Verifies all 10 queue tabs are available when Horizon is enabled,
     * including recent, pending, completed, metrics, workload, supervisors, monitoring.
     */
    public function test_get_queue_tabs_shows_all_tabs_when_horizon_available(): void
    {
        $this->mock(JobRepository::class);
        config(['aicl.features.horizon' => true]);

        $page = new OperationsManager;
        $tabs = $page->getQueueTabs();

        $this->assertArrayHasKey('overview', $tabs);
        $this->assertArrayHasKey('recent', $tabs);
        $this->assertArrayHasKey('pending', $tabs);
        $this->assertArrayHasKey('completed', $tabs);
        $this->assertArrayHasKey('failed-jobs', $tabs);
        $this->assertArrayHasKey('batches', $tabs);
        $this->assertArrayHasKey('metrics', $tabs);
        $this->assertArrayHasKey('workload', $tabs);
        $this->assertArrayHasKey('supervisors', $tabs);
        $this->assertArrayHasKey('monitoring', $tabs);
        $this->assertCount(10, $tabs);
    }

    /**
     * Verifies only 3 queue tabs are available when Horizon is disabled:
     * overview, failed-jobs, batches.
     */
    public function test_get_queue_tabs_shows_reduced_tabs_when_horizon_unavailable(): void
    {
        config(['aicl.features.horizon' => false]);

        $page = new OperationsManager;
        $tabs = $page->getQueueTabs();

        $this->assertArrayHasKey('overview', $tabs);
        $this->assertArrayHasKey('failed-jobs', $tabs);
        $this->assertArrayHasKey('batches', $tabs);
        $this->assertArrayNotHasKey('recent', $tabs);
        $this->assertArrayNotHasKey('pending', $tabs);
        $this->assertArrayNotHasKey('completed', $tabs);
        $this->assertArrayNotHasKey('metrics', $tabs);
        $this->assertArrayNotHasKey('workload', $tabs);
        $this->assertArrayNotHasKey('supervisors', $tabs);
        $this->assertArrayNotHasKey('monitoring', $tabs);
        $this->assertCount(3, $tabs);
    }

    // ── Scheduler Section ────────────────────────────────────

    /** Verifies scheduler tabs include registered, history, and schedule-failures. */
    public function test_get_scheduler_tabs_returns_expected_tabs(): void
    {
        $page = new OperationsManager;
        $tabs = $page->getSchedulerTabs();

        $this->assertArrayHasKey('registered', $tabs);
        $this->assertArrayHasKey('history', $tabs);
        $this->assertArrayHasKey('schedule-failures', $tabs);
        $this->assertCount(3, $tabs);
    }

    /** Verifies scheduler stats include all expected metric keys. */
    public function test_get_scheduler_stats_returns_expected_structure(): void
    {
        $page = new OperationsManager;
        $stats = $page->getSchedulerStats();

        $this->assertArrayHasKey('total_registered', $stats);
        $this->assertArrayHasKey('last_run_at', $stats);
        $this->assertArrayHasKey('failed_24h', $stats);
        $this->assertArrayHasKey('success_rate_24h', $stats);
    }

    /** Verifies registered tasks returns a countable result. */
    public function test_get_registered_tasks_returns_array(): void
    {
        $page = new OperationsManager;
        $tasks = $page->getRegisteredTasks();

        $this->assertCount(count($tasks), $tasks);
    }

    // ── Notification Section ────────────────────────────────────

    /** Verifies notification tabs include delivery-health and failed-deliveries. */
    public function test_get_notification_tabs_returns_expected_tabs(): void
    {
        $page = new OperationsManager;
        $tabs = $page->getNotificationTabs();

        $this->assertArrayHasKey('delivery-health', $tabs);
        $this->assertArrayHasKey('failed-deliveries', $tabs);
        $this->assertCount(2, $tabs);
    }

    /** Verifies notification delivery health returns a countable result. */
    public function test_get_notification_delivery_health_returns_array(): void
    {
        $page = new OperationsManager;
        $health = $page->getNotificationDeliveryHealth();

        $this->assertCount(count($health), $health);
    }

    /** Verifies notification queue depth returns a non-negative integer. */
    public function test_get_notification_queue_depth_returns_integer(): void
    {
        $page = new OperationsManager;
        $depth = $page->getNotificationQueueDepth();

        $this->assertGreaterThanOrEqual(0, $depth);
    }

    /** Verifies stuck deliveries count returns a non-negative integer. */
    public function test_get_stuck_deliveries_returns_integer(): void
    {
        $page = new OperationsManager;
        $stuck = $page->getStuckDeliveries();

        $this->assertGreaterThanOrEqual(0, $stuck);
    }

    // ── Access Control ────────────────────────────────────

    /** Verifies guests cannot access the operations manager. */
    public function test_can_access_returns_false_for_guests(): void
    {
        $this->assertFalse(OperationsManager::canAccess());
    }

    // ── Sessions Section ──────────────────────────────────

    /** Verifies getActiveSessions returns a Collection instance. */
    public function test_get_active_sessions_returns_collection(): void
    {
        Cache::forget('presence:session_index');

        $page = new OperationsManager;
        $sessions = $page->getActiveSessions();

        $this->assertInstanceOf(Collection::class, $sessions);
    }

    /**
     * Verifies getActiveSessions reads from the PresenceRegistry
     * and returns sessions with correct user_id.
     */
    public function test_get_active_sessions_delegates_to_presence_registry(): void
    {
        $registry = app(PresenceRegistry::class);

        $registry->touch('test-sess-001', $this->superAdmin->getKey(), [
            'user_name' => $this->superAdmin->name,
            'user_email' => $this->superAdmin->email,
            'current_url' => 'https://app.test/admin/dashboard',
            'ip_address' => '127.0.0.1',
        ]);

        $page = new OperationsManager;
        $sessions = $page->getActiveSessions();

        $this->assertCount(1, $sessions);

        /** @var array<string, mixed> $firstSession */
        $firstSession = $sessions->first();
        $this->assertSame($this->superAdmin->getKey(), $firstSession['user_id']);

        // Cleanup
        $registry->forget('test-sess-001');
    }

    /**
     * Verifies that a non-super_admin user cannot terminate a session.
     * The session should remain intact and no event should be dispatched.
     */
    public function test_terminate_session_requires_super_admin(): void
    {
        Event::fake([SessionTerminated::class]);

        $this->actingAs($this->admin);

        $registry = app(PresenceRegistry::class);
        $registry->touch('target-sess', 99, [
            'user_name' => 'Victim',
            'user_email' => 'victim@example.com',
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        $page = new OperationsManager;
        $page->terminateSession('target-sess');

        // Session should NOT be terminated (admin != super_admin)
        $this->assertNotNull(Cache::get('presence:sessions:target-sess'));
        Event::assertNotDispatched(SessionTerminated::class);

        // Cleanup
        $registry->forget('target-sess');
    }

    /**
     * Verifies that a super_admin can successfully terminate another
     * user's session and the SessionTerminated event fires.
     */
    public function test_terminate_session_succeeds_for_super_admin(): void
    {
        Event::fake([SessionTerminated::class]);

        $registry = app(PresenceRegistry::class);
        $registry->touch('target-sess', $this->admin->getKey(), [
            'user_name' => $this->admin->name,
            'user_email' => $this->admin->email,
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($this->superAdmin)->get('/admin/operations-manager');

        $page = new OperationsManager;
        $page->terminateSession('target-sess');

        $this->assertNull(Cache::get('presence:sessions:target-sess'));
        Event::assertDispatched(SessionTerminated::class);
    }

    /**
     * Verifies that a super_admin cannot terminate their own session.
     * Self-termination prevention protects against accidental lockout.
     */
    public function test_terminate_session_prevents_self_termination(): void
    {
        Event::fake([SessionTerminated::class]);

        $this->actingAs($this->superAdmin)->get('/admin/operations-manager');

        $currentSessionId = session()->getId();

        $registry = app(PresenceRegistry::class);
        $registry->touch($currentSessionId, $this->superAdmin->getKey(), [
            'user_name' => $this->superAdmin->name,
            'user_email' => $this->superAdmin->email,
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        $page = new OperationsManager;
        $page->terminateSession($currentSessionId);

        // Should NOT be terminated (own session)
        $this->assertNotNull(Cache::get('presence:sessions:'.$currentSessionId));
        Event::assertNotDispatched(SessionTerminated::class);

        // Cleanup
        $registry->forget($currentSessionId);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
