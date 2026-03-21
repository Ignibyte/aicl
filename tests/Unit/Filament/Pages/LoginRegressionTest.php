<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Filament\Pages\Auth\Login;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Tests\TestCase;

/**
 * Regression tests for Login page PHPStan changes.
 *
 * Covers the new docblocks and typed return annotations on getTitle(),
 * getHeading(), getSubheading(), hasLogo(), and getSocialProviders().
 * These are PHPDoc-only changes -- tests verify methods still return
 * expected values under strict_types.
 */
class LoginRegressionTest extends TestCase
{
    // -- getTitle --

    /**
     * Test getTitle returns "Sign in" string.
     *
     * PHPStan change: Added docblock, no behavioral change.
     */
    public function test_get_title_returns_sign_in(): void
    {
        // Arrange
        $page = new Login;

        // Act
        $title = $page->getTitle();

        // Assert: getTitle returns string|Htmlable, use Htmlable-safe comparison
        $titleStr = $title instanceof Htmlable ? $title->toHtml() : $title;
        $this->assertSame('Sign in', $titleStr);
    }

    // -- getHeading --

    /**
     * Test getHeading returns empty string for split layout.
     *
     * PHPStan change: Added docblock, no behavioral change.
     */
    public function test_get_heading_returns_empty_string(): void
    {
        // Arrange
        $page = new Login;

        // Act
        $heading = $page->getHeading();

        // Assert: empty string for the split layout
        $headingStr = $heading instanceof Htmlable ? $heading->toHtml() : $heading;
        $this->assertSame('', $headingStr);
    }

    // -- getSubheading --

    /**
     * Test getSubheading returns null for split layout.
     *
     * PHPStan change: Added docblock, no behavioral change.
     */
    public function test_get_subheading_returns_null(): void
    {
        // Arrange
        $page = new Login;

        // Act
        $subheading = $page->getSubheading();

        // Assert
        $this->assertNull($subheading);
    }

    // -- hasLogo --

    /**
     * Test hasLogo returns false for the split layout.
     *
     * PHPStan change: Added docblock, no behavioral change.
     */
    public function test_has_logo_returns_false(): void
    {
        // Arrange
        $page = new Login;

        // Act
        $result = $page->hasLogo();

        // Assert: no logo on the split layout login page
        $this->assertFalse($result);
    }

    // -- Class hierarchy --

    /**
     * Test Login extends Filament's base Login page.
     */
    public function test_extends_filament_base_login(): void
    {
        // Assert: verify the class hierarchy using instanceof check
        $page = new Login;
        $this->assertInstanceOf(BaseLogin::class, $page);
    }

    // -- getSocialProviders with feature disabled --

    /**
     * Test getSocialProviders returns empty array when social login is disabled.
     *
     * PHPStan change: Added @return type annotation.
     */
    public function test_get_social_providers_empty_when_disabled(): void
    {
        // Arrange: disable social login
        config(['aicl.features.social_login' => false]);
        $page = new Login;

        // Act
        $providers = $page->getSocialProviders();

        // Assert: empty when feature disabled
        $this->assertSame([], $providers);
    }
}
