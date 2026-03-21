<?php

namespace Aicl\Tests\Unit\Notifications;

use Aicl\Notifications\BaseNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\TestCase;

class BaseNotificationTest extends TestCase
{
    /** @phpstan-ignore-next-line */
    private function createConcreteNotification(array $data = []): BaseNotification
    {
        return new class($data) extends BaseNotification
        {
            /** @phpstan-ignore-next-line */
            public function __construct(private array $testData = []) {}

            public function toDatabase(object $notifiable): array
            {
                return $this->testData ?: [
                    'title' => 'Test Notification',
                    'body' => 'This is a test',
                    'icon' => 'heroicon-o-bell',
                    'color' => 'primary',
                    'action_url' => 'https://example.com',
                    'action_text' => 'View',
                ];
            }
        };
    }

    public function test_base_notification_is_abstract(): void
    {
        $reflection = new \ReflectionClass(BaseNotification::class);

        $this->assertTrue($reflection->isAbstract());
    }

    public function test_base_notification_extends_notification(): void
    {
        $notification = $this->createConcreteNotification();

        $this->assertInstanceOf(Notification::class, $notification);
    }

    public function test_base_notification_implements_should_queue(): void
    {
        $notification = $this->createConcreteNotification();

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    public function test_base_notification_uses_queueable_trait(): void
    {
        $traits = class_uses(BaseNotification::class);

        $this->assertContains(Queueable::class, $traits);
    }

    public function test_via_returns_all_channels_by_default(): void
    {
        $notification = $this->createConcreteNotification();
        $notifiable = new \stdClass;

        $channels = $notification->via($notifiable);

        $this->assertEquals(['database', 'mail', 'broadcast'], $channels);
    }

    public function test_only_via_restricts_to_single_channel(): void
    {
        $notification = $this->createConcreteNotification();
        $notifiable = new \stdClass;

        $notification->onlyVia('database');

        $this->assertEquals(['database'], $notification->via($notifiable));
    }

    public function test_only_via_returns_fluent_self(): void
    {
        $notification = $this->createConcreteNotification();
        $result = $notification->onlyVia('mail');

        $this->assertSame($notification, $result);
    }

    public function test_to_mail_returns_mail_message(): void
    {
        $notification = $this->createConcreteNotification();
        $notifiable = new \stdClass;

        $mail = $notification->toMail($notifiable);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    public function test_to_mail_uses_title_as_subject(): void
    {
        $notification = $this->createConcreteNotification([
            'title' => 'Custom Subject',
            'body' => 'Test body',
        ]);

        $mail = $notification->toMail(new \stdClass);

        $this->assertEquals('Custom Subject', $mail->subject);
    }

    public function test_to_broadcast_returns_broadcast_message(): void
    {
        $notification = $this->createConcreteNotification();
        $notifiable = new \stdClass;

        $broadcast = $notification->toBroadcast($notifiable);

        $this->assertInstanceOf(BroadcastMessage::class, $broadcast);
    }

    public function test_get_icon_returns_default_bell(): void
    {
        $notification = $this->createConcreteNotification();

        $this->assertEquals('heroicon-o-bell', $notification->getIcon());
    }

    public function test_get_color_returns_default_primary(): void
    {
        $notification = $this->createConcreteNotification();

        $this->assertEquals('primary', $notification->getColor());
    }

    public function test_to_database_is_abstract(): void
    {
        $reflection = new \ReflectionClass(BaseNotification::class);
        $method = $reflection->getMethod('toDatabase');

        $this->assertTrue($method->isAbstract());
    }
}
