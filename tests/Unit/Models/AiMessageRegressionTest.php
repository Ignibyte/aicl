<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Models;

use Aicl\Enums\AiMessageRole;
use Aicl\Models\AiConversation;
use Aicl\Models\AiMessage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for AiMessage model PHPStan changes.
 *
 * Covers typed property declarations, enum cast for role, relationship
 * return type annotations, and role helper methods with strict equality.
 */
class AiMessageRegressionTest extends TestCase
{
    /**
     * Test that the model uses HasUuids trait.
     *
     * Verifies trait composition after generic template annotation added to HasFactory.
     */
    public function test_model_uses_has_uuids(): void
    {
        // Arrange
        $traits = class_uses_recursive(AiMessage::class);

        // Assert
        $this->assertArrayHasKey(HasUuids::class, $traits);
    }

    /**
     * Test fillable contains all expected attributes.
     */
    public function test_fillable_contains_expected_attributes(): void
    {
        // Arrange
        $message = new AiMessage;

        // Act
        $fillable = $message->getFillable();

        // Assert
        $expected = ['ai_conversation_id', 'role', 'content', 'token_count', 'metadata'];
        foreach ($expected as $attribute) {
            $this->assertContains($attribute, $fillable, "Missing fillable: {$attribute}");
        }
    }

    /**
     * Test casts returns expected definitions.
     *
     * PHPStan added @return array<string, string> and enum cast for role.
     */
    public function test_casts_returns_expected_definitions(): void
    {
        // Arrange
        $message = new AiMessage;

        // Act: call protected casts() via reflection
        $reflection = new \ReflectionMethod($message, 'casts');
        $casts = $reflection->invoke($message);

        // Assert
        $this->assertSame(AiMessageRole::class, $casts['role']);
        $this->assertSame('integer', $casts['token_count']);
        $this->assertSame('array', $casts['metadata']);
    }

    /**
     * Test conversation relationship method returns BelongsTo type.
     *
     * PHPStan added @return BelongsTo<AiConversation, $this> annotation.
     * Uses reflection because calling the method needs a DB connection.
     */
    public function test_conversation_relationship_returns_belongs_to(): void
    {
        // Arrange
        $method = new \ReflectionMethod(AiMessage::class, 'conversation');
        $returnType = $method->getReturnType();

        // Assert: method returns BelongsTo
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        /** @var \ReflectionNamedType $returnType */
        $this->assertSame(BelongsTo::class, $returnType->getName());
    }

    /**
     * Test isFromUser uses strict enum comparison.
     *
     * PHPStan enforced === comparison with AiMessageRole::User.
     */
    public function test_is_from_user_returns_true_for_user_role(): void
    {
        // Arrange
        $message = new AiMessage;
        $message->role = AiMessageRole::User;

        // Act
        $result = $message->isFromUser();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isFromUser returns false for non-user role.
     */
    public function test_is_from_user_returns_false_for_assistant_role(): void
    {
        // Arrange
        $message = new AiMessage;
        $message->role = AiMessageRole::Assistant;

        // Act
        $result = $message->isFromUser();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isFromAssistant uses strict enum comparison.
     */
    public function test_is_from_assistant_returns_true_for_assistant_role(): void
    {
        // Arrange
        $message = new AiMessage;
        $message->role = AiMessageRole::Assistant;

        // Act
        $result = $message->isFromAssistant();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isFromAssistant returns false for non-assistant role.
     */
    public function test_is_from_assistant_returns_false_for_system_role(): void
    {
        // Arrange
        $message = new AiMessage;
        $message->role = AiMessageRole::System;

        // Act
        $result = $message->isFromAssistant();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isSystem uses strict enum comparison.
     */
    public function test_is_system_returns_true_for_system_role(): void
    {
        // Arrange
        $message = new AiMessage;
        $message->role = AiMessageRole::System;

        // Act
        $result = $message->isSystem();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isSystem returns false for non-system role.
     */
    public function test_is_system_returns_false_for_user_role(): void
    {
        // Arrange
        $message = new AiMessage;
        $message->role = AiMessageRole::User;

        // Act
        $result = $message->isSystem();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test table name is explicitly set.
     */
    public function test_table_name_is_ai_messages(): void
    {
        // Arrange
        $message = new AiMessage;

        // Assert
        $this->assertSame('ai_messages', $message->getTable());
    }
}
