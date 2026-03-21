<?php

namespace Aicl\Tests\Unit\Horizon;

use Aicl\Horizon\Contracts\LongWaitDetectedNotification;
use Aicl\Horizon\Notifications\LongWaitDetected;
use PHPUnit\Framework\TestCase;

class LongWaitDetectedNotificationTest extends TestCase
{
    public function test_implements_contract(): void
    {
        $notification = new LongWaitDetected('redis', 'default', 120);

        $this->assertInstanceOf(LongWaitDetectedNotification::class, $notification);
    }

    public function test_stores_connection_queue_and_seconds(): void
    {
        $notification = new LongWaitDetected('redis', 'high', 300);

        $this->assertSame('redis', $notification->longWaitConnection);
        $this->assertSame('high', $notification->longWaitQueue);
        $this->assertSame(300, $notification->seconds);
    }

    public function test_via_returns_mail_channel(): void
    {
        $notification = new LongWaitDetected('redis', 'default', 60);

        $this->assertSame(['mail'], $notification->via(null));
    }

    public function test_has_to_mail_method(): void
    {
        $this->assertTrue((new \ReflectionClass(LongWaitDetected::class))->hasMethod('toMail'));
    }

    public function test_signature_is_unique_per_connection_queue(): void
    {
        $notification1 = new LongWaitDetected('redis', 'default', 60);
        $notification2 = new LongWaitDetected('redis', 'high', 60);
        $notification3 = new LongWaitDetected('redis', 'default', 300);

        // Different connection/queue = different signature
        $this->assertNotSame($notification1->signature(), $notification2->signature());

        // Same connection/queue but different seconds = same signature (dedup key)
        $this->assertSame($notification1->signature(), $notification3->signature());
    }

    public function test_signature_is_md5_hash(): void
    {
        $notification = new LongWaitDetected('redis', 'default', 60);

        $expected = md5('redis'.'default');
        $this->assertSame($expected, $notification->signature());
    }
}
