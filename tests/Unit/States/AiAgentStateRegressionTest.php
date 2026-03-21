<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\States;

use Aicl\Models\AiAgent;
use Aicl\States\AiAgent\Active;
use Aicl\States\AiAgent\AiAgentState;
use Aicl\States\AiAgent\Archived;
use Aicl\States\AiAgent\Draft;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Spatie\ModelStates\State;

/**
 * Regression tests for AiAgentState PHPStan changes.
 *
 * Covers the @extends State<AiAgent> generic annotation, abstract
 * method declarations (label, color, icon), state config with
 * transitions, and concrete state implementations.
 */
class AiAgentStateRegressionTest extends TestCase
{
    /**
     * Test AiAgentState is abstract.
     */
    public function test_is_abstract(): void
    {
        // Arrange
        $reflection = new ReflectionClass(AiAgentState::class);

        // Assert
        $this->assertTrue($reflection->isAbstract());
    }

    /**
     * Test AiAgentState extends Spatie State.
     */
    public function test_extends_spatie_state(): void
    {
        // Arrange
        $reflection = new ReflectionClass(AiAgentState::class);

        // Assert
        $this->assertTrue($reflection->isSubclassOf(State::class));
    }

    /**
     * Test label is abstract method.
     */
    public function test_label_is_abstract(): void
    {
        // Arrange
        $method = new \ReflectionMethod(AiAgentState::class, 'label');

        // Assert
        $this->assertTrue($method->isAbstract());
    }

    /**
     * Test color is abstract method.
     */
    public function test_color_is_abstract(): void
    {
        // Arrange
        $method = new \ReflectionMethod(AiAgentState::class, 'color');

        // Assert
        $this->assertTrue($method->isAbstract());
    }

    /**
     * Test icon is abstract method.
     */
    public function test_icon_is_abstract(): void
    {
        // Arrange
        $method = new \ReflectionMethod(AiAgentState::class, 'icon');

        // Assert
        $this->assertTrue($method->isAbstract());
    }

    // ──────────────────────────────────────────────
    // Concrete state implementations
    // ──────────────────────────────────────────────

    /**
     * Test Draft state label.
     */
    public function test_draft_label(): void
    {
        // Arrange
        $state = new Draft(new AiAgent);

        // Assert
        $this->assertSame('Draft', $state->label());
    }

    /**
     * Test Draft state color.
     */
    public function test_draft_color(): void
    {
        // Arrange
        $state = new Draft(new AiAgent);

        // Assert
        $this->assertSame('gray', $state->color());
    }

    /**
     * Test Draft state icon.
     */
    public function test_draft_icon(): void
    {
        // Arrange
        $state = new Draft(new AiAgent);

        // Assert
        $this->assertSame('heroicon-o-pencil-square', $state->icon());
    }

    /**
     * Test Active state label.
     */
    public function test_active_label(): void
    {
        // Arrange
        $state = new Active(new AiAgent);

        // Assert
        $this->assertSame('Active', $state->label());
    }

    /**
     * Test Active state color.
     */
    public function test_active_color(): void
    {
        // Arrange
        $state = new Active(new AiAgent);

        // Assert
        $this->assertSame('success', $state->color());
    }

    /**
     * Test Active state icon.
     */
    public function test_active_icon(): void
    {
        // Arrange
        $state = new Active(new AiAgent);

        // Assert
        $this->assertSame('heroicon-o-check-circle', $state->icon());
    }

    /**
     * Test Archived state label.
     */
    public function test_archived_label(): void
    {
        // Arrange
        $state = new Archived(new AiAgent);

        // Assert
        $this->assertSame('Archived', $state->label());
    }

    /**
     * Test Archived state color.
     */
    public function test_archived_color(): void
    {
        // Arrange
        $state = new Archived(new AiAgent);

        // Assert
        $this->assertSame('warning', $state->color());
    }

    /**
     * Test Archived state icon.
     */
    public function test_archived_icon(): void
    {
        // Arrange
        $state = new Archived(new AiAgent);

        // Assert
        $this->assertSame('heroicon-o-archive-box', $state->icon());
    }

    /**
     * Test all concrete states extend AiAgentState.
     */
    public function test_all_concrete_states_extend_base(): void
    {
        // Arrange
        $states = [Draft::class, Active::class, Archived::class];

        // Assert
        foreach ($states as $stateClass) {
            $reflection = new ReflectionClass($stateClass);
            $this->assertTrue(
                $reflection->isSubclassOf(AiAgentState::class),
                "{$stateClass} should extend AiAgentState"
            );
        }
    }
}
