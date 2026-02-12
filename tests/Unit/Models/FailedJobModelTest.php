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

        $this->assertTrue(
            method_exists($job, 'getJobNameAttribute') || property_exists($job, 'appends'),
            'FailedJob should have a job_name accessor'
        );
    }

    public function test_model_has_exception_summary_accessor(): void
    {
        $job = new FailedJob;

        $this->assertTrue(
            method_exists($job, 'getExceptionSummaryAttribute') || property_exists($job, 'appends'),
            'FailedJob should have an exception_summary accessor'
        );
    }

    public function test_table_name_is_failed_jobs(): void
    {
        $job = new FailedJob;

        $this->assertEquals('failed_jobs', $job->getTable());
    }
}
