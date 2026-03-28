<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use InvalidArgumentException;

/**
 * FilterRegistry.
 */
class FilterRegistry
{
    /** @var array<string, TemplateFilter> */
    private array $filters = [];

    /**
     * Register a filter by name.
     */
    public function register(string $name, TemplateFilter $filter): void
    {
        $this->filters[$name] = $filter;
    }

    /**
     * Resolve a filter by name.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(string $name): TemplateFilter
    {
        if (! isset($this->filters[$name])) {
            throw new InvalidArgumentException("Template filter [{$name}] is not registered.");
        }

        return $this->filters[$name];
    }

    /**
     * Check if a filter is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->filters[$name]);
    }

    /**
     * Get all registered filters.
     *
     * @return array<string, TemplateFilter>
     */
    public function all(): array
    {
        return $this->filters;
    }
}
