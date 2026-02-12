<?php

namespace Aicl\Tests\Feature;

use Aicl\Filament\Resources\FailedJobs\FailedJobResource;
use Aicl\Filament\Resources\FailedJobs\Pages\ViewFailedJob;
use Aicl\Models\FailedJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ViewFailedJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\SettingsSeeder']);

        $permissions = [
            'ViewAny:FailedJob', 'View:FailedJob', 'Create:FailedJob',
            'Update:FailedJob', 'Delete:FailedJob',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function createFailedJob(): FailedJob
    {
        return FailedJob::create([
            'uuid' => fake()->uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => ['displayName' => 'App\\Jobs\\TestJob', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call'],
            'exception' => "RuntimeException: Something went wrong\n#0 /app/test.php(10): test()\n#1 {main}",
            'failed_at' => now(),
        ]);
    }

    public function test_view_failed_job_page_renders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $job = $this->createFailedJob();

        Livewire::actingAs($user)
            ->test(ViewFailedJob::class, ['record' => $job->getKey()])
            ->assertSuccessful();
    }

    public function test_view_failed_job_displays_job_details(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $job = $this->createFailedJob();

        Livewire::actingAs($user)
            ->test(ViewFailedJob::class, ['record' => $job->getKey()])
            ->assertSee($job->uuid)
            ->assertSee('default');
    }

    public function test_view_failed_job_has_retry_action(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $job = $this->createFailedJob();

        Livewire::actingAs($user)
            ->test(ViewFailedJob::class, ['record' => $job->getKey()])
            ->assertActionExists('retry');
    }

    public function test_view_failed_job_has_delete_action(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $job = $this->createFailedJob();

        Livewire::actingAs($user)
            ->test(ViewFailedJob::class, ['record' => $job->getKey()])
            ->assertActionExists('delete');
    }

    public function test_view_failed_job_resource_class(): void
    {
        $this->assertEquals(FailedJobResource::class, ViewFailedJob::getResource());
    }
}
