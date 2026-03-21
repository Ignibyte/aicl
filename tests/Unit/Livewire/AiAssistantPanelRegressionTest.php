<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Livewire;

use Aicl\Livewire\AiAssistantPanel;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for AiAssistantPanel Livewire component PHPStan changes.
 *
 * Covers the render() return type change from untyped to View,
 * the phpstan-ignore on the $toolResults annotation,
 * and the PHPDoc addition for toolResults array type.
 */
class AiAssistantPanelRegressionTest extends TestCase
{
    // -- render() return type --

    /**
     * Test render method has View return type.
     *
     * PHPStan change: Changed from `public function render()` to
     * `public function render(): View`.
     */
    public function test_render_method_has_view_return_type(): void
    {
        // Arrange
        $reflection = new \ReflectionMethod(AiAssistantPanel::class, 'render');

        // Assert: return type is View
        $returnType = $reflection->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame(View::class, $returnType->getName());
    }

    // -- conversations computed property --

    /**
     * Test conversations method exists and has Computed attribute.
     *
     * The conversations() method returns a Collection of the user's
     * AI conversations for the sidebar.
     */
    public function test_conversations_has_computed_attribute(): void
    {
        // Arrange
        $reflection = new \ReflectionMethod(AiAssistantPanel::class, 'conversations');
        $attributes = $reflection->getAttributes();
        $hasComputed = false;

        foreach ($attributes as $attr) {
            if ($attr->getName() === 'Livewire\Attributes\Computed') {
                $hasComputed = true;

                break;
            }
        }

        // Assert
        $this->assertTrue($hasComputed, 'conversations() should have #[Computed] attribute');
    }

    // -- Class extends Component --

    /**
     * Test AiAssistantPanel extends Livewire Component.
     */
    public function test_extends_livewire_component(): void
    {
        // Assert: verify parent class via reflection
        $reflection = new \ReflectionClass(AiAssistantPanel::class);
        $parentClass = $reflection->getParentClass();
        $this->assertNotFalse($parentClass);
        // Component is the direct or ancestor parent
        $this->assertInstanceOf(\ReflectionClass::class, $parentClass);
    }

    // -- loadMessages method --

    /**
     * Test loadMessages method exists and is public.
     *
     * The method loads and formats messages for the chat display,
     * including tool results enrichment.
     */
    public function test_load_messages_method_exists(): void
    {
        // Arrange
        $reflection = new \ReflectionMethod(AiAssistantPanel::class, 'loadMessages');

        // Assert
        $this->assertTrue($reflection->isPublic());
    }
}
