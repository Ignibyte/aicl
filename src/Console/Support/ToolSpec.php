<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

/**
 * Value object representing a parsed *.tool.md specification file.
 *
 * Contains all the structured data needed to generate an AI tool class.
 */
class ToolSpec
{
    /**
     * @param string                          $className       PascalCase class name (e.g., 'ProjectSummary')
     * @param string                          $description     Human-readable description paragraph
     * @param string                          $name            Snake_case tool name for the LLM (e.g., 'project_summary')
     * @param string                          $category        Tool category for UI grouping (e.g., 'queries', 'system')
     * @param bool                            $authRequired    Whether the tool requires authentication
     * @param string                          $toolDescription LLM-facing description explaining when to use the tool
     * @param array<int, ToolParameterSpec>   $parameters      Input parameters
     * @param array<int, ToolReturnFieldSpec> $returns         Return field definitions
     */
    public function __construct(
        public string $className,
        public string $description,
        public string $name,
        public string $category = 'general',
        public bool $authRequired = false,
        public string $toolDescription = '',
        public array $parameters = [],
        public array $returns = [],
    ) {}

    /**
     * Whether the tool has any parameters.
     */
    public function hasParameters(): bool
    {
        return ! empty($this->parameters);
    }

    /**
     * Whether the tool defines return fields.
     */
    public function hasReturns(): bool
    {
        return ! empty($this->returns);
    }

    /**
     * Get only the required parameters.
     *
     * @return array<int, ToolParameterSpec>
     */
    public function requiredParameters(): array
    {
        return array_values(array_filter(
            $this->parameters,
            fn (ToolParameterSpec $p): bool => $p->required,
        ));
    }

    /**
     * Get only the optional parameters.
     *
     * @return array<int, ToolParameterSpec>
     */
    public function optionalParameters(): array
    {
        return array_values(array_filter(
            $this->parameters,
            fn (ToolParameterSpec $p): bool => ! $p->required,
        ));
    }
}
