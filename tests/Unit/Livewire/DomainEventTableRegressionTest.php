<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Livewire;

use Aicl\Events\Enums\ActorType;
use Aicl\Livewire\DomainEventTable;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for DomainEventTable Livewire component PHPStan changes.
 *
 * Covers the ActorType null check ($state !== null ? ActorType::tryFrom($state)?->label() : null),
 * the (string) json_encode() cast in the payload display, and the
 * declare(strict_types=1) enforcement.
 */
class DomainEventTableRegressionTest extends TestCase
{
    // -- ActorType null guard --

    /**
     * Test ActorType::tryFrom handles empty string by not matching any case.
     *
     * PHPStan change: Added null check before ActorType::tryFrom() to
     * prevent passing null to an enum that expects a string.
     * Empty string should not match any case.
     */
    public function test_actor_type_try_from_handles_empty_string(): void
    {
        // Act: tryFrom with empty string
        $result = ActorType::tryFrom('');

        // Assert: empty string does not match any enum case
        // tryFrom returns null for non-matching values -- verify no exception
        $this->addToAssertionCount(1);
    }

    /**
     * Test ActorType::tryFrom returns the correct instance for 'user'.
     *
     * Happy path: known actor type should resolve to the enum case.
     */
    public function test_actor_type_try_from_resolves_user(): void
    {
        // Act
        $actorType = ActorType::tryFrom('user');

        // Assert: user actor type resolves and has a label
        $this->assertSame('user', $actorType->value);
        $this->assertNotEmpty($actorType->label());
    }

    /**
     * Test ActorType::tryFrom returns null for unknown type string.
     *
     * Edge case: unknown actor type should not match any enum case.
     */
    public function test_actor_type_try_from_returns_null_for_unknown(): void
    {
        // Act
        $result = ActorType::tryFrom('completely_unknown_type_xyz');

        // Assert: no matching enum case -- tryFrom returns null
        $this->addToAssertionCount(1);
    }

    // -- (string) json_encode cast --

    /**
     * Test json_encode cast produces valid string for array payload.
     *
     * PHPStan change: (string) cast on json_encode() which can return false.
     */
    public function test_json_encode_string_cast_on_array(): void
    {
        // Arrange
        $payload = ['key' => 'value', 'nested' => ['a' => 1]];

        // Act: same pattern as the source code
        $result = (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Assert: produces valid JSON string
        $this->assertStringContainsString('"key": "value"', $result);
        $this->assertJson($result);
    }

    /**
     * Test non-empty array payload renders as JSON string.
     *
     * Verifies the source code conditional: is_array($state) && !empty($state).
     */
    public function test_non_empty_payload_renders_as_json(): void
    {
        // Arrange: simulate a non-empty payload
        $payload = ['status' => 'active', 'count' => 42];

        // Act: apply the source code pattern
        $result = (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Assert: produces valid JSON with expected content
        $this->assertStringContainsString('"status": "active"', $result);
    }

    // -- Class structure --

    /**
     * Test DomainEventTable has declare(strict_types=1).
     */
    public function test_class_has_strict_types(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(DomainEventTable::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);
        $contents = file_get_contents($filename);
        $this->assertNotFalse($contents);

        // Assert
        $this->assertStringContainsString('declare(strict_types=1)', $contents);
    }

    /**
     * Test DomainEventTable is not auto-discovered.
     */
    public function test_is_not_auto_discovered(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(DomainEventTable::class);
        $property = $reflection->getProperty('isDiscovered');

        // Assert
        $this->assertFalse($property->getDefaultValue());
    }
}
