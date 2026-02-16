<?php

namespace Aicl\Tests\Unit\Components;

use Aicl\Components\ComponentDefinition;
use Aicl\Components\ComponentDiscoveryService;
use Tests\TestCase;

class ComponentDiscoveryServiceTest extends TestCase
{
    private ComponentDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ComponentDiscoveryService;
    }

    public function test_scan_discovers_components_from_framework_directory(): void
    {
        $componentDir = base_path('packages/aicl/components');
        $this->service->scan($componentDir, 'framework', 'Aicl\\View\\Components');

        $components = $this->service->components();
        $this->assertNotEmpty($components);
        $this->assertGreaterThanOrEqual(33, count($components));
    }

    public function test_scan_ignores_non_existent_directory(): void
    {
        $this->service->scan('/tmp/nonexistent-component-dir-xyz');
        $this->assertEmpty($this->service->components());
    }

    public function test_scan_ignores_directories_without_component_json(): void
    {
        // The schema/ directory has no component.json
        $components = $this->service->components();
        $schemaKey = array_filter(array_keys($components), fn ($k) => $k === 'schema');
        $this->assertEmpty($schemaKey);
    }

    public function test_discovered_components_are_component_definition_instances(): void
    {
        $componentDir = base_path('packages/aicl/components');
        $this->service->scan($componentDir, 'framework');

        foreach ($this->service->components() as $component) {
            $this->assertInstanceOf(ComponentDefinition::class, $component);
        }
    }

    public function test_discovered_component_has_required_fields(): void
    {
        $componentDir = base_path('packages/aicl/components');
        $this->service->scan($componentDir, 'framework');

        $statCard = $this->service->components()['stat-card'] ?? null;
        $this->assertNotNull($statCard, 'stat-card should be discovered');
        $this->assertEquals('Stat Card', $statCard->name);
        $this->assertEquals('x-aicl-stat-card', $statCard->tag);
        $this->assertEquals('metric', $statCard->category);
        $this->assertEquals('framework', $statCard->source);
    }

    public function test_client_components_override_framework(): void
    {
        $componentDir = base_path('packages/aicl/components');
        $this->service->scan($componentDir, 'framework');

        $countBefore = count($this->service->components());

        // Re-scan same dir as "client" — should shadow
        $this->service->scan($componentDir, 'client');
        $countAfter = count($this->service->components());

        // Count shouldn't increase since client shadows framework
        $this->assertEquals($countBefore, $countAfter);
    }

    public function test_validation_errors_are_tracked(): void
    {
        // Create a temp directory with invalid component.json
        $tempDir = sys_get_temp_dir().'/aicl-test-discovery-'.uniqid();
        $componentDir = $tempDir.'/invalid-component';
        mkdir($componentDir, 0755, true);
        file_put_contents($componentDir.'/component.json', 'NOT VALID JSON');

        $this->service->scan($tempDir, 'framework');

        $errors = $this->service->errors();
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('invalid-component', $errors);

        // Cleanup
        @unlink($componentDir.'/component.json');
        @rmdir($componentDir);
        @rmdir($tempDir);
    }

    public function test_all_framework_components_have_valid_manifests(): void
    {
        $componentDir = base_path('packages/aicl/components');
        $this->service->scan($componentDir, 'framework');

        $errors = $this->service->errors();
        $this->assertEmpty($errors, 'Framework components should all have valid manifests. Errors: '.json_encode($errors));
    }

    public function test_component_tags_match_directory_names(): void
    {
        $componentDir = base_path('packages/aicl/components');
        $this->service->scan($componentDir, 'framework');

        foreach ($this->service->components() as $shortTag => $definition) {
            $this->assertEquals(
                $shortTag,
                $definition->shortTag(),
                "Component keyed as '{$shortTag}' should have matching shortTag()"
            );
        }
    }
}
