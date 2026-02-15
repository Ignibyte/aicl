<?php

namespace Aicl\Tests\Unit\Notifications;

use Aicl\Notifications\DriverResult;
use PHPUnit\Framework\TestCase;

class DriverResultTest extends TestCase
{
    public function test_success_static_constructor(): void
    {
        $result = DriverResult::success();

        $this->assertTrue($result->success);
        $this->assertNull($result->messageId);
        $this->assertNull($result->response);
        $this->assertNull($result->error);
        $this->assertTrue($result->retryable);
    }

    public function test_success_with_message_id(): void
    {
        $result = DriverResult::success(messageId: 'msg-123');

        $this->assertTrue($result->success);
        $this->assertSame('msg-123', $result->messageId);
    }

    public function test_success_with_response(): void
    {
        $response = ['status' => 200, 'body' => 'ok'];
        $result = DriverResult::success(response: $response);

        $this->assertTrue($result->success);
        $this->assertSame($response, $result->response);
    }

    public function test_success_with_all_parameters(): void
    {
        $response = ['dedup_key' => 'abc-123'];
        $result = DriverResult::success(messageId: 'msg-456', response: $response);

        $this->assertTrue($result->success);
        $this->assertSame('msg-456', $result->messageId);
        $this->assertSame($response, $result->response);
    }

    public function test_failure_static_constructor(): void
    {
        $result = DriverResult::failure(error: 'Something went wrong');

        $this->assertFalse($result->success);
        $this->assertSame('Something went wrong', $result->error);
        $this->assertTrue($result->retryable);
        $this->assertNull($result->messageId);
        $this->assertNull($result->response);
    }

    public function test_failure_not_retryable(): void
    {
        $result = DriverResult::failure(error: 'Bad request', retryable: false);

        $this->assertFalse($result->success);
        $this->assertFalse($result->retryable);
    }

    public function test_failure_retryable_by_default(): void
    {
        $result = DriverResult::failure(error: 'Server error');

        $this->assertTrue($result->retryable);
    }

    public function test_failure_with_response(): void
    {
        $response = ['status' => 500, 'body' => 'Internal Server Error'];
        $result = DriverResult::failure(error: 'Server error', response: $response);

        $this->assertFalse($result->success);
        $this->assertSame($response, $result->response);
    }

    public function test_failure_with_all_parameters(): void
    {
        $response = ['status' => 429, 'body' => 'Too Many Requests'];
        $result = DriverResult::failure(
            error: 'Rate limited',
            retryable: true,
            response: $response,
        );

        $this->assertFalse($result->success);
        $this->assertSame('Rate limited', $result->error);
        $this->assertTrue($result->retryable);
        $this->assertSame($response, $result->response);
    }

    public function test_properties_are_readonly(): void
    {
        $result = DriverResult::success(messageId: 'test');

        $reflection = new \ReflectionClass($result);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }

    public function test_constructor_directly(): void
    {
        $result = new DriverResult(
            success: true,
            messageId: 'direct-id',
            response: ['test' => true],
            error: null,
            retryable: false,
        );

        $this->assertTrue($result->success);
        $this->assertSame('direct-id', $result->messageId);
        $this->assertSame(['test' => true], $result->response);
        $this->assertNull($result->error);
        $this->assertFalse($result->retryable);
    }
}
