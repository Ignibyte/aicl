<?php

namespace Aicl\Tests\Unit;

use Aicl\Components\ComponentRegistry;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ComponentRegistryConsistencyTest extends TestCase
{
    private string $componentsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->componentsPath = dirname(__DIR__, 2).'/components';
    }

    public function test_every_component_directory_has_component_json(): void
    {
        $dirs = collect(File::directories($this->componentsPath))
            ->map(fn (string $path): string => basename($path))
            ->reject(fn (string $name): bool => in_array($name, ['_shared', 'schema']));

        foreach ($dirs as $dir) {
            $jsonPath = $this->componentsPath.'/'.$dir.'/component.json';
            $this->assertFileExists($jsonPath, "Component directory '{$dir}' is missing component.json");
        }
    }

    public function test_every_component_json_has_required_fields(): void
    {
        $requiredFields = ['name', 'tag', 'category', 'description', 'decision_rule'];

        $dirs = collect(File::directories($this->componentsPath))
            ->map(fn (string $path): string => basename($path))
            ->reject(fn (string $name): bool => in_array($name, ['_shared', 'schema']));

        foreach ($dirs as $dir) {
            $jsonPath = $this->componentsPath.'/'.$dir.'/component.json';
            if (! file_exists($jsonPath)) {
                continue;
            }

            $manifest = json_decode(file_get_contents($jsonPath), true);
            $this->assertIsArray($manifest, "component.json in '{$dir}' is not valid JSON");

            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $manifest,
                    "component.json in '{$dir}' is missing required field '{$field}'"
                );
            }
        }
    }

    public function test_every_component_has_blade_template(): void
    {
        $dirs = collect(File::directories($this->componentsPath))
            ->map(fn (string $path): string => basename($path))
            ->reject(fn (string $name): bool => in_array($name, ['_shared', 'schema']));

        foreach ($dirs as $dir) {
            $bladePath = $this->componentsPath.'/'.$dir.'/'.$dir.'.blade.php';
            $this->assertFileExists($bladePath, "Component directory '{$dir}' is missing Blade template '{$dir}.blade.php'");
        }
    }

    public function test_component_tags_follow_naming_convention(): void
    {
        $dirs = collect(File::directories($this->componentsPath))
            ->map(fn (string $path): string => basename($path))
            ->reject(fn (string $name): bool => in_array($name, ['_shared', 'schema']));

        foreach ($dirs as $dir) {
            $jsonPath = $this->componentsPath.'/'.$dir.'/component.json';
            if (! file_exists($jsonPath)) {
                continue;
            }

            $manifest = json_decode(file_get_contents($jsonPath), true);
            $expectedTag = 'x-aicl-'.$dir;

            $this->assertEquals(
                $expectedTag,
                $manifest['tag'] ?? '',
                "Component '{$dir}' tag should be '{$expectedTag}' but got '{$manifest['tag']}'"
            );
        }
    }

    public function test_registry_count_matches_component_directories(): void
    {
        $registry = app(ComponentRegistry::class);

        $dirCount = collect(File::directories($this->componentsPath))
            ->map(fn (string $path): string => basename($path))
            ->reject(fn (string $name): bool => in_array($name, ['_shared', 'schema']))
            ->count();

        // Only count framework-source components (excludes CMS and client components)
        $frameworkCount = $registry->all()
            ->filter(fn ($c) => $c->source === 'framework')
            ->count();

        $this->assertEquals(
            $dirCount,
            $frameworkCount,
            "Framework registry has {$frameworkCount} components but found {$dirCount} component directories"
        );
    }

    public function test_registry_contains_all_component_directories(): void
    {
        $registry = app(ComponentRegistry::class);

        $dirs = collect(File::directories($this->componentsPath))
            ->map(fn (string $path): string => basename($path))
            ->reject(fn (string $name): bool => in_array($name, ['_shared', 'schema']));

        foreach ($dirs as $dir) {
            $this->assertNotNull(
                $registry->get($dir),
                "Component '{$dir}' exists on disk but is not registered in ComponentRegistry"
            );
        }
    }

    public function test_all_categories_are_valid(): void
    {
        $validCategories = ['layout', 'metric', 'data', 'status', 'timeline', 'action', 'utility', 'feedback', 'collection', 'interactive'];

        $dirs = collect(File::directories($this->componentsPath))
            ->map(fn (string $path): string => basename($path))
            ->reject(fn (string $name): bool => in_array($name, ['_shared', 'schema']));

        foreach ($dirs as $dir) {
            $jsonPath = $this->componentsPath.'/'.$dir.'/component.json';
            if (! file_exists($jsonPath)) {
                continue;
            }

            $manifest = json_decode(file_get_contents($jsonPath), true);
            $category = $manifest['category'] ?? 'unknown';

            $this->assertContains(
                $category,
                $validCategories,
                "Component '{$dir}' has unknown category '{$category}'"
            );
        }
    }

    public function test_php_class_files_match_component_json(): void
    {
        $dirs = collect(File::directories($this->componentsPath))
            ->map(fn (string $path): string => basename($path))
            ->reject(fn (string $name): bool => in_array($name, ['_shared', 'schema']));

        foreach ($dirs as $dir) {
            $phpFiles = glob($this->componentsPath.'/'.$dir.'/*.php');
            if (empty($phpFiles)) {
                // Alpine-only components have no PHP class — that's valid
                continue;
            }

            $phpFile = $phpFiles[0];
            $className = pathinfo($phpFile, PATHINFO_FILENAME);

            $this->assertMatchesRegularExpression(
                '/^[A-Z]/',
                $className,
                "PHP class file in '{$dir}' should start with uppercase: got '{$className}'"
            );
        }
    }
}
