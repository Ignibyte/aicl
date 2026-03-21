<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Models;

use Aicl\Models\SocialAccount;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Regression tests for SocialAccount model PHPStan changes.
 *
 * Covers typed property declarations, encrypted cast definitions,
 * BelongsTo relationship annotation, and isExpired() null guard
 * for token_expires_at. Uses Laravel TestCase because setting
 * datetime model attributes triggers cast pipeline which needs
 * the application container.
 */
class SocialAccountRegressionTest extends TestCase
{
    /**
     * Test casts returns encrypted and datetime definitions.
     *
     * PHPStan migration retained the casts; this verifies the return
     * type annotation is correct and encrypted casts are declared.
     */
    public function test_casts_returns_encrypted_and_datetime(): void
    {
        // Arrange
        $account = new SocialAccount;

        // Act: call protected casts() via reflection
        $reflection = new \ReflectionMethod($account, 'casts');
        $casts = $reflection->invoke($account);

        // Assert
        $this->assertSame('encrypted', $casts['token']);
        $this->assertSame('encrypted', $casts['refresh_token']);
        $this->assertSame('datetime', $casts['token_expires_at']);
    }

    /**
     * Test user relationship method exists and returns BelongsTo.
     *
     * PHPStan added @return BelongsTo<User, $this> annotation.
     * Uses reflection because calling user() needs config() service.
     */
    public function test_user_relationship_method_returns_belongs_to(): void
    {
        // Arrange
        $method = new \ReflectionMethod(SocialAccount::class, 'user');
        $returnType = $method->getReturnType();

        // Assert: method exists and returns BelongsTo
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        /** @var \ReflectionNamedType $returnType */
        $this->assertSame(BelongsTo::class, $returnType->getName());
    }

    /**
     * Test isExpired returns false when token_expires_at is null.
     *
     * Null guard: if (! $this->token_expires_at) return false.
     */
    public function test_is_expired_returns_false_for_null_expiry(): void
    {
        // Arrange
        $account = new SocialAccount;
        $account->token_expires_at = null;

        // Act
        $result = $account->isExpired();

        // Assert: no expiry = not expired
        $this->assertFalse($result);
    }

    /**
     * Test isExpired returns true for past date.
     *
     * Verifies Carbon isPast() comparison after strict_types.
     */
    public function test_is_expired_returns_true_for_past_date(): void
    {
        // Arrange
        $account = new SocialAccount;
        $account->token_expires_at = Carbon::now()->subDay();

        // Act
        $result = $account->isExpired();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isExpired returns false for future date.
     */
    public function test_is_expired_returns_false_for_future_date(): void
    {
        // Arrange
        $account = new SocialAccount;
        $account->token_expires_at = Carbon::now()->addDay();

        // Act
        $result = $account->isExpired();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test fillable includes all OAuth-related fields.
     */
    public function test_fillable_includes_oauth_fields(): void
    {
        // Arrange
        $account = new SocialAccount;

        // Act
        $fillable = $account->getFillable();

        // Assert
        $expected = ['user_id', 'provider', 'provider_id', 'avatar_url', 'token', 'refresh_token', 'token_expires_at'];
        foreach ($expected as $attr) {
            $this->assertContains($attr, $fillable, "Missing fillable: {$attr}");
        }
    }
}
