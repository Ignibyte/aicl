<?php

namespace Aicl\Tests\Feature\Horizon;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Contracts\TagRepository;
use Aicl\Horizon\Livewire\CompletedJobsTable;
use Aicl\Horizon\Livewire\FailedJobsTable;
use Aicl\Horizon\Livewire\MonitoredTagsTable;
use Aicl\Horizon\Livewire\PendingJobsTable;
use Aicl\Horizon\Livewire\RecentJobsTable;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class LivewireComponentsTest extends TestCase
{
    // ─── Class Structure ──────────────────────────────────

    public function test_recent_jobs_table_extends_livewire_component(): void
    {
        $this->assertTrue(is_subclass_of(RecentJobsTable::class, Component::class));
    }

    public function test_pending_jobs_table_extends_livewire_component(): void
    {
        $this->assertTrue(is_subclass_of(PendingJobsTable::class, Component::class));
    }

    public function test_completed_jobs_table_extends_livewire_component(): void
    {
        $this->assertTrue(is_subclass_of(CompletedJobsTable::class, Component::class));
    }

    public function test_failed_jobs_table_extends_livewire_component(): void
    {
        $this->assertTrue(is_subclass_of(FailedJobsTable::class, Component::class));
    }

    public function test_monitored_tags_table_extends_livewire_component(): void
    {
        $this->assertTrue(is_subclass_of(MonitoredTagsTable::class, Component::class));
    }

    // ─── RecentJobsTable ──────────────────────────────────

    public function test_recent_jobs_table_renders(): void
    {
        $jobRepo = Mockery::mock(JobRepository::class);
        $jobRepo->shouldReceive('getRecent')->andReturn(new Collection);
        app()->instance(JobRepository::class, $jobRepo);

        Livewire::test(RecentJobsTable::class)
            ->assertStatus(200)
            ->assertSee('No recent jobs.');
    }

    public function test_recent_jobs_table_shows_jobs(): void
    {
        $job = (object) [
            'id' => '1',
            'name' => 'App\\Jobs\\SendWelcomeEmail',
            'queue' => 'default',
            'status' => 'completed',
            'payload' => (object) ['tags' => []],
            'completed_at' => 1700000100000,
            'reserved_at' => 1700000099000,
        ];

        $jobRepo = Mockery::mock(JobRepository::class);
        $jobRepo->shouldReceive('getRecent')->andReturn(new Collection([$job]));
        app()->instance(JobRepository::class, $jobRepo);

        Livewire::test(RecentJobsTable::class)
            ->assertStatus(200)
            ->assertSee('SendWelcomeEmail')
            ->assertSee('default');
    }

    // ─── PendingJobsTable ──────────────────────────────────

    public function test_pending_jobs_table_renders(): void
    {
        $jobRepo = Mockery::mock(JobRepository::class);
        $jobRepo->shouldReceive('getPending')->andReturn(new Collection);
        app()->instance(JobRepository::class, $jobRepo);

        Livewire::test(PendingJobsTable::class)
            ->assertStatus(200)
            ->assertSee('No pending jobs.');
    }

    // ─── CompletedJobsTable ──────────────────────────────────

    public function test_completed_jobs_table_renders(): void
    {
        $jobRepo = Mockery::mock(JobRepository::class);
        $jobRepo->shouldReceive('getCompleted')->andReturn(new Collection);
        app()->instance(JobRepository::class, $jobRepo);

        Livewire::test(CompletedJobsTable::class)
            ->assertStatus(200)
            ->assertSee('No completed jobs.');
    }

    // ─── FailedJobsTable ──────────────────────────────────

    public function test_failed_jobs_table_renders(): void
    {
        $jobRepo = Mockery::mock(JobRepository::class);
        $jobRepo->shouldReceive('getFailed')->andReturn(new Collection);
        app()->instance(JobRepository::class, $jobRepo);

        Livewire::test(FailedJobsTable::class)
            ->assertStatus(200)
            ->assertSee('No failed jobs');
    }

    public function test_failed_jobs_table_shows_failed_jobs(): void
    {
        $job = (object) [
            'id' => 'abc-123',
            'name' => 'App\\Jobs\\ProcessPayment',
            'queue' => 'high',
            'exception' => 'RuntimeException: Payment gateway timeout',
            'failed_at' => time(),
        ];

        $jobRepo = Mockery::mock(JobRepository::class);
        $jobRepo->shouldReceive('getFailed')->andReturn(new Collection([$job]));
        app()->instance(JobRepository::class, $jobRepo);

        Livewire::test(FailedJobsTable::class)
            ->assertStatus(200)
            ->assertSee('ProcessPayment')
            ->assertSee('high')
            ->assertSee('Payment gateway timeout');
    }

    // ─── MonitoredTagsTable ──────────────────────────────────

    public function test_monitored_tags_table_renders(): void
    {
        $tagRepo = Mockery::mock(TagRepository::class);
        $tagRepo->shouldReceive('monitoring')->andReturn([]);
        app()->instance(TagRepository::class, $tagRepo);

        Livewire::test(MonitoredTagsTable::class)
            ->assertStatus(200)
            ->assertSee('No tags being monitored');
    }

    public function test_monitored_tags_table_shows_tags(): void
    {
        $tagRepo = Mockery::mock(TagRepository::class);
        $tagRepo->shouldReceive('monitoring')->andReturn(['App\\Models\\User:42']);
        app()->instance(TagRepository::class, $tagRepo);

        Livewire::test(MonitoredTagsTable::class)
            ->assertStatus(200)
            ->assertSee('App\\Models\\User:42');
    }

    public function test_monitored_tags_has_new_tag_property(): void
    {
        $tagRepo = Mockery::mock(TagRepository::class);
        $tagRepo->shouldReceive('monitoring')->andReturn([]);
        app()->instance(TagRepository::class, $tagRepo);

        Livewire::test(MonitoredTagsTable::class)
            ->assertSet('newTag', '');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
