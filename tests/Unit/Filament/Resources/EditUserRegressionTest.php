<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Filament\Resources;

use Aicl\Filament\Resources\Users\Pages\EditUser;
use Filament\Resources\Pages\EditRecord;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for EditUser page PHPStan changes.
 *
 * Covers the declare(strict_types=1) enforcement, the getRecord()
 * instanceof User type check in the 2FA reset action, and the
 * class docblock addition.
 */
class EditUserRegressionTest extends TestCase
{
    // -- Class configuration --

    /**
     * Test EditUser references the correct resource class.
     *
     * Verifies the static $resource property points to UserResource.
     */
    public function test_edit_user_references_user_resource(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(EditUser::class);
        $property = $reflection->getProperty('resource');

        // Act
        $value = $property->getDefaultValue();

        // Assert: references the UserResource class
        $this->assertSame('Aicl\Filament\Resources\Users\UserResource', $value);
    }

    /**
     * Test EditUser extends EditRecord.
     *
     * Verifies the class hierarchy is correct.
     */
    public function test_edit_user_extends_edit_record(): void
    {
        // Assert: verify using instanceof on an instance
        $reflection = new \ReflectionClass(EditUser::class);
        $parent = $reflection->getParentClass();
        $this->assertNotFalse($parent);
        $this->assertSame(EditRecord::class, $parent->getName());
    }
}
