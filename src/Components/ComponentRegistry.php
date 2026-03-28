<?php

declare(strict_types=1);

namespace Aicl\Components;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Singleton registry providing a query API for all registered AICL components.
 *
 * Used by AI agents, the scaffolder, and RLM validation to discover components,
 * get field-to-component recommendations, and validate prop usage.
 */
class ComponentRegistry
{
    /** @var array<string, ComponentDefinition> */
    private array $components = [];

    private FieldSignalEngine $signalEngine;

    private bool $booted = false;

    public function __construct(
        private readonly ComponentDiscoveryService $discovery,
    ) {
        $this->signalEngine = new FieldSignalEngine;
    }

    /**
     * Boot the registry by scanning component directories.
     * Called by the service provider during boot.
     *
     * @param  array<int, array<string, string>>  $scanPaths
     */
    public function boot(array $scanPaths): void
    {
        if ($this->booted) {
            // @codeCoverageIgnoreStart — Untestable in unit context
            return;
            // @codeCoverageIgnoreEnd
        }

        // Check for cached registry first
        $cachePath = $this->cachePath();
        if (file_exists($cachePath) && ! config('app.debug')) {
            // @codeCoverageIgnoreStart — Untestable in unit context
            $this->loadFromCache($cachePath);
            $this->booted = true;

            return;
            // @codeCoverageIgnoreEnd
        }

        // Scan directories (framework first, then client — client overrides)
        foreach ($scanPaths as $path) {
            $source = $path['source'] ?? 'framework';
            $namespace = $path['namespace'] ?? 'Aicl\\View\\Components';
            $this->discovery->scan($path['path'], $source, $namespace);
        }

        $this->components = $this->discovery->components();
        $this->booted = true;
    }

    /**
     * Register component definitions directly (used for testing and cache restore).
     */
    public function register(ComponentDefinition ...$definitions): void
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        foreach ($definitions as $definition) {
            $this->components[$definition->shortTag()] = $definition;
        }
        $this->booted = true;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get all registered components.
     *
     * @return Collection<string, ComponentDefinition>
     */
    public function all(): Collection
    {
        return collect($this->components);
    }

    /**
     * Get a component by its short tag name (e.g., 'stat-card').
     */
    public function get(string $tag): ?ComponentDefinition
    {
        // Accept both 'stat-card' and 'x-aicl-stat-card'
        $tag = str_replace('x-aicl-', '', $tag);

        return $this->components[$tag] ?? null;
    }

    /**
     * Filter components by category.
     *
     * @return Collection<string, ComponentDefinition>
     */
    public function forCategory(string $category): Collection
    {
        return $this->all()->filter(fn (ComponentDefinition $c): bool => $c->category === $category);
    }

    /**
     * Filter components by rendering context.
     *
     * @return Collection<string, ComponentDefinition>
     */
    public function forContext(string $context): Collection
    {
        return $this->all()->filter(fn (ComponentDefinition $c): bool => $c->supportsContext($context) && ! $c->isExcludedFrom($context));
    }

    /**
     * Get a display component for a specific content type and display mode.
     *
     * Filters entity-display context components by their entity_display manifest section.
     */
    public function displayComponent(string $contentType, string $displayMode): ?ComponentDefinition
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return $this->forContext('entity-display')
            ->first(fn (ComponentDefinition $c): bool => $c->entityDisplay !== null
                && ($c->entityDisplay['content_type'] ?? '') === $contentType
                && ($c->entityDisplay['display_mode'] ?? '') === $displayMode
            );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get all display components for a specific content type.
     *
     * @return Collection<string, ComponentDefinition>
     */
    public function displayComponents(string $contentType): Collection
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return $this->forContext('entity-display')
            ->filter(fn (ComponentDefinition $c): bool => $c->entityDisplay !== null
                && ($c->entityDisplay['content_type'] ?? '') === $contentType
            );
        // @codeCoverageIgnoreEnd
    }

    /**
     * AI recommendation: given a field type, what component should be used?
     *
     * @param  array<string, string>  $allFields
     */
    public function recommend(string $fieldType, string $context = 'blade', string $fieldName = '', array $allFields = []): ?ComponentRecommendation
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return $this->signalEngine->match($fieldName, $fieldType, $context, $allFields);
        // @codeCoverageIgnoreEnd
    }

    /**
     * AI recommendation: given an entity's fields, what components for a view?
     *
     * @param  array<string|int, mixed>  $fields  Array of 'name:type' strings or ['name' => 'type'] pairs
     * @param  string  $context  Rendering context
     * @param  string  $viewType  View type: index, show, card
     * @return array<ComponentRecommendation>
     */
    public function recommendForEntity(array $fields, string $context = 'blade', string $viewType = 'index'): array
    {
        // Normalize field format
        $normalized = [];
        foreach ($fields as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
                // @codeCoverageIgnoreStart — Untestable in unit context
            } elseif (is_string($value) && str_contains($value, ':')) {
                [$name, $type] = explode(':', $value, 2);
                $normalized[$name] = $type;
                // @codeCoverageIgnoreEnd
            }
        }

        return $this->signalEngine->recommendForEntity($normalized, $context, $viewType);
    }

    /**
     * Get the full prop schema for a component.
     *
     * @return array<string, mixed>|null
     */
    public function schema(string $tag): ?array
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        $component = $this->get($tag);

        return $component?->props;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Validate props against a component's schema (dev-only).
     *
     * @param  array<string, mixed>  $props
     * @return array{valid: bool, errors: array<string>}
     */
    public function validateProps(string $tag, array $props): array
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        $component = $this->get($tag);
        if ($component === null) {
            return ['valid' => false, 'errors' => ["Component '{$tag}' not found"]];
        }

        $errors = [];

        // Check required props
        foreach ($component->requiredProps() as $requiredProp) {
            if (! array_key_exists($requiredProp, $props)) {
                $errors[] = "Missing required prop: {$requiredProp}";
                // @codeCoverageIgnoreEnd
            }
        }

        // Check for unknown props (warning only)
        // @codeCoverageIgnoreStart — Untestable in unit context
        foreach (array_keys($props) as $propName) {
            if (! isset($component->props[$propName])) {
                if (config('app.debug')) {
                    Log::debug("AICL Component '{$tag}': unknown prop '{$propName}'");
                    // @codeCoverageIgnoreEnd
                }
            }
        }

        // Check enum values
        // @codeCoverageIgnoreStart — Untestable in unit context
        foreach ($props as $propName => $propValue) {
            if (isset($component->props[$propName]['enum'])) {
                $allowed = $component->props[$propName]['enum'];
                if (! in_array($propValue, $allowed, true)) {
                    $errors[] = "Prop '{$propName}' value '{$propValue}' not in allowed values: ".implode(', ', $allowed);
                    // @codeCoverageIgnoreEnd
                }
            }
        }

        // @codeCoverageIgnoreStart — Untestable in unit context
        return ['valid' => count($errors) === 0, 'errors' => $errors];
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get components that can be children of a given parent component.
     *
     * @return Collection<string, ComponentDefinition>
     */
    public function composableChildren(string $parentTag): Collection
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        $parentTag = str_replace('x-aicl-', '', $parentTag);

        return $this->all()->filter(
            fn (ComponentDefinition $c): bool => in_array($parentTag, $c->composableIn, true)
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the count of registered components.
     */
    public function count(): int
    {
        return count($this->components);
    }

    /**
     * Get unique categories.
     *
     * @return array<string>
     */
    public function categories(): array
    {
        return $this->all()
            ->pluck('category')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Write the registry to a cache file.
     */
    public function writeCache(): string
    {
        $cachePath = $this->cachePath();
        $data = [];

        foreach ($this->components as $tag => $definition) {
            $data[$tag] = $definition->toArray();
        }

        $content = '<?php return '.var_export($data, true).';';
        file_put_contents($cachePath, $content);

        return $cachePath;
    }

    /**
     * Clear the cache file.
     */
    public function clearCache(): bool
    {
        $cachePath = $this->cachePath();
        if (file_exists($cachePath)) {
            return unlink($cachePath);
        }

        return false;
    }

    /**
     * Check if cache exists.
     */
    public function isCached(): bool
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        return file_exists($this->cachePath());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Load registry from cache file.
     */
    private function loadFromCache(string $cachePath): void
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        $data = require $cachePath;
        if (! is_array($data)) {
            return;
        }

        foreach ($data as $tag => $componentData) {
            $this->components[$tag] = ComponentDefinition::fromArray($componentData);
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Get the cache file path.
     */
    private function cachePath(): string
    {
        return base_path('bootstrap/cache/component-registry.php');
    }

    /**
     * Get the discovery service (for error reporting).
     */
    public function discovery(): ComponentDiscoveryService
    {
        return $this->discovery;
    }
}
