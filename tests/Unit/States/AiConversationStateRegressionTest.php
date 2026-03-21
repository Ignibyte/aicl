<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\States;

use Aicl\Models\AiConversation;
use Aicl\States\AiConversation\Active;
use Aicl\States\AiConversation\AiConversationState;
use Aicl\States\AiConversation\Archived;
use Aicl\States\AiConversation\Summarized;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Spatie\ModelStates\State;

/**
 * Regression tests for AiConversationState PHPStan changes.
 *
 * Covers the @extends State<AiConversation> generic annotation,
 * abstract method declarations (label, color, icon), state config
 * with transitions, and concrete state implementations.
 */
class AiConversationStateRegressionTest extends TestCase
{
    /**
     * Test AiConversationState is abstract.
     */
    public function test_is_abstract(): void
    {
        // Arrange
        $reflection = new ReflectionClass(AiConversationState::class);

        // Assert
        $this->assertTrue($reflection->isAbstract());
    }

    /**
     * Test AiConversationState extends Spatie State.
     */
    public function test_extends_spatie_state(): void
    {
        // Arrange
        $reflection = new ReflectionClass(AiConversationState::class);

        // Assert
        $this->assertTrue($reflection->isSubclassOf(State::class));
    }

    /**
     * Test label is abstract method.
     */
    public function test_label_is_abstract(): void
    {
        // Arrange
        $method = new \ReflectionMethod(AiConversationState::class, 'label');

        // Assert
        $this->assertTrue($method->isAbstract());
    }

    /**
     * Test color is abstract method.
     */
    public function test_color_is_abstract(): void
    {
        // Arrange
        $method = new \ReflectionMethod(AiConversationState::class, 'color');

        // Assert
        $this->assertTrue($method->isAbstract());
    }

    /**
     * Test icon is abstract method.
     */
    public function test_icon_is_abstract(): void
    {
        // Arrange
        $method = new \ReflectionMethod(AiConversationState::class, 'icon');

        // Assert
        $this->assertTrue($method->isAbstract());
    }

    // ──────────────────────────────────────────────
    // Concrete state implementations
    // ──────────────────────────────────────────────

    /**
     * Test Active state label.
     */
    public function test_active_label(): void
    {
        // Arrange
        $state = new Active(new AiConversation);

        // Assert
        $this->assertSame('Active', $state->label());
    }

    /**
     * Test Active state color.
     */
    public function test_active_color(): void
    {
        // Arrange
        $state = new Active(new AiConversation);

        // Assert
        $this->assertSame('success', $state->color());
    }

    /**
     * Test Active state icon.
     */
    public function test_active_icon(): void
    {
        // Arrange
        $state = new Active(new AiConversation);

        // Assert
        $this->assertSame('heroicon-o-chat-bubble-left-right', $state->icon());
    }

    /**
     * Test Summarized state label.
     */
    public function test_summarized_label(): void
    {
        // Arrange
        $state = new Summarized(new AiConversation);

        // Assert
        $this->assertSame('Summarized', $state->label());
    }

    /**
     * Test Summarized state color.
     */
    public function test_summarized_color(): void
    {
        // Arrange
        $state = new Summarized(new AiConversation);

        // Assert
        $this->assertSame('info', $state->color());
    }

    /**
     * Test Summarized state icon.
     */
    public function test_summarized_icon(): void
    {
        // Arrange
        $state = new Summarized(new AiConversation);

        // Assert
        $this->assertSame('heroicon-o-document-text', $state->icon());
    }

    /**
     * Test Archived state label.
     */
    public function test_archived_label(): void
    {
        // Arrange
        $state = new Archived(new AiConversation);

        // Assert
        $this->assertSame('Archived', $state->label());
    }

    /**
     * Test Archived state color.
     */
    public function test_archived_color(): void
    {
        // Arrange
        $state = new Archived(new AiConversation);

        // Assert
        $this->assertSame('gray', $state->color());
    }

    /**
     * Test Archived state icon.
     */
    public function test_archived_icon(): void
    {
        // Arrange
        $state = new Archived(new AiConversation);

        // Assert
        $this->assertSame('heroicon-o-archive-box', $state->icon());
    }

    /**
     * Test all concrete states extend AiConversationState.
     */
    public function test_all_concrete_states_extend_base(): void
    {
        // Arrange
        $states = [Active::class, Summarized::class, Archived::class];

        // Assert
        foreach ($states as $stateClass) {
            $reflection = new ReflectionClass($stateClass);
            $this->assertTrue(
                $reflection->isSubclassOf(AiConversationState::class),
                "{$stateClass} should extend AiConversationState"
            );
        }
    }
}
