<?php

namespace Aicl\Tests\Feature;

use Aicl\Models\FailedJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FailedJobModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

    }

    // ─── getJobNameAttribute ──────────────────────────────────

    public function test_job_name_from_display_name(): void
    {
        $job = FailedJob::create([
            'uuid' => fake()->uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => ['displayName' => 'App\\Jobs\\SendWelcomeEmail', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call'],
            'exception' => 'RuntimeException: test',
            'failed_at' => now(),
        ]);

        $this->assertEquals('App\\Jobs\\SendWelcomeEmail', $job->job_name);
    }

    public function test_job_name_falls_back_to_job_key(): void
    {
        $job = FailedJob::create([
            'uuid' => fake()->uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => ['job' => 'Illuminate\\Queue\\CallQueuedHandler@call'],
            'exception' => 'RuntimeException: test',
            'failed_at' => now(),
        ]);

        $this->assertEquals('Illuminate\\Queue\\CallQueuedHandler@call', $job->job_name);
    }

    public function test_job_name_returns_unknown_when_no_keys(): void
    {
        $job = FailedJob::create([
            'uuid' => fake()->uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => ['data' => 'some-data'],
            'exception' => 'RuntimeException: test',
            'failed_at' => now(),
        ]);

        $this->assertEquals('Unknown Job', $job->job_name);
    }

    public function test_job_name_from_string_payload(): void
    {
        $job = new FailedJob;
        $job->uuid = fake()->uuid();
        $job->connection = 'redis';
        $job->queue = 'default';
        // Force string payload by setting raw attribute
        $job->setRawAttributes([
            'uuid' => fake()->uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ProcessOrder']),
            'exception' => 'RuntimeException: test',
            'failed_at' => now()->toDateTimeString(),
        ]);

        $this->assertEquals('App\\Jobs\\ProcessOrder', $job->job_name);
    }

    // ─── getExceptionSummaryAttribute ──────────────────────────

    public function test_exception_summary_returns_first_line(): void
    {
        $job = FailedJob::create([
            'uuid' => fake()->uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => ['displayName' => 'TestJob'],
            'exception' => "RuntimeException: Something went wrong\n#0 /app/Jobs/TestJob.php(42): doSomething()\n#1 {main}",
            'failed_at' => now(),
        ]);

        $this->assertEquals('RuntimeException: Something went wrong', $job->exception_summary);
    }

    public function test_exception_summary_with_single_line_exception(): void
    {
        $job = FailedJob::create([
            'uuid' => fake()->uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => ['displayName' => 'TestJob'],
            'exception' => 'TypeError: Argument #1 must be of type int, string given',
            'failed_at' => now(),
        ]);

        $this->assertEquals('TypeError: Argument #1 must be of type int, string given', $job->exception_summary);
    }

    public function test_exception_summary_with_empty_exception(): void
    {
        $job = FailedJob::create([
            'uuid' => fake()->uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => ['displayName' => 'TestJob'],
            'exception' => '',
            'failed_at' => now(),
        ]);

        $this->assertEquals('', $job->exception_summary);
    }

    // ─── Casts ────────────────────────────────────────────────

    public function test_failed_at_is_cast_to_datetime(): void
    {
        $job = FailedJob::create([
            'uuid' => fake()->uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => ['displayName' => 'TestJob'],
            'exception' => 'RuntimeException: test',
            'failed_at' => now(),
        ]);

        $fresh = $job->fresh();
        $this->assertInstanceOf(Carbon::class, $fresh->failed_at);
    }

    public function test_payload_is_cast_to_array(): void
    {
        $job = FailedJob::create([
            'uuid' => fake()->uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => ['displayName' => 'TestJob', 'data' => ['key' => 'value']],
            'exception' => 'RuntimeException: test',
            'failed_at' => now(),
        ]);

        $fresh = $job->fresh();
        $this->assertIsArray($fresh->payload);
        $this->assertEquals('TestJob', $fresh->payload['displayName']);
    }

    // ─── Model Config ─────────────────────────────────────────

    public function test_uses_failed_jobs_table(): void
    {
        $job = new FailedJob;
        $this->assertEquals('failed_jobs', $job->getTable());
    }

    public function test_timestamps_are_disabled(): void
    {
        $job = new FailedJob;
        $this->assertFalse($job->usesTimestamps());
    }
}
