<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\NotificationTemplateResolver;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for NotificationTemplateResolver PHPStan changes.
 *
 * Tests the declare(strict_types=1) addition and PHPDoc type
 * annotation improvements for the template resolution methods.
 * resolveBody() takes a single template string argument.
 */
class NotificationTemplateResolverRegressionTest extends TestCase
{
    /**
     * Test resolveBody resolves model template variables.
     *
     * PHPStan change: preg_replace_callback() ?? $result ensures null safety.
     */
    public function test_resolve_body_resolves_model_variables(): void
    {
        // Arrange
        $resolver = new NotificationTemplateResolver('Task');

        // Act: resolve a template with model variables
        $result = $resolver->resolveBody('Task {model.name} was created');

        // Assert: should replace {model.name} with PHP interpolation
        $this->assertStringContainsString('task', $result);
        $this->assertStringNotContainsString('{model.name}', $result);
    }

    /**
     * Test resolveBody resolves actor variables.
     *
     * Verifies string replacement under strict_types.
     */
    public function test_resolve_body_resolves_actor_variables(): void
    {
        // Arrange
        $resolver = new NotificationTemplateResolver('Order');

        // Act
        $result = $resolver->resolveBody('{actor.name} updated the order');

        // Assert: should replace {actor.name}
        $this->assertStringNotContainsString('{actor.name}', $result);
    }

    /**
     * Test resolveBody resolves status change variables.
     *
     * Verifies old/new status template resolution.
     */
    public function test_resolve_body_resolves_status_variables(): void
    {
        // Arrange
        $resolver = new NotificationTemplateResolver('Task');

        // Act
        $result = $resolver->resolveBody('Status changed from {old.status.label} to {new.status.label}');

        // Assert: should replace both old and new status variables
        $this->assertStringNotContainsString('{old.status.label}', $result);
        $this->assertStringNotContainsString('{new.status.label}', $result);
    }

    /**
     * Test resolveBody handles templates without variables.
     *
     * Edge case: plain text should pass through unchanged.
     */
    public function test_resolve_body_handles_plain_text(): void
    {
        // Arrange
        $resolver = new NotificationTemplateResolver('Task');

        // Act
        $result = $resolver->resolveBody('A new task has been created.');

        // Assert: should pass through unchanged
        $this->assertSame('A new task has been created.', $result);
    }
}
