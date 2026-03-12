<?php

namespace Aicl\Tests\Feature\Horizon;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\Contracts\TagRepository;
use Aicl\Horizon\Contracts\WorkloadRepository;
use Aicl\Horizon\HorizonServiceProvider;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    public function test_horizon_provider_is_registered_when_feature_enabled(): void
    {
        config(['aicl.features.horizon' => true]);

        $this->assertTrue(
            collect(app()->getLoadedProviders())
                ->keys()
                ->contains(HorizonServiceProvider::class)
        );
    }

    public function test_job_repository_is_bound_when_horizon_enabled(): void
    {
        config(['aicl.features.horizon' => true]);

        $this->assertTrue(app()->bound(JobRepository::class));
    }

    public function test_metrics_repository_is_bound_when_horizon_enabled(): void
    {
        config(['aicl.features.horizon' => true]);

        $this->assertTrue(app()->bound(MetricsRepository::class));
    }

    public function test_tag_repository_is_bound_when_horizon_enabled(): void
    {
        config(['aicl.features.horizon' => true]);

        $this->assertTrue(app()->bound(TagRepository::class));
    }

    public function test_supervisor_repository_is_bound_when_horizon_enabled(): void
    {
        config(['aicl.features.horizon' => true]);

        $this->assertTrue(app()->bound(SupervisorRepository::class));
    }

    public function test_workload_repository_is_bound_when_horizon_enabled(): void
    {
        config(['aicl.features.horizon' => true]);

        $this->assertTrue(app()->bound(WorkloadRepository::class));
    }

    public function test_horizon_config_is_merged(): void
    {
        $this->assertNotNull(config('aicl-horizon'));
        $this->assertNotNull(config('aicl-horizon.use'));
        $this->assertNotNull(config('aicl-horizon.prefix'));
    }

    public function test_horizon_feature_flag_defaults_to_true(): void
    {
        // The feature flag defaults to true (enabled) via env fallback
        $this->assertTrue(config('aicl.features.horizon', true));
    }
}
