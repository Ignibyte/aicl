<?php

namespace Aicl\Tests\Unit\Jobs;

use Aicl\Console\Commands\HubSeedCommand;
use Aicl\Console\Commands\UpgradeCommand;
use Aicl\Jobs\CheckPromotionCandidatesJob;
use Aicl\Jobs\GenerateEmbeddingJob;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmPattern;
use Aicl\Notifications\FailurePromotionCandidateNotification;
use Aicl\Rlm\EmbeddingService;
use Aicl\Rlm\PatternRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JobAndCommandCoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            \Aicl\Events\EntityCreated::class,
            \Aicl\Events\EntityCreating::class,
            \Aicl\Events\EntityUpdated::class,
            \Aicl\Events\EntityUpdating::class,
            \Aicl\Events\EntityDeleted::class,
            \Aicl\Events\EntityDeleting::class,
        ]);

        $this->admin = User::factory()->create(['id' => 1]);
        Queue::fake();
    }

    // ========================================================================
    // CheckPromotionCandidatesJob
    // ========================================================================

    public function test_promotion_job_returns_early_when_already_promoted(): void
    {
        Notification::fake();

        $failure = RlmFailure::factory()->promoted()->create([
            'owner_id' => $this->admin->id,
            'report_count' => 5,
            'project_count' => 3,
        ]);

        $job = new CheckPromotionCandidatesJob($failure);
        $job->handle();

        Notification::assertNothingSent();
    }

    public function test_promotion_job_returns_early_when_report_count_insufficient(): void
    {
        Notification::fake();

        $failure = RlmFailure::factory()->create([
            'owner_id' => $this->admin->id,
            'report_count' => 2,
            'project_count' => 3,
            'promoted_to_base' => false,
        ]);

        $job = new CheckPromotionCandidatesJob($failure);
        $job->handle();

        Notification::assertNothingSent();
    }

    public function test_promotion_job_returns_early_when_project_count_insufficient(): void
    {
        Notification::fake();

        $failure = RlmFailure::factory()->create([
            'owner_id' => $this->admin->id,
            'report_count' => 5,
            'project_count' => 1,
            'promoted_to_base' => false,
        ]);

        $job = new CheckPromotionCandidatesJob($failure);
        $job->handle();

        Notification::assertNothingSent();
    }

    public function test_promotion_job_notifies_failure_owner(): void
    {
        Notification::fake();

        $owner = $this->admin;

        $failure = RlmFailure::factory()->promotable()->create([
            'owner_id' => $owner->id,
        ]);

        $job = new CheckPromotionCandidatesJob($failure);
        $job->handle();

        Notification::assertSentTo(
            $owner,
            FailurePromotionCandidateNotification::class,
            function (FailurePromotionCandidateNotification $notification) use ($failure) {
                return $notification->failure->id === $failure->id;
            }
        );
    }

    public function test_promotion_job_notifies_admin_when_owner_relation_is_null(): void
    {
        Notification::fake();

        Role::findOrCreate('admin', 'web');
        $this->admin->assignRole('admin');

        $failure = RlmFailure::factory()->promotable()->create([
            'owner_id' => $this->admin->id,
        ]);

        // Force the owner relationship to return null by pre-loading it
        // This simulates the scenario where the owner can't be resolved
        $failure->setRelation('owner', null);

        $job = new CheckPromotionCandidatesJob($failure);
        $job->handle();

        Notification::assertSentTo(
            $this->admin,
            FailurePromotionCandidateNotification::class,
        );
    }

    public function test_promotion_job_sends_nothing_when_no_recipient_found(): void
    {
        Notification::fake();

        // Create the admin role so the query doesn't throw, but don't assign it to anyone
        Role::findOrCreate('admin', 'web');

        $failure = RlmFailure::factory()->promotable()->create([
            'owner_id' => $this->admin->id,
        ]);

        // Force owner relationship to return null
        $failure->setRelation('owner', null);

        // No user has the admin role, so User::role('admin')->first() returns null
        $job = new CheckPromotionCandidatesJob($failure);
        $job->handle();

        Notification::assertNothingSent();
    }

    // ========================================================================
    // GenerateEmbeddingJob
    // ========================================================================

    public function test_embedding_job_skips_when_service_unavailable(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => $this->admin->id]);

        $service = $this->mock(EmbeddingService::class);
        $service->shouldReceive('isAvailable')->once()->andReturn(false);
        $service->shouldNotReceive('generate');

        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function (string $message) {
                return str_contains($message, 'Embedding service unavailable');
            });

        $job = new GenerateEmbeddingJob($pattern);
        $job->handle($service);
    }

    public function test_embedding_job_skips_when_embedding_text_is_empty(): void
    {
        $pattern = RlmPattern::factory()->create([
            'owner_id' => $this->admin->id,
            'name' => '',
            'description' => '',
            'target' => '',
        ]);

        $service = $this->mock(EmbeddingService::class);
        $service->shouldReceive('isAvailable')->once()->andReturn(true);
        $service->shouldNotReceive('generate');

        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function (string $message) {
                return str_contains($message, 'Empty embedding text');
            });

        $job = new GenerateEmbeddingJob($pattern);
        $job->handle($service);
    }

    public function test_embedding_job_returns_early_when_embedding_is_null(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => $this->admin->id]);

        $service = $this->mock(EmbeddingService::class);
        $service->shouldReceive('isAvailable')->once()->andReturn(true);
        $service->shouldReceive('generate')->once()->andReturn(null);

        $job = new GenerateEmbeddingJob($pattern);
        $job->handle($service);

        // Verify cacheEmbedding was not called by checking no cache was set
        $this->assertNull($pattern->getCachedEmbedding());
    }

    public function test_embedding_job_generates_and_caches_embedding(): void
    {
        $pattern = RlmPattern::factory()->create(['owner_id' => $this->admin->id]);
        $expectedEmbedding = array_fill(0, 10, 0.1);

        $service = $this->mock(EmbeddingService::class);
        $service->shouldReceive('isAvailable')->once()->andReturn(true);
        $service->shouldReceive('generate')
            ->once()
            ->with($pattern->embeddingText())
            ->andReturn($expectedEmbedding);

        $job = new GenerateEmbeddingJob($pattern);
        $job->handle($service);

        // Verify the embedding was cached
        $cached = $pattern->getCachedEmbedding();
        $this->assertNotNull($cached);
        $this->assertSame($expectedEmbedding, $cached);
    }

    // ========================================================================
    // UpgradeCommand
    // ========================================================================

    public function test_upgrade_command_reports_already_up_to_date(): void
    {
        $manifest = require base_path('packages/aicl/config/upgrade-manifest.php');
        $version = $manifest['version'];

        // Write a state file that matches the current manifest version
        $statePath = base_path('.aicl-state.json');
        file_put_contents($statePath, json_encode([
            'package_version' => $version,
            'last_upgraded' => now()->toIso8601String(),
            'applied' => [],
        ]));

        try {
            $this->artisan('aicl:upgrade')
                ->assertExitCode(0)
                ->expectsOutputToContain("Already up to date with v{$version}");
        } finally {
            @unlink($statePath);
        }
    }

    public function test_upgrade_command_dry_run_shows_changes(): void
    {
        // Remove state file if it exists so everything appears as needing update
        $statePath = base_path('.aicl-state.json');
        @unlink($statePath);

        $this->artisan('aicl:upgrade')
            ->assertExitCode(0)
            ->expectsOutputToContain('dry-run')
            ->expectsOutputToContain('Run with --force to apply changes.');
    }

    public function test_upgrade_command_unknown_section_returns_failure(): void
    {
        $this->artisan('aicl:upgrade', ['--section' => 'nonexistent'])
            ->assertExitCode(1)
            ->expectsOutputToContain('Unknown section: nonexistent');
    }

    public function test_upgrade_command_section_filter_limits_processing(): void
    {
        $statePath = base_path('.aicl-state.json');
        @unlink($statePath);

        // Filter to only the 'claude' section
        $this->artisan('aicl:upgrade', ['--section' => 'claude'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Claude Configuration');
    }

    public function test_upgrade_command_force_applies_changes(): void
    {
        $statePath = base_path('.aicl-state.json');
        @unlink($statePath);

        $this->artisan('aicl:upgrade', ['--force' => true, '--section' => 'claude'])
            ->assertExitCode(0)
            ->expectsOutputToContain('applying changes')
            ->expectsOutputToContain('State saved to .aicl-state.json');

        // Verify state file was created
        $this->assertFileExists($statePath);

        $state = json_decode(file_get_contents($statePath), true);
        $this->assertArrayHasKey('package_version', $state);
        $this->assertArrayHasKey('last_upgraded', $state);

        @unlink($statePath);
    }

    public function test_upgrade_command_fresh_flag_ignores_state(): void
    {
        $manifest = require base_path('packages/aicl/config/upgrade-manifest.php');
        $version = $manifest['version'];

        // Write a state file matching the current version
        $statePath = base_path('.aicl-state.json');
        file_put_contents($statePath, json_encode([
            'package_version' => $version,
            'last_upgraded' => now()->toIso8601String(),
            'applied' => [],
        ]));

        try {
            // Without --fresh, would say "Already up to date"
            // With --fresh, should process sections (dry-run)
            $this->artisan('aicl:upgrade', ['--fresh' => true])
                ->assertExitCode(0)
                ->expectsOutputToContain('dry-run');
        } finally {
            @unlink($statePath);
        }
    }

    public function test_build_initial_state_returns_state_array(): void
    {
        $packageRoot = base_path('packages/aicl');
        $projectRoot = base_path();

        $state = UpgradeCommand::buildInitialState($packageRoot, $projectRoot, '1.0.0');

        $this->assertArrayHasKey('package_version', $state);
        $this->assertArrayHasKey('last_upgraded', $state);
        $this->assertArrayHasKey('applied', $state);
        $this->assertSame('1.0.0', $state['package_version']);
    }

    public function test_build_initial_state_with_missing_manifest(): void
    {
        $state = UpgradeCommand::buildInitialState('/nonexistent/path', base_path(), '2.0.0');

        $this->assertSame('2.0.0', $state['package_version']);
        $this->assertArrayHasKey('last_upgraded', $state);
        $this->assertSame([], $state['applied']);
    }

    public function test_build_initial_state_hashes_existing_files(): void
    {
        $packageRoot = base_path('packages/aicl');
        $projectRoot = base_path();

        $state = UpgradeCommand::buildInitialState($packageRoot, $projectRoot, '1.0.0');

        // The CLAUDE.md file should exist and be hashed
        if (file_exists($projectRoot.'/CLAUDE.md')) {
            $this->assertNotEmpty($state['applied']);
        }
    }

    // ========================================================================
    // HubSeedCommand
    // ========================================================================

    public function test_hub_seed_fails_with_no_users(): void
    {
        // Delete all users to trigger the error condition
        User::query()->delete();

        $this->artisan('aicl:hub-seed')
            ->assertExitCode(1)
            ->expectsOutputToContain('No users found');
    }

    public function test_hub_seed_seeds_patterns_successfully(): void
    {
        $this->artisan('aicl:hub-seed')
            ->assertExitCode(0)
            ->expectsOutputToContain('Hub seed complete');

        $patternCount = count(PatternRegistry::all());
        $this->assertGreaterThanOrEqual($patternCount, RlmPattern::count());
    }

    public function test_hub_seed_is_idempotent(): void
    {
        // Run seed twice
        $this->artisan('aicl:hub-seed')->assertExitCode(0);
        $countAfterFirst = RlmPattern::count();

        $this->artisan('aicl:hub-seed')->assertExitCode(0);
        $countAfterSecond = RlmPattern::count();

        // Should not create duplicates
        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    public function test_hub_seed_seeds_base_failures_from_sql(): void
    {
        $this->artisan('aicl:hub-seed')->assertExitCode(0);

        // The SQL file has multiple failures; at least some should be created
        $failureCount = RlmFailure::where('promoted_to_base', true)->count();
        $this->assertGreaterThan(0, $failureCount);
    }

    public function test_hub_seed_force_option_reseeds(): void
    {
        $this->artisan('aicl:hub-seed')->assertExitCode(0);

        // Modify a pattern's description
        $pattern = RlmPattern::first();
        $originalDesc = $pattern->description;
        $pattern->update(['description' => 'Modified by test']);

        // Force re-seed should restore original
        $this->artisan('aicl:hub-seed', ['--force' => true])->assertExitCode(0);

        $pattern->refresh();
        // updateOrCreate with force will update the description back
        $this->assertSame($originalDesc, $pattern->description);
    }
}
