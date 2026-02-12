<?php

namespace Aicl\Tests\Feature;

use Aicl\Models\FailedJob;
use Aicl\Services\LogParser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SystemUtilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\SettingsSeeder']);
    }

    public function test_failed_jobs_page_is_accessible_to_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/admin/failed-jobs');

        $response->assertStatus(200);
    }

    public function test_failed_jobs_page_is_not_accessible_to_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $response = $this->actingAs($user)->get('/admin/failed-jobs');

        $response->assertStatus(403);
    }

    public function test_queue_dashboard_is_accessible_to_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/admin/queue-dashboard');

        $response->assertStatus(200);
    }

    public function test_queue_dashboard_is_not_accessible_to_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $response = $this->actingAs($user)->get('/admin/queue-dashboard');

        $response->assertStatus(403);
    }

    public function test_log_viewer_is_accessible_to_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/admin/log-viewer');

        $response->assertStatus(200);
    }

    public function test_log_viewer_is_not_accessible_to_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $response = $this->actingAs($user)->get('/admin/log-viewer');

        $response->assertStatus(403);
    }

    public function test_settings_page_is_accessible_to_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/settings');

        $response->assertStatus(200);
    }

    public function test_settings_page_is_not_accessible_to_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/admin/settings');

        $response->assertStatus(403);
    }

    public function test_notification_center_is_accessible_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $response = $this->actingAs($user)->get('/admin/notifications');

        $response->assertStatus(200);
    }

    public function test_failed_job_model_parses_job_name(): void
    {
        $payload = json_encode([
            'displayName' => 'App\\Jobs\\ProcessPayment',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-123',
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => $payload,
            'exception' => 'Test exception message',
            'failed_at' => now(),
        ]);

        $job = FailedJob::first();

        $this->assertEquals('App\\Jobs\\ProcessPayment', $job->job_name);
    }

    public function test_failed_job_model_returns_exception_summary(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-456',
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => "RuntimeException: Something went wrong\n#0 /var/www/html/app/Jobs/Test.php(12): throw()\n#1 /var/www/html/vendor/laravel/framework",
            'failed_at' => now(),
        ]);

        $job = FailedJob::first();

        $this->assertEquals('RuntimeException: Something went wrong', $job->exception_summary);
    }

    public function test_log_parser_gets_log_files(): void
    {
        $logPath = storage_path('logs/test.log');
        File::put($logPath, "[2025-02-05 12:00:00] local.INFO: Test message\n");

        $parser = new LogParser;
        $files = $parser->getLogFiles();

        $this->assertNotEmpty($files);
        $this->assertContains('test.log', array_column($files, 'name'));

        File::delete($logPath);
    }

    public function test_log_parser_parses_log_entries(): void
    {
        $logContent = "[2025-02-05 12:00:00] local.INFO: Test info message\n[2025-02-05 12:00:01] local.ERROR: Test error message\n";
        $logPath = storage_path('logs/parse-test.log');
        File::put($logPath, $logContent);

        $parser = new LogParser;
        $entries = $parser->parseLogFile($logPath);

        $this->assertCount(2, $entries);
        // Entries are sorted descending by timestamp, so ERROR (later) comes first
        $this->assertEquals('ERROR', $entries->first()['level']);
        $this->assertEquals('INFO', $entries->last()['level']);

        File::delete($logPath);
    }

    public function test_log_parser_filters_by_level(): void
    {
        $logContent = "[2025-02-05 12:00:00] local.INFO: Info message\n[2025-02-05 12:00:01] local.ERROR: Error message\n[2025-02-05 12:00:02] local.INFO: Another info\n";
        $logPath = storage_path('logs/filter-test.log');
        File::put($logPath, $logContent);

        $parser = new LogParser;
        $entries = $parser->parseLogFile($logPath, 100, 'ERROR');

        $this->assertCount(1, $entries);
        $this->assertEquals('ERROR', $entries->first()['level']);

        File::delete($logPath);
    }

    public function test_log_parser_searches_messages(): void
    {
        $logContent = "[2025-02-05 12:00:00] local.INFO: User logged in\n[2025-02-05 12:00:01] local.INFO: Order processed\n[2025-02-05 12:00:02] local.ERROR: User session expired\n";
        $logPath = storage_path('logs/search-test.log');
        File::put($logPath, $logContent);

        $parser = new LogParser;
        $entries = $parser->parseLogFile($logPath, 100, null, 'User');

        $this->assertCount(2, $entries);

        File::delete($logPath);
    }

    public function test_log_parser_get_level_colors(): void
    {
        $parser = new LogParser;

        $this->assertEquals('danger', $parser->getLevelColor('ERROR'));
        $this->assertEquals('warning', $parser->getLevelColor('WARNING'));
        $this->assertEquals('info', $parser->getLevelColor('INFO'));
        $this->assertEquals('gray', $parser->getLevelColor('DEBUG'));
    }
}
