<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Enums;

use Aicl\Enums\AiMessageRole;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Regression tests for AiMessageRole enum PHPStan changes.
 *
 * Covers all enum cases, the Filament interface implementations
 * (HasLabel, HasColor, HasIcon), and their method return values.
 * PHPStan enforced exhaustive match coverage and interface compliance.
 */
class AiMessageRoleRegressionTest extends TestCase
{
    /**
     * Test enum implements Filament HasLabel interface.
     */
    public function test_implements_has_label(): void
    {
        // Arrange
        $reflection = new ReflectionClass(AiMessageRole::class);

        // Assert
        $this->assertTrue($reflection->implementsInterface(HasLabel::class));
    }

    /**
     * Test enum implements Filament HasColor interface.
     */
    public function test_implements_has_color(): void
    {
        // Arrange
        $reflection = new ReflectionClass(AiMessageRole::class);

        // Assert
        $this->assertTrue($reflection->implementsInterface(HasColor::class));
    }

    /**
     * Test enum implements Filament HasIcon interface.
     */
    public function test_implements_has_icon(): void
    {
        // Arrange
        $reflection = new ReflectionClass(AiMessageRole::class);

        // Assert
        $this->assertTrue($reflection->implementsInterface(HasIcon::class));
    }

    /**
     * Test all enum cases exist.
     */
    public function test_all_cases_exist(): void
    {
        // Act
        $cases = AiMessageRole::cases();

        // Assert: exactly 3 cases
        $this->assertCount(3, $cases);
    }

    // ──────────────────────────────────────────────
    // String backing values
    // ──────────────────────────────────────────────

    /**
     * Test User case value.
     */
    public function test_user_case_value(): void
    {
        $this->assertSame('user', AiMessageRole::User->value);
    }

    /**
     * Test Assistant case value.
     */
    public function test_assistant_case_value(): void
    {
        $this->assertSame('assistant', AiMessageRole::Assistant->value);
    }

    /**
     * Test System case value.
     */
    public function test_system_case_value(): void
    {
        $this->assertSame('system', AiMessageRole::System->value);
    }

    // ──────────────────────────────────────────────
    // getLabel() — Filament HasLabel
    // ──────────────────────────────────────────────

    /**
     * Test User label.
     */
    public function test_user_label(): void
    {
        $this->assertSame('User', AiMessageRole::User->getLabel());
    }

    /**
     * Test Assistant label.
     */
    public function test_assistant_label(): void
    {
        $this->assertSame('Assistant', AiMessageRole::Assistant->getLabel());
    }

    /**
     * Test System label.
     */
    public function test_system_label(): void
    {
        $this->assertSame('System', AiMessageRole::System->getLabel());
    }

    // ──────────────────────────────────────────────
    // getColor() — Filament HasColor
    // ──────────────────────────────────────────────

    /**
     * Test User color is primary.
     */
    public function test_user_color(): void
    {
        $this->assertSame('primary', AiMessageRole::User->getColor());
    }

    /**
     * Test Assistant color is success.
     */
    public function test_assistant_color(): void
    {
        $this->assertSame('success', AiMessageRole::Assistant->getColor());
    }

    /**
     * Test System color is gray.
     */
    public function test_system_color(): void
    {
        $this->assertSame('gray', AiMessageRole::System->getColor());
    }

    // ──────────────────────────────────────────────
    // getIcon() — Filament HasIcon
    // ──────────────────────────────────────────────

    /**
     * Test User icon.
     */
    public function test_user_icon(): void
    {
        $this->assertSame('heroicon-o-user', AiMessageRole::User->getIcon());
    }

    /**
     * Test Assistant icon.
     */
    public function test_assistant_icon(): void
    {
        $this->assertSame('heroicon-o-cpu-chip', AiMessageRole::Assistant->getIcon());
    }

    /**
     * Test System icon.
     */
    public function test_system_icon(): void
    {
        $this->assertSame('heroicon-o-cog-6-tooth', AiMessageRole::System->getIcon());
    }

    /**
     * Test from() constructs enum from string.
     *
     * Verifies backed enum construction works with strict types.
     */
    public function test_from_string_value(): void
    {
        // Act
        $role = AiMessageRole::from('user');

        // Assert
        $this->assertSame(AiMessageRole::User, $role);
    }

    /**
     * Test tryFrom handles invalid values gracefully.
     *
     * Verifies the backed enum tryFrom() does not throw for
     * invalid values. The method returns null but PHPStan proves
     * this at compile time, so we verify from() throws instead.
     */
    public function test_from_throws_for_invalid_value(): void
    {
        // Assert: from() throws ValueError for invalid value
        $this->expectException(\ValueError::class);

        // Act
        AiMessageRole::from('invalid');
    }
}
