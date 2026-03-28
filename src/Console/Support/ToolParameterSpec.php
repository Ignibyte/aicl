<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

/**
 * Represents a single parameter in a *.tool.md spec.
 *
 * Parsed from the ## Parameters table:
 * | Name | Type | Required | Description |
 */
class ToolParameterSpec
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $required = false,
        public string $description = '',
    ) {}

    /**
     * Map the spec type to a NeuronAI PropertyType constant name.
     */
    public function neuronAiType(): string
    {
        return match (strtolower($this->type)) {
            'string' => 'PropertyType::STRING',
            'integer', 'int' => 'PropertyType::INTEGER',
            'number', 'float', 'decimal' => 'PropertyType::NUMBER',
            'boolean', 'bool' => 'PropertyType::BOOLEAN',
            'array' => 'PropertyType::ARRAY',
            'object' => 'PropertyType::OBJECT',
            default => 'PropertyType::STRING',
        };
    }

    /**
     * Get the PHP type hint for the __invoke() parameter.
     */
    public function phpType(): string
    {
        return match (strtolower($this->type)) {
            'string' => 'string',
            'integer', 'int' => 'int',
            'number', 'float', 'decimal' => 'float',
            'boolean', 'bool' => 'bool',
            'array' => 'array',
            default => 'string',
        };
    }
}
