<?php

namespace Aicl\Components;

use Illuminate\Support\Facades\Log;

/**
 * Scans directories for SDC component directories containing component.json manifests.
 *
 * Discovery locations (priority order):
 * 1. packages/aicl/components/ — Framework components (lowest priority, overridable)
 * 2. app/Components/ — Client components (highest priority, can shadow framework)
 */
class ComponentDiscoveryService
{
    /** @var array<string, ComponentDefinition> Discovered components keyed by short tag */
    private array $components = [];

    /** @var array<string, array<string>> Validation errors per component directory */
    private array $errors = [];

    private string $schemaPath;

    public function __construct()
    {
        $this->schemaPath = dirname(__DIR__, 2).'/components/schema/component-v1.json';
    }

    /**
     * Scan a directory for component subdirectories containing component.json files.
     *
     * @param  string  $directory  Absolute path to scan
     * @param  string  $source  Source identifier: 'framework' or 'client'
     * @param  string  $namespace  PHP namespace prefix for component classes
     */
    public function scan(string $directory, string $source = 'framework', string $namespace = 'Aicl\\View\\Components'): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $dirs = glob($directory.'/*', GLOB_ONLYDIR);
        if ($dirs === false) {
            return;
        }

        foreach ($dirs as $componentDir) {
            $manifestPath = $componentDir.'/component.json';
            if (! file_exists($manifestPath)) {
                continue;
            }

            $this->discoverComponent($componentDir, $manifestPath, $source, $namespace);
        }
    }

    /**
     * Parse and validate a single component directory.
     */
    private function discoverComponent(string $dir, string $manifestPath, string $source, string $namespace): void
    {
        $dirName = basename($dir);
        $json = file_get_contents($manifestPath);

        if ($json === false) {
            $this->errors[$dirName][] = "Cannot read component.json at {$manifestPath}";

            return;
        }

        $manifest = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[$dirName][] = 'Invalid JSON: '.json_last_error_msg();

            return;
        }

        // Validate against schema
        $validationErrors = $this->validateManifest($manifest, $dirName);
        if (count($validationErrors) > 0) {
            $this->errors[$dirName] = $validationErrors;

            if (config('app.debug')) {
                Log::warning("AICL Component '{$dirName}' has validation errors", $validationErrors);
            }

            return;
        }

        // Resolve PHP class
        $className = $this->resolveClassName($dir, $namespace);
        if ($className === null) {
            $this->errors[$dirName][] = "No PHP class found in {$dir}";

            return;
        }

        // Resolve template
        $template = $this->resolveTemplate($dir, $dirName);

        // Resolve JS module
        $jsModule = $this->resolveJsModule($dir, $dirName);

        $shortTag = str_replace('x-aicl-', '', $manifest['tag']);

        // Client components override framework components with same tag
        $definition = ComponentDefinition::fromManifest(
            manifest: $manifest,
            class: $className,
            template: $template,
            jsModule: $jsModule,
            source: $source,
        );

        $this->components[$shortTag] = $definition;
    }

    /**
     * Validate a manifest against the v1 schema.
     *
     * @return array<string> List of validation errors
     */
    public function validateManifest(array $manifest, string $componentName = 'unknown'): array
    {
        $errors = [];

        // Required fields
        $required = ['$schema', 'name', 'tag', 'category', 'status', 'description', 'context', 'props', 'decision_rule'];
        foreach ($required as $field) {
            if (! isset($manifest[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        if (count($errors) > 0) {
            return $errors;
        }

        // Schema version
        if ($manifest['$schema'] !== 'aicl-component-v1') {
            $errors[] = "Invalid schema version: expected 'aicl-component-v1', got '{$manifest['$schema']}'";
        }

        // Tag format
        if (! preg_match('/^x-aicl-[a-z][a-z0-9-]*$/', $manifest['tag'])) {
            $errors[] = "Invalid tag format: '{$manifest['tag']}' — must match x-aicl-{name}";
        }

        // Category enum
        $validCategories = ['metric', 'data', 'collection', 'action', 'status', 'timeline', 'layout', 'feedback', 'utility'];
        if (! in_array($manifest['category'], $validCategories, true)) {
            $errors[] = "Invalid category: '{$manifest['category']}' — must be one of: ".implode(', ', $validCategories);
        }

        // Status enum
        $validStatuses = ['experimental', 'stable', 'deprecated'];
        if (! in_array($manifest['status'], $validStatuses, true)) {
            $errors[] = "Invalid status: '{$manifest['status']}' — must be one of: ".implode(', ', $validStatuses);
        }

        // Context array
        if (! is_array($manifest['context']) || count($manifest['context']) === 0) {
            $errors[] = 'Context must be a non-empty array';
        } else {
            $validContexts = ['blade', 'livewire', 'filament-widget', 'email', 'pdf'];
            foreach ($manifest['context'] as $ctx) {
                if (! in_array($ctx, $validContexts, true)) {
                    $errors[] = "Invalid context: '{$ctx}'";
                }
            }
        }

        // Props must be an object
        if (! is_array($manifest['props'])) {
            $errors[] = 'Props must be an object';
        }

        return $errors;
    }

    /**
     * Find the PHP class file in a component directory.
     */
    private function resolveClassName(string $dir, string $namespace): ?string
    {
        $files = glob($dir.'/*.php');
        if ($files === false || count($files) === 0) {
            return null;
        }

        // Take the first .php file (should be one class per component)
        $file = $files[0];
        $className = pathinfo($file, PATHINFO_FILENAME);

        return $namespace.'\\'.$className;
    }

    /**
     * Resolve the Blade template view name.
     */
    private function resolveTemplate(string $dir, string $dirName): string
    {
        $bladeFile = $dir.'/'.$dirName.'.blade.php';
        if (file_exists($bladeFile)) {
            return $bladeFile;
        }

        // Fallback: look for any .blade.php
        $files = glob($dir.'/*.blade.php');
        if ($files !== false && count($files) > 0) {
            return $files[0];
        }

        return $dir.'/'.$dirName.'.blade.php';
    }

    /**
     * Resolve the JS module file path.
     */
    private function resolveJsModule(string $dir, string $dirName): ?string
    {
        $jsFile = $dir.'/'.$dirName.'.js';

        return file_exists($jsFile) ? $jsFile : null;
    }

    /**
     * Get all discovered components.
     *
     * @return array<string, ComponentDefinition>
     */
    public function components(): array
    {
        return $this->components;
    }

    /**
     * Get validation errors from the last scan.
     *
     * @return array<string, array<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Check if scanning found any errors.
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Reset discovered components and errors.
     */
    public function reset(): void
    {
        $this->components = [];
        $this->errors = [];
    }
}
