<?php

namespace Aicl\Tests\Feature\Notifications;

use Aicl\Models\NotificationLog;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Enums\DeliveryStatus;
use Aicl\Notifications\Models\NotificationChannel;
use Aicl\Notifications\Models\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationChannelModelTest extends TestCase
{
    use RefreshDatabase;

    private function createChannel(array $attributes = []): NotificationChannel
    {
        return NotificationChannel::create(array_merge([
            'name' => 'Test Channel',
            'slug' => 'test-channel-'.uniqid(),
            'type' => ChannelType::Slack,
            'config' => ['webhook_url' => 'https://hooks.slack.com/test'],
            'is_active' => true,
        ], $attributes));
    }

    // ─── Creation & Attributes ─────────────────────────────────

    public function test_create_notification_channel(): void
    {
        $channel = $this->createChannel([
            'name' => 'Production Slack',
            'slug' => 'production-slack',
        ]);

        $this->assertNotNull($channel->id);
        $this->assertSame('Production Slack', $channel->name);
        $this->assertSame('production-slack', $channel->slug);
    }

    public function test_uses_uuid_primary_key(): void
    {
        $channel = $this->createChannel();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $channel->id
        );
    }

    public function test_slug_is_unique(): void
    {
        $this->createChannel(['slug' => 'unique-slug']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->createChannel(['slug' => 'unique-slug']);
    }

    // ─── Casts ─────────────────────────────────────────────────

    public function test_type_is_cast_to_channel_type_enum(): void
    {
        $channel = $this->createChannel(['type' => ChannelType::Email]);
        $fresh = $channel->fresh();

        $this->assertInstanceOf(ChannelType::class, $fresh->type);
        $this->assertSame(ChannelType::Email, $fresh->type);
    }

    public function test_config_is_encrypted_array(): void
    {
        $config = ['webhook_url' => 'https://hooks.slack.com/secret', 'api_key' => 'supersecret'];
        $channel = $this->createChannel(['config' => $config]);
        $fresh = $channel->fresh();

        $this->assertIsArray($fresh->config);
        $this->assertSame('https://hooks.slack.com/secret', $fresh->config['webhook_url']);
        $this->assertSame('supersecret', $fresh->config['api_key']);
    }

    public function test_config_is_stored_encrypted_in_database(): void
    {
        $channel = $this->createChannel([
            'config' => ['secret_key' => 'my-secret-value'],
        ]);

        // Read raw from database — should not contain plaintext
        $raw = \Illuminate\Support\Facades\DB::table('notification_channels')
            ->where('id', $channel->id)
            ->value('config');

        $this->assertStringNotContainsString('my-secret-value', $raw);
    }

    public function test_message_templates_is_cast_to_array(): void
    {
        $templates = [
            'App\\Notifications\\TestNotification' => [
                'title' => 'Custom: {{ title }}',
                'body' => 'Custom: {{ body }}',
            ],
        ];

        $channel = $this->createChannel(['message_templates' => $templates]);
        $fresh = $channel->fresh();

        $this->assertIsArray($fresh->message_templates);
        $this->assertArrayHasKey('App\\Notifications\\TestNotification', $fresh->message_templates);
    }

    public function test_rate_limit_is_cast_to_array(): void
    {
        $channel = $this->createChannel(['rate_limit' => ['max' => 10, 'period' => '1m']]);
        $fresh = $channel->fresh();

        $this->assertIsArray($fresh->rate_limit);
        $this->assertSame(10, $fresh->rate_limit['max']);
        $this->assertSame('1m', $fresh->rate_limit['period']);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $channel = $this->createChannel(['is_active' => true]);
        $fresh = $channel->fresh();

        $this->assertIsBool($fresh->is_active);
        $this->assertTrue($fresh->is_active);
    }

    // ─── Scopes ────────────────────────────────────────────────

    public function test_scope_active(): void
    {
        $this->createChannel(['is_active' => true, 'slug' => 'active-channel']);
        $this->createChannel(['is_active' => false, 'slug' => 'inactive-channel']);

        $activeChannels = NotificationChannel::active()->get();

        $this->assertCount(1, $activeChannels);
        $this->assertSame('active-channel', $activeChannels->first()->slug);
    }

    public function test_scope_of_type(): void
    {
        $this->createChannel(['type' => ChannelType::Slack, 'slug' => 'slack-channel']);
        $this->createChannel(['type' => ChannelType::Email, 'slug' => 'email-channel']);
        $this->createChannel(['type' => ChannelType::Slack, 'slug' => 'slack-channel-2']);

        $slackChannels = NotificationChannel::ofType(ChannelType::Slack)->get();

        $this->assertCount(2, $slackChannels);
    }

    public function test_scopes_can_be_chained(): void
    {
        $this->createChannel(['type' => ChannelType::Slack, 'is_active' => true, 'slug' => 'active-slack']);
        $this->createChannel(['type' => ChannelType::Slack, 'is_active' => false, 'slug' => 'inactive-slack']);
        $this->createChannel(['type' => ChannelType::Email, 'is_active' => true, 'slug' => 'active-email']);

        $activeSlack = NotificationChannel::active()->ofType(ChannelType::Slack)->get();

        $this->assertCount(1, $activeSlack);
        $this->assertSame('active-slack', $activeSlack->first()->slug);
    }

    // ─── Relationships ─────────────────────────────────────────

    public function test_delivery_logs_relationship(): void
    {
        $user = User::factory()->create();
        $channel = $this->createChannel();

        $notificationLog = NotificationLog::create([
            'type' => 'Test\\Notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['slack'],
            'channel_status' => ['slack' => 'sent'],
            'data' => ['message' => 'test'],
        ]);

        NotificationDeliveryLog::create([
            'notification_log_id' => $notificationLog->id,
            'channel_id' => $channel->id,
            'status' => DeliveryStatus::Sent,
            'payload' => ['title' => 'Test'],
        ]);

        $this->assertCount(1, $channel->deliveryLogs);
        $this->assertInstanceOf(NotificationDeliveryLog::class, $channel->deliveryLogs->first());
    }

    // ─── getTemplate ───────────────────────────────────────────

    public function test_get_template_returns_exact_match(): void
    {
        $channel = $this->createChannel([
            'message_templates' => [
                'App\\Notifications\\OrderCreated' => [
                    'title' => 'New Order: {{ title }}',
                    'body' => '{{ body }}',
                ],
            ],
        ]);

        $template = $channel->getTemplate('App\\Notifications\\OrderCreated');

        $this->assertNotNull($template);
        $this->assertSame('New Order: {{ title }}', $template['title']);
    }

    public function test_get_template_falls_back_to_default(): void
    {
        $channel = $this->createChannel([
            'message_templates' => [
                '_default' => [
                    'title' => 'Default: {{ title }}',
                    'body' => '{{ body }}',
                ],
            ],
        ]);

        $template = $channel->getTemplate('App\\Notifications\\UnknownNotification');

        $this->assertNotNull($template);
        $this->assertSame('Default: {{ title }}', $template['title']);
    }

    public function test_get_template_returns_null_when_no_match_and_no_default(): void
    {
        $channel = $this->createChannel([
            'message_templates' => [
                'App\\Notifications\\SpecificOne' => [
                    'title' => 'Specific',
                    'body' => 'body',
                ],
            ],
        ]);

        $template = $channel->getTemplate('App\\Notifications\\Other');

        $this->assertNull($template);
    }

    public function test_get_template_returns_null_when_no_templates(): void
    {
        $channel = $this->createChannel(['message_templates' => null]);

        $template = $channel->getTemplate('App\\Notifications\\Any');

        $this->assertNull($template);
    }

    public function test_get_template_prefers_exact_over_default(): void
    {
        $channel = $this->createChannel([
            'message_templates' => [
                'App\\Notifications\\OrderCreated' => [
                    'title' => 'Exact Match',
                    'body' => 'exact',
                ],
                '_default' => [
                    'title' => 'Default',
                    'body' => 'default',
                ],
            ],
        ]);

        $template = $channel->getTemplate('App\\Notifications\\OrderCreated');

        $this->assertSame('Exact Match', $template['title']);
    }
}
