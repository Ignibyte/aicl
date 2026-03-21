<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Models;

use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use Aicl\Models\AiMessage;
use Aicl\States\AiConversation\AiConversationState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\TestCase;

/**
 * Regression tests for AiConversation model PHPStan changes.
 *
 * Covers typed property declarations, cast definitions, relationship
 * return type annotations, scope signatures, accessor return types,
 * compaction logic with int cast, and searchableColumns override.
 * Uses Laravel TestCase because getIsCompactableAttribute calls config().
 */
class AiConversationRegressionTest extends TestCase
{
    /**
     * Test that the model uses expected traits.
     *
     * PHPStan migration added generic template to HasFactory.
     */
    public function test_model_uses_expected_traits(): void
    {
        // Arrange
        $traits = class_uses_recursive(AiConversation::class);

        // Assert
        $this->assertArrayHasKey(HasUuids::class, $traits);
        $this->assertArrayHasKey(SoftDeletes::class, $traits);
    }

    /**
     * Test fillable contains all expected attributes.
     *
     * Verifies fillable matches the typed property declarations.
     */
    public function test_fillable_contains_all_expected_attributes(): void
    {
        // Arrange
        $conversation = new AiConversation;

        // Act
        $fillable = $conversation->getFillable();

        // Assert
        $expected = [
            'title', 'user_id', 'ai_agent_id', 'message_count',
            'token_count', 'summary', 'is_pinned', 'context_page',
            'last_message_at', 'state',
        ];

        foreach ($expected as $attribute) {
            $this->assertContains($attribute, $fillable, "Missing fillable: {$attribute}");
        }
    }

    /**
     * Test casts returns expected definitions.
     *
     * PHPStan added @return array<string, string> to casts().
     */
    public function test_casts_returns_expected_definitions(): void
    {
        // Arrange
        $conversation = new AiConversation;

        // Act: call protected casts() via reflection
        $reflection = new \ReflectionMethod($conversation, 'casts');
        $casts = $reflection->invoke($conversation);

        // Assert
        $this->assertSame(AiConversationState::class, $casts['state']);
        $this->assertSame('integer', $casts['message_count']);
        $this->assertSame('integer', $casts['token_count']);
        $this->assertSame('boolean', $casts['is_pinned']);
        $this->assertSame('datetime', $casts['last_message_at']);
    }

    /**
     * Test agent relationship method returns BelongsTo type.
     *
     * PHPStan added @return BelongsTo<AiAgent, $this> annotation.
     * Uses reflection because calling the method needs a DB connection.
     */
    public function test_agent_relationship_returns_belongs_to(): void
    {
        // Arrange
        $method = new \ReflectionMethod(AiConversation::class, 'agent');
        $returnType = $method->getReturnType();

        // Assert: method returns BelongsTo
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        /** @var \ReflectionNamedType $returnType */
        $this->assertSame(BelongsTo::class, $returnType->getName());
    }

    /**
     * Test messages relationship method returns HasMany type.
     *
     * PHPStan added @return HasMany<AiMessage, $this> annotation.
     * Uses reflection because calling the method needs a DB connection.
     */
    public function test_messages_relationship_returns_has_many(): void
    {
        // Arrange
        $method = new \ReflectionMethod(AiConversation::class, 'messages');
        $returnType = $method->getReturnType();

        // Assert: method returns HasMany
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        /** @var \ReflectionNamedType $returnType */
        $this->assertSame(HasMany::class, $returnType->getName());
    }

    /**
     * Test getDisplayTitleAttribute returns title when set.
     *
     * PHPStan enforced string return type on accessor.
     */
    public function test_display_title_returns_title_when_set(): void
    {
        // Arrange
        $conversation = new AiConversation;
        $conversation->title = 'My Chat';

        // Act
        $result = $conversation->getDisplayTitleAttribute();

        // Assert
        $this->assertSame('My Chat', $result);
    }

    /**
     * Test getDisplayTitleAttribute returns default when title is null.
     *
     * Null coalescing: $this->title ?? 'New Conversation'.
     */
    public function test_display_title_returns_default_when_null(): void
    {
        // Arrange
        $conversation = new AiConversation;
        $conversation->title = null;

        // Act
        $result = $conversation->getDisplayTitleAttribute();

        // Assert: falls back to default string
        $this->assertSame('New Conversation', $result);
    }

    /**
     * Test getIsCompactableAttribute with int cast on config value.
     *
     * PHPStan enforced (int) cast: (int) config('aicl.ai.assistant.compaction_threshold', 50).
     * When message_count exceeds threshold and summary is null, the
     * conversation is compactable.
     */
    public function test_is_compactable_returns_true_when_above_threshold_and_no_summary(): void
    {
        // Arrange: message count above default threshold (50)
        $conversation = new AiConversation;
        $conversation->message_count = 51;
        $conversation->summary = null;

        // Act
        $result = $conversation->getIsCompactableAttribute();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test getIsCompactableAttribute returns false when below threshold.
     */
    public function test_is_compactable_returns_false_when_below_threshold(): void
    {
        // Arrange: message count below threshold
        $conversation = new AiConversation;
        $conversation->message_count = 10;
        $conversation->summary = null;

        // Act
        $result = $conversation->getIsCompactableAttribute();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test getIsCompactableAttribute returns false when summary exists.
     *
     * Even if above threshold, having a summary means already compacted.
     */
    public function test_is_compactable_returns_false_when_summary_exists(): void
    {
        // Arrange: above threshold but with summary
        $conversation = new AiConversation;
        $conversation->message_count = 100;
        $conversation->summary = 'Previous conversation summary';

        // Act
        $result = $conversation->getIsCompactableAttribute();

        // Assert: already compacted
        $this->assertFalse($result);
    }

    /**
     * Test searchableColumns returns only 'title'.
     *
     * Override to prevent BF-001/BF-005.
     */
    public function test_searchable_columns_returns_title_only(): void
    {
        // Arrange
        $conversation = new AiConversation;

        // Act: call protected searchableColumns() via reflection
        $reflection = new \ReflectionMethod($conversation, 'searchableColumns');
        $columns = $reflection->invoke($conversation);

        // Assert: only title — no 'name' column on conversations
        $this->assertSame(['title'], $columns);
    }

    /**
     * Test table name is explicitly set.
     */
    public function test_table_name_is_ai_conversations(): void
    {
        // Arrange
        $conversation = new AiConversation;

        // Assert
        $this->assertSame('ai_conversations', $conversation->getTable());
    }
}
