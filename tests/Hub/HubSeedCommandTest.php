<?php

namespace Aicl\Tests\Hub;

use Aicl\Console\Commands\HubSeedCommand;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmPattern;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubSeedCommandTest extends TestCase
{
    use RefreshDatabase;

    // --- Command Registration ---

    public function test_command_is_registered(): void
    {
        $this->artisan('aicl:hub-seed --help')
            ->assertSuccessful();
    }

    public function test_command_has_force_option(): void
    {
        $reflection = new \ReflectionClass(HubSeedCommand::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertStringContainsString('--force', $defaults['signature']);
    }

    // --- Pattern Seeding ---

    public function test_seeds_patterns_from_registry(): void
    {
        User::factory()->create();

        $this->artisan('aicl:hub-seed')
            ->assertSuccessful();

        $this->assertGreaterThan(0, RlmPattern::query()->count());
    }

    public function test_pattern_seeding_is_idempotent(): void
    {
        User::factory()->create();

        $this->artisan('aicl:hub-seed')->assertSuccessful();
        $countFirst = RlmPattern::query()->count();

        $this->artisan('aicl:hub-seed')->assertSuccessful();
        $countSecond = RlmPattern::query()->count();

        $this->assertEquals($countFirst, $countSecond);
    }

    public function test_seeded_patterns_have_registry_source(): void
    {
        User::factory()->create();

        $this->artisan('aicl:hub-seed')->assertSuccessful();

        $this->assertEquals(
            RlmPattern::query()->count(),
            RlmPattern::query()->where('source', 'registry')->count(),
        );
    }

    // --- Base Failure Seeding ---

    public function test_seeds_base_failures(): void
    {
        User::factory()->create();

        $this->artisan('aicl:hub-seed')
            ->assertSuccessful();

        $this->assertGreaterThan(0, RlmFailure::query()->count());
    }

    public function test_base_failures_are_marked_promoted(): void
    {
        User::factory()->create();

        $this->artisan('aicl:hub-seed')->assertSuccessful();

        $allFailures = RlmFailure::query()->count();
        $promoted = RlmFailure::query()->where('promoted_to_base', true)->count();

        $this->assertEquals($allFailures, $promoted);
    }

    public function test_failure_seeding_is_idempotent(): void
    {
        User::factory()->create();

        $this->artisan('aicl:hub-seed')->assertSuccessful();
        $countFirst = RlmFailure::query()->count();

        $this->artisan('aicl:hub-seed')->assertSuccessful();
        $countSecond = RlmFailure::query()->count();

        $this->assertEquals($countFirst, $countSecond);
    }

    // --- Error Handling ---

    public function test_fails_when_no_users_exist(): void
    {
        $this->artisan('aicl:hub-seed')
            ->assertFailed();
    }

    // --- Hub Env Stub ---

    public function test_hub_env_stub_exists(): void
    {
        $stubPath = dirname(__DIR__, 2).'/stubs/hub-env.stub';

        $this->assertFileExists($stubPath);
    }

    public function test_hub_env_stub_contains_required_vars(): void
    {
        $stubPath = dirname(__DIR__, 2).'/stubs/hub-env.stub';
        $contents = file_get_contents($stubPath);

        $this->assertStringContainsString('AICL_RLM_HUB_ENABLED', $contents);
        $this->assertStringContainsString('AICL_HUB_ADMIN', $contents);
        $this->assertStringContainsString('AICL_HUB_SEARCH', $contents);
        $this->assertStringContainsString('ELASTICSEARCH_HOST', $contents);
        $this->assertStringContainsString('DB_CONNECTION=pgsql', $contents);
    }

    // --- Config Flag ---

    public function test_hub_admin_feature_flag_exists(): void
    {
        $this->assertNotNull(config('aicl.features.hub_admin'));
    }
}
