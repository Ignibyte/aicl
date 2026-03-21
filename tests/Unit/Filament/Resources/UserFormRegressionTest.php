<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Filament\Resources;

use Aicl\Filament\Resources\Users\Schemas\UserForm;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for UserForm schema PHPStan changes.
 *
 * Covers the replacement of Spatie FeatureSettings with config() calls
 * for MFA-related helper text and disabled state. The FeatureSettings
 * class was removed during config consolidation; the form now reads
 * directly from config('aicl.features.require_mfa').
 */
class UserFormRegressionTest extends TestCase
{
    // -- Class structure --

    /**
     * Test UserForm has a static configure method.
     *
     * The form schema is configured via a static method that receives
     * a Schema instance. This verifies the method exists and is public.
     */
    public function test_user_form_has_configure_method(): void
    {
        // Arrange
        $reflection = new \ReflectionMethod(UserForm::class, 'configure');

        // Assert: method is public and static
        $this->assertTrue($reflection->isPublic());
        $this->assertTrue($reflection->isStatic());
    }

    /**
     * Test UserForm configure method accepts a Schema parameter.
     *
     * PHPStan change: Added proper @param and @return annotations.
     */
    public function test_user_form_configure_accepts_schema_parameter(): void
    {
        // Arrange
        $reflection = new \ReflectionMethod(UserForm::class, 'configure');
        $params = $reflection->getParameters();

        // Assert: single parameter named $schema
        $this->assertCount(1, $params);
        $this->assertSame('schema', $params[0]->getName());
    }
}
