<?php

namespace Aicl\Tests\Unit\Horizon;

use Aicl\Horizon\Models\QueueMetricSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QueueMetricSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_exists_and_extends_eloquent(): void
    {
        $this->assertTrue(is_subclass_of(QueueMetricSnapshot::class, Model::class));
    }

    public function test_table_name_is_queue_metric_snapshots(): void
    {
        $model = new QueueMetricSnapshot;

        $this->assertSame('queue_metric_snapshots', $model->getTable());
    }

    public function test_timestamps_are_disabled(): void
    {
        $model = new QueueMetricSnapshot;

        $this->assertFalse($model->usesTimestamps());
    }

    public function test_fillable_attributes(): void
    {
        $model = new QueueMetricSnapshot;
        $expected = ['type', 'name', 'throughput', 'runtime', 'wait', 'recorded_at'];

        $this->assertEqualsCanonicalizing($expected, $model->getFillable());
    }

    public function test_casts_throughput_to_float(): void
    {
        $snapshot = QueueMetricSnapshot::factory()->create(['throughput' => '42.5']);

        $this->assertIsFloat($snapshot->fresh()->throughput);
        $this->assertSame(42.5, $snapshot->fresh()->throughput);
    }

    public function test_casts_runtime_to_float(): void
    {
        $snapshot = QueueMetricSnapshot::factory()->create(['runtime' => '123.45']);

        $this->assertIsFloat($snapshot->fresh()->runtime);
        $this->assertSame(123.45, $snapshot->fresh()->runtime);
    }

    public function test_casts_wait_to_float(): void
    {
        $snapshot = QueueMetricSnapshot::factory()->queue()->create(['wait' => '5.25']);

        $this->assertIsFloat($snapshot->fresh()->wait);
        $this->assertSame(5.25, $snapshot->fresh()->wait);
    }

    public function test_casts_recorded_at_to_datetime(): void
    {
        $snapshot = QueueMetricSnapshot::factory()->create();

        $this->assertInstanceOf(Carbon::class, $snapshot->fresh()->recorded_at);
    }

    public function test_scope_of_type_filters_by_type(): void
    {
        QueueMetricSnapshot::factory()->queue()->create();
        QueueMetricSnapshot::factory()->job()->create();

        $queues = QueueMetricSnapshot::query()->ofType('queue')->get();
        $jobs = QueueMetricSnapshot::query()->ofType('job')->get();

        $this->assertCount(1, $queues);
        $this->assertCount(1, $jobs);
        $this->assertSame('queue', $queues->first()->type);
        $this->assertSame('job', $jobs->first()->type);
    }

    public function test_scope_for_queue_filters_by_queue_name(): void
    {
        QueueMetricSnapshot::factory()->queue('default')->create();
        QueueMetricSnapshot::factory()->queue('high')->create();
        QueueMetricSnapshot::factory()->job()->create();

        $results = QueueMetricSnapshot::query()->forQueue('default')->get();

        $this->assertCount(1, $results);
        $this->assertSame('queue', $results->first()->type);
        $this->assertSame('default', $results->first()->name);
    }

    public function test_scope_for_job_filters_by_job_name(): void
    {
        $jobName = 'App\\Jobs\\ProcessPayment';
        QueueMetricSnapshot::factory()->job($jobName)->create();
        QueueMetricSnapshot::factory()->job('App\\Jobs\\SendNotification')->create();
        QueueMetricSnapshot::factory()->queue()->create();

        $results = QueueMetricSnapshot::query()->forJob($jobName)->get();

        $this->assertCount(1, $results);
        $this->assertSame('job', $results->first()->type);
        $this->assertSame($jobName, $results->first()->name);
    }

    public function test_scope_for_range_filters_by_minutes(): void
    {
        QueueMetricSnapshot::factory()->create(['recorded_at' => now()->subMinutes(30)]);
        QueueMetricSnapshot::factory()->create(['recorded_at' => now()->subMinutes(90)]);
        QueueMetricSnapshot::factory()->create(['recorded_at' => now()->subMinutes(150)]);

        $results = QueueMetricSnapshot::query()->forRange(60)->get();

        $this->assertCount(1, $results);
    }

    public function test_get_historical_data_combines_scopes(): void
    {
        QueueMetricSnapshot::factory()->queue('default')->create([
            'recorded_at' => now()->subMinutes(30),
        ]);
        QueueMetricSnapshot::factory()->queue('default')->create([
            'recorded_at' => now()->subMinutes(90),
        ]);
        QueueMetricSnapshot::factory()->queue('high')->create([
            'recorded_at' => now()->subMinutes(30),
        ]);

        $results = QueueMetricSnapshot::getHistoricalData('queue', 'default', 60);

        $this->assertCount(1, $results);
        $this->assertSame('default', $results->first()->name);
    }

    public function test_get_historical_data_orders_by_recorded_at(): void
    {
        QueueMetricSnapshot::factory()->queue('default')->create([
            'recorded_at' => now()->subMinutes(50),
        ]);
        QueueMetricSnapshot::factory()->queue('default')->create([
            'recorded_at' => now()->subMinutes(10),
        ]);
        QueueMetricSnapshot::factory()->queue('default')->create([
            'recorded_at' => now()->subMinutes(30),
        ]);

        $results = QueueMetricSnapshot::getHistoricalData('queue', 'default', 60);

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]->recorded_at->lt($results[1]->recorded_at));
        $this->assertTrue($results[1]->recorded_at->lt($results[2]->recorded_at));
    }

    public function test_factory_creates_valid_instance(): void
    {
        $snapshot = QueueMetricSnapshot::factory()->create();

        $this->assertDatabaseHas('queue_metric_snapshots', [
            'id' => $snapshot->id,
        ]);
        $this->assertContains($snapshot->type, ['queue', 'job']);
        $this->assertNotEmpty($snapshot->name);
        $this->assertNotNull($snapshot->throughput);
        $this->assertNotNull($snapshot->runtime);
        $this->assertNotNull($snapshot->recorded_at);
    }

    public function test_factory_queue_state(): void
    {
        $snapshot = QueueMetricSnapshot::factory()->queue()->create();

        $this->assertSame('queue', $snapshot->type);
        $this->assertNotNull($snapshot->wait);
    }

    public function test_factory_job_state(): void
    {
        $snapshot = QueueMetricSnapshot::factory()->job()->create();

        $this->assertSame('job', $snapshot->type);
        $this->assertNull($snapshot->wait);
    }
}
