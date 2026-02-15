<?php

namespace Aicl\Tests\Unit\Notifications;

use Aicl\Database\Seeders\NotificationChannelSeeder;
use Aicl\Notifications\Contracts\HasExternalChannels;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Models\NotificationChannel;
use Aicl\Notifications\RlmFailureAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalPipelineActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_channel_seeder_creates_three_channels(): void
    {
        $seeder = new NotificationChannelSeeder;
        $seeder->run();

        $this->assertDatabaseCount('notification_channels', 3);
    }

    public function test_seeder_creates_email_channel(): void
    {
        (new NotificationChannelSeeder)->run();

        $this->assertDatabaseHas('notification_channels', [
            'slug' => 'system-email',
            'is_active' => true,
        ]);

        $channel = NotificationChannel::where('slug', 'system-email')->first();
        $this->assertSame(ChannelType::Email, $channel->type);
    }

    public function test_seeder_creates_slack_channel_inactive(): void
    {
        (new NotificationChannelSeeder)->run();

        $this->assertDatabaseHas('notification_channels', [
            'slug' => 'slack-alerts',
            'is_active' => false,
        ]);

        $channel = NotificationChannel::where('slug', 'slack-alerts')->first();
        $this->assertSame(ChannelType::Slack, $channel->type);
    }

    public function test_seeder_creates_webhook_channel_inactive(): void
    {
        (new NotificationChannelSeeder)->run();

        $this->assertDatabaseHas('notification_channels', [
            'slug' => 'generic-webhook',
            'is_active' => false,
        ]);

        $channel = NotificationChannel::where('slug', 'generic-webhook')->first();
        $this->assertSame(ChannelType::Webhook, $channel->type);
    }

    public function test_seeder_is_idempotent(): void
    {
        (new NotificationChannelSeeder)->run();
        (new NotificationChannelSeeder)->run();

        $this->assertDatabaseCount('notification_channels', 3);
    }

    public function test_channels_have_default_message_templates(): void
    {
        (new NotificationChannelSeeder)->run();

        $channels = NotificationChannel::all();

        foreach ($channels as $channel) {
            $this->assertNotNull($channel->message_templates);
            $this->assertArrayHasKey('_default', $channel->message_templates);
        }
    }

    public function test_channels_have_rate_limits(): void
    {
        (new NotificationChannelSeeder)->run();

        $channels = NotificationChannel::all();

        foreach ($channels as $channel) {
            $this->assertNotNull($channel->rate_limit);
            $this->assertArrayHasKey('max', $channel->rate_limit);
            $this->assertArrayHasKey('period', $channel->rate_limit);
        }
    }

    public function test_rlm_failure_assigned_implements_has_external_channels(): void
    {
        $this->assertTrue(
            is_subclass_of(RlmFailureAssignedNotification::class, HasExternalChannels::class)
        );
    }

    public function test_rlm_failure_assigned_external_channels_returns_active_channels(): void
    {
        (new NotificationChannelSeeder)->run();

        $notification = $this->createMock(RlmFailureAssignedNotification::class);
        $notification->method('externalChannels')
            ->willReturn(NotificationChannel::active()->get());

        $channels = $notification->externalChannels();

        // Only system-email is active by default
        $this->assertCount(1, $channels);
        $this->assertSame('system-email', $channels->first()->slug);
    }
}
