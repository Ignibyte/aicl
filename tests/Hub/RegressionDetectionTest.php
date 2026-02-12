<?php

namespace Aicl\Tests\Hub;

use Aicl\Models\FailureReport;
use Aicl\Models\RlmFailure;
use Aicl\Notifications\FailureRegressionNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegressionDetectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_regression_notification_sent_when_fixed_failure_reappears(): void
    {
        Notification::fake();

        $failure = RlmFailure::factory()->create([
            'scaffolding_fixed' => true,
            'report_count' => 0,
            'owner_id' => $this->admin->id,
        ]);

        FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => $this->admin->id,
        ]);

        Notification::assertSentTo($this->admin, FailureRegressionNotification::class);
    }

    public function test_no_regression_notification_for_unfixed_failure(): void
    {
        Notification::fake();

        $failure = RlmFailure::factory()->create([
            'scaffolding_fixed' => false,
            'report_count' => 0,
            'owner_id' => $this->admin->id,
        ]);

        FailureReport::factory()->create([
            'rlm_failure_id' => $failure->id,
            'owner_id' => $this->admin->id,
        ]);

        Notification::assertNotSentTo($this->admin, FailureRegressionNotification::class);
    }

    public function test_regression_notification_has_correct_content(): void
    {
        $notification = new FailureRegressionNotification(
            failure: RlmFailure::factory()->make(['failure_code' => 'BF-TEST', 'title' => 'Test regression']),
            report: FailureReport::factory()->make(['entity_name' => 'Project']),
        );

        $this->assertEquals('danger', $notification->getColor());
        $this->assertEquals('heroicon-o-exclamation-triangle', $notification->getIcon());
    }

    public function test_regression_notification_database_payload(): void
    {
        $failure = RlmFailure::factory()->make([
            'failure_code' => 'BF-123',
            'title' => 'Missing timestamps',
        ]);
        $report = FailureReport::factory()->make([
            'entity_name' => 'Invoice',
        ]);

        $notification = new FailureRegressionNotification($failure, $report);
        $data = $notification->toDatabase($this->admin);

        $this->assertEquals('Regression detected', $data['title']);
        $this->assertStringContainsString('BF-123', $data['body']);
        $this->assertStringContainsString('Invoice', $data['body']);
        $this->assertStringContainsString('Previously-fixed', $data['body']);
    }
}
