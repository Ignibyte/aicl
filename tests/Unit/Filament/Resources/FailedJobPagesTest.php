<?php

namespace Aicl\Tests\Unit\Filament\Resources;

use Aicl\Filament\Resources\FailedJobs\FailedJobResource;
use Aicl\Filament\Resources\FailedJobs\FailedJobsTable;
use Aicl\Filament\Resources\FailedJobs\Pages\ListFailedJobs;
use Aicl\Filament\Resources\FailedJobs\Pages\ViewFailedJob;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;
use PHPUnit\Framework\TestCase;

class FailedJobPagesTest extends TestCase
{
    // ─── FailedJobsTable ───────────────────────────────────────

    public function test_failed_jobs_table_exists(): void
    {
        $this->assertTrue(class_exists(FailedJobsTable::class));
    }

    public function test_failed_jobs_table_has_configure_method(): void
    {
        $this->assertTrue(method_exists(FailedJobsTable::class, 'configure'));

        $reflection = new \ReflectionMethod(FailedJobsTable::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    // ─── ListFailedJobs ────────────────────────────────────────

    public function test_list_failed_jobs_extends_list_records(): void
    {
        $this->assertTrue(is_subclass_of(ListFailedJobs::class, ListRecords::class));
    }

    public function test_list_failed_jobs_resource(): void
    {
        $reflection = new \ReflectionClass(ListFailedJobs::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(FailedJobResource::class, $defaults['resource']);
    }

    public function test_list_failed_jobs_has_header_actions(): void
    {
        $this->assertTrue(method_exists(ListFailedJobs::class, 'getHeaderActions'));
    }

    // ─── ViewFailedJob ─────────────────────────────────────────

    public function test_view_failed_job_extends_view_record(): void
    {
        $this->assertTrue(is_subclass_of(ViewFailedJob::class, ViewRecord::class));
    }

    public function test_view_failed_job_resource(): void
    {
        $reflection = new \ReflectionClass(ViewFailedJob::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(FailedJobResource::class, $defaults['resource']);
    }

    public function test_view_failed_job_has_infolist(): void
    {
        $this->assertTrue(method_exists(ViewFailedJob::class, 'infolist'));
    }

    public function test_view_failed_job_has_header_actions(): void
    {
        $this->assertTrue(method_exists(ViewFailedJob::class, 'getHeaderActions'));
    }

    // ─── FailedJobResource extended checks ─────────────────────

    public function test_cannot_edit(): void
    {
        $reflection = new \ReflectionMethod(FailedJobResource::class, 'canEdit');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_has_can_access_method(): void
    {
        $this->assertTrue(method_exists(FailedJobResource::class, 'canAccess'));
    }
}
