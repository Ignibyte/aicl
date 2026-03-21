<?php

namespace Aicl\Tests\Unit\Models;

use Aicl\Models\FailedJob;
use PHPUnit\Framework\TestCase;

class FailedJobModelTest extends TestCase
{
    public function test_model_exists(): void
    {
        $this->assertTrue(class_exists(FailedJob::class));
    }

    public function test_model_has_job_name_accessor(): void
    {
        $job = new FailedJob;
        $ref = new \ReflectionClass($job);

        $this->assertTrue(
            $ref->hasMethod('getJobNameAttribute') || $ref->hasProperty('appends'),
            'FailedJob should have a job_name accessor'
        );
    }

    public function test_model_has_exception_summary_accessor(): void
    {
        $job = new FailedJob;
        $ref = new \ReflectionClass($job);

        $this->assertTrue(
            $ref->hasMethod('getExceptionSummaryAttribute') || $ref->hasProperty('appends'),
            'FailedJob should have an exception_summary accessor'
        );
    }

    public function test_table_name_is_failed_jobs(): void
    {
        $job = new FailedJob;

        $this->assertEquals('failed_jobs', $job->getTable());
    }
}
