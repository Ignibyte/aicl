<?php

namespace Aicl\Tests\Unit\Traits;

use Aicl\Traits\HasNotificationLogging;
use PHPUnit\Framework\TestCase;

class HasNotificationLoggingTest extends TestCase
{
    public function test_trait_exists(): void
    {
        $this->assertTrue(trait_exists(HasNotificationLogging::class));
    }

    public function test_trait_provides_notification_logs_method(): void
    {
        $reflection = new \ReflectionClass(HasNotificationLogging::class);

        $this->assertTrue($reflection->hasMethod('notificationLogs'));
    }

    public function test_notification_logs_returns_morph_many(): void
    {
        $reflection = new \ReflectionClass(HasNotificationLogging::class);
        $method = $reflection->getMethod('notificationLogs');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('MorphMany', $returnType->getName());
    }
}
