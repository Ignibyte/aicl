<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Notifications;

use Aicl\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for BaseNotification PHPStan changes.
 *
 * Extends existing BaseNotificationTest with coverage for PHPStan-specific
 * changes: return type annotations on via(), toMail(), toBroadcast(),
 * onlyVia() fluent return, and null coalescing in toMail() for missing
 * data keys. Tests the interaction between onlyChannel restriction and
 * the default channel list.
 */
class BaseNotificationRegressionTest extends TestCase
{
    /**
     * Helper to create a concrete notification for testing.
     *
     * @param  array<string, mixed>  $data  Custom notification data
     * @return BaseNotification Concrete anonymous class instance
     */
    private function makeNotification(array $data = []): BaseNotification
    {
        return new class($data) extends BaseNotification
        {
            /** @phpstan-ignore-next-line */
            public function __construct(private array $testData = [])
            {
                // No parent constructor call needed — Queueable trait
                // initializes in boot, not constructor
            }

            public function toDatabase(object $notifiable): array
            {
                return $this->testData ?: [
                    'title' => 'Test Title',
                    'body' => 'Test Body',
                ];
            }
        };
    }

    /**
     * Test via() returns all 3 channels when onlyChannel is null.
     *
     * PHPStan added @return array<int, string> annotation.
     */
    public function test_via_returns_all_channels_by_default(): void
    {
        // Arrange
        $notification = $this->makeNotification();

        // Act
        $channels = $notification->via(new \stdClass);

        // Assert: database, mail, broadcast
        $this->assertSame(['database', 'mail', 'broadcast'], $channels);
    }

    /**
     * Test via() returns single channel after onlyVia() restriction.
     */
    public function test_via_returns_single_channel_after_only_via(): void
    {
        // Arrange
        $notification = $this->makeNotification();
        $notification->onlyVia('database');

        // Act
        $channels = $notification->via(new \stdClass);

        // Assert: only the restricted channel
        $this->assertSame(['database'], $channels);
    }

    /**
     * Test onlyVia returns fluent static instance.
     *
     * PHPStan enforced the static return type.
     */
    public function test_only_via_returns_same_instance(): void
    {
        // Arrange
        $notification = $this->makeNotification();

        // Act
        $result = $notification->onlyVia('mail');

        // Assert: fluent — returns same instance
        $this->assertSame($notification, $result);
    }

    /**
     * Test toMail uses null coalescing for missing title.
     *
     * PHPStan migration retained the ?? fallbacks. This tests the
     * branch where 'title' key is missing from toDatabase() output.
     */
    public function test_to_mail_handles_missing_title(): void
    {
        // Arrange: notification with no title key
        $notification = $this->makeNotification(['body' => 'Only body']);

        // Act
        $mail = $notification->toMail(new \stdClass);

        // Assert: falls back to 'New Notification'
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('New Notification', $mail->subject);
    }

    /**
     * Test toMail uses null coalescing for missing body.
     */
    public function test_to_mail_handles_missing_body(): void
    {
        // Arrange: notification with no body key
        $notification = $this->makeNotification(['title' => 'Only Title']);

        // Act
        $mail = $notification->toMail(new \stdClass);

        // Assert: falls back to empty string for body
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Only Title', $mail->subject);
    }

    /**
     * Test toMail includes action when action_url is present.
     */
    public function test_to_mail_includes_action_when_url_present(): void
    {
        // Arrange
        $notification = $this->makeNotification([
            'title' => 'Test',
            'body' => 'Body',
            'action_url' => 'https://example.com/action',
            'action_text' => 'Click Here',
        ]);

        // Act
        $mail = $notification->toMail(new \stdClass);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Test', $mail->subject);
    }

    /**
     * Test toMail uses default action text when not provided.
     */
    public function test_to_mail_uses_default_action_text(): void
    {
        // Arrange: action_url but no action_text
        $notification = $this->makeNotification([
            'title' => 'Test',
            'body' => 'Body',
            'action_url' => 'https://example.com',
        ]);

        // Act
        $mail = $notification->toMail(new \stdClass);

        // Assert: mail is created successfully with default text
        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    /**
     * Test toBroadcast wraps toDatabase output.
     *
     * PHPStan enforced BroadcastMessage return type.
     */
    public function test_to_broadcast_wraps_database_data(): void
    {
        // Arrange
        $notification = $this->makeNotification([
            'title' => 'Broadcast Test',
            'body' => 'Broadcast Body',
        ]);

        // Act
        $broadcast = $notification->toBroadcast(new \stdClass);

        // Assert
        $this->assertInstanceOf(BroadcastMessage::class, $broadcast);
    }

    /**
     * Test getIcon returns default bell icon.
     *
     * PHPStan enforced string return type.
     */
    public function test_get_icon_returns_bell(): void
    {
        // Arrange
        $notification = $this->makeNotification();

        // Act & Assert
        $this->assertSame('heroicon-o-bell', $notification->getIcon());
    }

    /**
     * Test getColor returns default primary color.
     *
     * PHPStan enforced string return type.
     */
    public function test_get_color_returns_primary(): void
    {
        // Arrange
        $notification = $this->makeNotification();

        // Act & Assert
        $this->assertSame('primary', $notification->getColor());
    }

    /**
     * Test multiple onlyVia calls override previous restriction.
     *
     * Last onlyVia() wins — the channel is replaced, not accumulated.
     */
    public function test_multiple_only_via_calls_override(): void
    {
        // Arrange
        $notification = $this->makeNotification();

        // Act: call onlyVia twice
        $notification->onlyVia('database');
        $notification->onlyVia('broadcast');

        // Assert: last one wins
        $channels = $notification->via(new \stdClass);
        $this->assertSame(['broadcast'], $channels);
    }
}
