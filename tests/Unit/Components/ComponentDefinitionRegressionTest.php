<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Components;

use Aicl\Components\ComponentDefinition;
use Tests\TestCase;

/**
 * Regression tests for ComponentDefinition PHPStan changes.
 *
 * Tests the PHPDoc type annotations added to constructor parameters
 * and method return types. Verifies that serialization and deserialization
 * work correctly under strict_types with typed arrays.
 */
class ComponentDefinitionRegressionTest extends TestCase
{
    /**
     * Test toArray serializes all typed properties correctly.
     *
     * PHPStan change: Added @return array<string, mixed> annotation.
     * Verifies all properties serialize to the expected types.
     */
    public function test_to_array_serializes_all_properties(): void
    {
        // Arrange: create a fully populated component definition with correct constructor args
        $def = $this->createTestDefinition('stat-card');

        // Act
        $array = $def->toArray();

        // Assert: should produce an array with all expected keys
        $this->assertSame('stat-card', $array['name']);
        $this->assertSame('x-aicl-stat-card', $array['tag']);
        $this->assertSame('metric', $array['category']);
        $this->assertIsArray($array['context']);
    }

    /**
     * Test fromArray restores a ComponentDefinition from cached data.
     *
     * PHPStan change: Added @param array<string, mixed> annotation.
     */
    public function test_from_array_restores_component_definition(): void
    {
        // Arrange: create a definition and serialize it
        $original = $this->createTestDefinition('alert-banner');
        $cached = $original->toArray();

        // Act: restore from array
        $restored = ComponentDefinition::fromArray($cached);

        // Assert: should reconstruct identically
        $this->assertSame($original->name, $restored->name);
        $this->assertSame($original->tag, $restored->tag);
        $this->assertSame($original->category, $restored->category);
    }

    /**
     * Test requiredProps returns only required property names.
     *
     * PHPStan change: Added @return array<int, string> annotation.
     */
    public function test_required_props_returns_string_array(): void
    {
        // Arrange: definition with mixed required/optional props
        $def = $this->createTestDefinition('kpi-card', [
            'label' => ['type' => 'string', 'required' => true],
            'value' => ['type' => 'int', 'required' => true],
            'icon' => ['type' => 'string', 'required' => false],
        ]);

        // Act
        $required = $def->requiredProps();

        // Assert: should return only required prop names as strings
        $this->assertContains('label', $required);
        $this->assertContains('value', $required);
        $this->assertNotContains('icon', $required);
    }

    /**
     * Test isExcludedFrom checks notFor list correctly.
     *
     * Verifies typed context arrays work under strict_types.
     */
    public function test_is_excluded_from_checks_not_for_list(): void
    {
        // Arrange: create definition with notFor entries
        $def = $this->createTestDefinition('test-comp', [], ['page'], ['form', 'modal']);

        // Assert: 'form' is in notFor, 'page' is not
        $this->assertTrue($def->isExcludedFrom('form'));
        $this->assertTrue($def->isExcludedFrom('modal'));
        $this->assertFalse($def->isExcludedFrom('page'));
    }

    /**
     * Helper: create a ComponentDefinition with the correct constructor signature.
     *
     * @param  array<string, mixed>  $props
     * @param  array<int, string>  $context
     * @param  array<int, string>  $notFor
     */
    private function createTestDefinition(
        string $name,
        array $props = [],
        array $context = [],
        array $notFor = [],
    ): ComponentDefinition {
        return new ComponentDefinition(
            name: $name,
            tag: "x-aicl-{$name}",
            class: 'Aicl\\View\\Components\\'.str_replace('-', '', ucwords($name, '-')),
            template: "components/{$name}.blade.php",
            jsModule: null,
            category: 'metric',
            status: 'stable',
            description: "Test component: {$name}",
            context: $context,
            notFor: $notFor,
            props: $props,
            slots: [],
            variants: [],
            composableIn: [],
            decisionRule: '',
            fieldSignals: [],
            filamentEquivalent: null,
            entityDisplay: null,
            source: 'package',
        );
    }
}
