<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Enums;

use Aicl\Enums\AiProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for AiProvider enum PHPStan changes.
 *
 * Covers all enum cases, the string backing values, and the
 * label(), color(), and icon() method return values. PHPStan
 * enforced exhaustive match coverage in these methods.
 */
class AiProviderRegressionTest extends TestCase
{
    /**
     * Test all enum cases exist.
     *
     * PHPStan requires exhaustive match — all cases must be covered.
     */
    public function test_all_cases_exist(): void
    {
        // Act
        $cases = AiProvider::cases();

        // Assert: exactly 4 cases
        $this->assertCount(4, $cases);
    }

    /**
     * Test OpenAi case value.
     */
    public function test_openai_case_value(): void
    {
        $this->assertSame('openai', AiProvider::OpenAi->value);
    }

    /**
     * Test Anthropic case value.
     */
    public function test_anthropic_case_value(): void
    {
        $this->assertSame('anthropic', AiProvider::Anthropic->value);
    }

    /**
     * Test Ollama case value.
     */
    public function test_ollama_case_value(): void
    {
        $this->assertSame('ollama', AiProvider::Ollama->value);
    }

    /**
     * Test Custom case value.
     */
    public function test_custom_case_value(): void
    {
        $this->assertSame('custom', AiProvider::Custom->value);
    }

    // ──────────────────────────────────────────────
    // label() method
    // ──────────────────────────────────────────────

    /**
     * Test OpenAi label returns 'OpenAI'.
     *
     * Note: capital 'AI' in label vs camelCase in enum.
     */
    public function test_openai_label(): void
    {
        $this->assertSame('OpenAI', AiProvider::OpenAi->label());
    }

    /**
     * Test Anthropic label.
     */
    public function test_anthropic_label(): void
    {
        $this->assertSame('Anthropic', AiProvider::Anthropic->label());
    }

    /**
     * Test Ollama label.
     */
    public function test_ollama_label(): void
    {
        $this->assertSame('Ollama', AiProvider::Ollama->label());
    }

    /**
     * Test Custom label.
     */
    public function test_custom_label(): void
    {
        $this->assertSame('Custom', AiProvider::Custom->label());
    }

    // ──────────────────────────────────────────────
    // color() method
    // ──────────────────────────────────────────────

    /**
     * Test OpenAi color is success.
     */
    public function test_openai_color(): void
    {
        $this->assertSame('success', AiProvider::OpenAi->color());
    }

    /**
     * Test Anthropic color is info.
     */
    public function test_anthropic_color(): void
    {
        $this->assertSame('info', AiProvider::Anthropic->color());
    }

    /**
     * Test Ollama color is warning.
     */
    public function test_ollama_color(): void
    {
        $this->assertSame('warning', AiProvider::Ollama->color());
    }

    /**
     * Test Custom color is gray.
     */
    public function test_custom_color(): void
    {
        $this->assertSame('gray', AiProvider::Custom->color());
    }

    // ──────────────────────────────────────────────
    // icon() method
    // ──────────────────────────────────────────────

    /**
     * Test OpenAi icon.
     */
    public function test_openai_icon(): void
    {
        $this->assertSame('heroicon-o-bolt', AiProvider::OpenAi->icon());
    }

    /**
     * Test Anthropic icon.
     */
    public function test_anthropic_icon(): void
    {
        $this->assertSame('heroicon-o-cpu-chip', AiProvider::Anthropic->icon());
    }

    /**
     * Test Ollama icon.
     */
    public function test_ollama_icon(): void
    {
        $this->assertSame('heroicon-o-server', AiProvider::Ollama->icon());
    }

    /**
     * Test Custom icon.
     */
    public function test_custom_icon(): void
    {
        $this->assertSame('heroicon-o-cog-6-tooth', AiProvider::Custom->icon());
    }

    /**
     * Test enum can be constructed from string value.
     *
     * Verifies the backed enum from() works with strict types.
     */
    public function test_from_string_value(): void
    {
        // Act
        $provider = AiProvider::from('openai');

        // Assert
        $this->assertSame(AiProvider::OpenAi, $provider);
    }

    /**
     * Test from() throws for invalid value.
     *
     * Verifies the backed enum from() throws ValueError for
     * invalid values, confirming strict type enforcement.
     */
    public function test_from_throws_for_invalid_value(): void
    {
        // Assert: from() throws ValueError for invalid value
        $this->expectException(\ValueError::class);

        // Act
        AiProvider::from('nonexistent');
    }
}
