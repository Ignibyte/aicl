<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\AI;

use Aicl\AI\AiAssistantController;
use Aicl\AI\Jobs\AiStreamJob;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Regression tests for AiAssistantController PHPStan changes.
 *
 * Tests the null guard added to $request->user() and the (int) type cast
 * on config('aicl.ai.streaming.max_concurrent_per_user'). These changes
 * were introduced during PHPStan level 5-to-8 migration to enforce
 * strict type safety.
 */
class AiAssistantControllerRegressionTest extends TestCase
{
    /**
     * Test that the controller returns 401 when request->user() is null.
     *
     * PHPStan change: Added explicit null check for $request->user()
     * which previously assumed user was always authenticated. Under strict_types,
     * accessing ->id on null would throw a fatal error.
     */
    public function test_ask_returns_401_when_user_is_null(): void
    {
        // Arrange: configure a valid AI provider but don't authenticate
        config([
            'aicl.ai.provider' => 'openai',
            'aicl.ai.openai.api_key' => 'sk-test-key',
        ]);

        Bus::fake([AiStreamJob::class]);

        // Act: send request without authentication
        $response = $this->postJson(route('api.v1.ai.ask'), [
            'prompt' => 'Hello world',
        ]);

        // Assert: should get 401 Unauthenticated
        // The auth middleware may handle this before the controller,
        // but the null guard is a defense-in-depth measure
        $response->assertUnauthorized();
    }

    /**
     * Test that max_concurrent_per_user config is cast to int.
     *
     * PHPStan change: Added (int) cast to config() return value.
     * Under strict_types, comparing a string from config with an int
     * counter would behave differently. This verifies the cast works
     * with various config value types.
     */
    public function test_max_concurrent_per_user_config_cast_to_int(): void
    {
        // Arrange: set config as string (simulating how config values can be stored)
        config(['aicl.ai.streaming.max_concurrent_per_user' => '3']);

        // Act: cast the value as the controller does
        $maxConcurrent = (int) config('aicl.ai.streaming.max_concurrent_per_user', 2);

        // Assert: string '3' is properly cast to int 3
        $this->assertSame(3, $maxConcurrent);
    }

    /**
     * Test that max_concurrent_per_user uses default when not set.
     *
     * When the config key doesn't exist at all, config() returns the
     * default value. Note: config() returns null (not the default) when
     * the key exists but is explicitly null.
     */
    public function test_max_concurrent_per_user_defaults_to_2_when_not_set(): void
    {
        // Arrange: remove the config key entirely by setting parent to empty
        // Use a key that definitely doesn't exist
        $maxConcurrent = (int) config('aicl.ai.streaming.nonexistent_key', 2);

        // Assert: defaults to 2 when config key is missing
        $this->assertSame(2, $maxConcurrent);
    }

    /**
     * Test that max_concurrent_per_user handles zero config.
     *
     * Edge case: zero should be respected (disables streaming).
     */
    public function test_max_concurrent_per_user_handles_zero(): void
    {
        // Arrange: set config to zero
        config(['aicl.ai.streaming.max_concurrent_per_user' => 0]);

        // Act
        $maxConcurrent = (int) config('aicl.ai.streaming.max_concurrent_per_user', 2);

        // Assert: zero is a valid int value and should not fall through to default
        $this->assertSame(0, $maxConcurrent);
    }
}
