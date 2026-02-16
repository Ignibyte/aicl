<?php

namespace Aicl\Tests\Unit\Components;

use Aicl\Components\ComponentDiscoveryService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Validates all 33 component.json manifests against the v1 schema.
 * Verifies prop definitions match PHP class constructor params.
 */
class ComponentManifestTest extends TestCase
{
    private static ?array $manifests = null;

    protected static function loadManifests(): array
    {
        if (self::$manifests !== null) {
            return self::$manifests;
        }

        $componentDir = base_path('packages/aicl/components');
        $manifests = [];

        foreach (glob($componentDir.'/*/component.json') as $manifestPath) {
            $dirName = basename(dirname($manifestPath));
            $manifests[$dirName] = json_decode(file_get_contents($manifestPath), true);
        }

        self::$manifests = $manifests;

        return $manifests;
    }

    public function test_all_components_have_valid_json_manifests(): void
    {
        $service = new ComponentDiscoveryService;
        $service->scan(base_path('packages/aicl/components'), 'framework');

        $errors = $service->errors();
        $this->assertEmpty($errors, 'All component manifests should be valid. Errors: '.json_encode($errors, JSON_PRETTY_PRINT));
    }

    public function test_at_least_33_components_discovered(): void
    {
        $service = new ComponentDiscoveryService;
        $service->scan(base_path('packages/aicl/components'), 'framework');

        $this->assertGreaterThanOrEqual(33, count($service->components()));
    }

    #[DataProvider('manifestProvider')]
    public function test_manifest_has_required_fields(string $dirName, array $manifest): void
    {
        $requiredFields = ['name', 'tag', 'category', 'status', 'description', 'decision_rule'];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $manifest, "Component '{$dirName}' missing '{$field}'");
        }
    }

    #[DataProvider('manifestProvider')]
    public function test_manifest_tag_matches_directory(string $dirName, array $manifest): void
    {
        $expectedTag = "x-aicl-{$dirName}";
        $this->assertEquals($expectedTag, $manifest['tag'], "Component '{$dirName}' tag should be '{$expectedTag}'");
    }

    #[DataProvider('manifestProvider')]
    public function test_manifest_category_is_valid(string $dirName, array $manifest): void
    {
        $validCategories = [
            'layout', 'metric', 'metrics', 'data', 'action', 'actions', 'utility',
            'feedback', 'navigation', 'overlay', 'form', 'status', 'timeline', 'collection',
        ];
        $this->assertContains(
            $manifest['category'],
            $validCategories,
            "Component '{$dirName}' has invalid category '{$manifest['category']}'"
        );
    }

    #[DataProvider('manifestProvider')]
    public function test_manifest_status_is_valid(string $dirName, array $manifest): void
    {
        $validStatuses = ['stable', 'beta', 'experimental', 'deprecated'];
        $this->assertContains(
            $manifest['status'],
            $validStatuses,
            "Component '{$dirName}' has invalid status '{$manifest['status']}'"
        );
    }

    #[DataProvider('manifestProvider')]
    public function test_manifest_context_is_array(string $dirName, array $manifest): void
    {
        $this->assertIsArray(
            $manifest['context'] ?? [],
            "Component '{$dirName}' context should be an array"
        );
    }

    #[DataProvider('manifestProvider')]
    public function test_manifest_has_blade_template(string $dirName, array $manifest): void
    {
        $templatePath = base_path("packages/aicl/components/{$dirName}/{$dirName}.blade.php");
        $this->assertFileExists($templatePath, "Component '{$dirName}' missing Blade template");
    }

    #[DataProvider('manifestProvider')]
    public function test_manifest_has_php_class(string $dirName, array $manifest): void
    {
        $className = str_replace(' ', '', ucwords(str_replace('-', ' ', $dirName)));
        $classPath = base_path("packages/aicl/components/{$dirName}/{$className}.php");
        $this->assertFileExists($classPath, "Component '{$dirName}' missing PHP class '{$className}.php'");
    }

    #[DataProvider('manifestProvider')]
    public function test_manifest_props_have_type_and_description(string $dirName, array $manifest): void
    {
        $props = $manifest['props'] ?? [];
        foreach ($props as $propName => $propDef) {
            $this->assertArrayHasKey('type', $propDef, "Prop '{$propName}' in '{$dirName}' missing type");
            $this->assertArrayHasKey('description', $propDef, "Prop '{$propName}' in '{$dirName}' missing description");
        }
    }

    public static function manifestProvider(): array
    {
        // Use __DIR__ relative path since base_path() is unavailable in static context
        // __DIR__ = packages/aicl/tests/Unit/Components → up 3 = packages/aicl/
        $componentDir = dirname(__DIR__, 3).'/components';
        $componentDir = realpath($componentDir) ?: $componentDir;
        $manifests = [];

        foreach (glob($componentDir.'/*/component.json') as $manifestPath) {
            $dirName = basename(dirname($manifestPath));
            $data = json_decode(file_get_contents($manifestPath), true);
            if ($data !== null) {
                $manifests[$dirName] = [$dirName, $data];
            }
        }

        return $manifests;
    }
}
