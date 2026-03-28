<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

use InvalidArgumentException;

/**
 * RelationshipParser.
 */
class RelationshipParser
{
    use ParsesCommaSeparatedDefinitions;

    /**
     * @var array<int, string>
     */
    protected const SUPPORTED_TYPES = [
        'hasMany',
        'hasOne',
        'belongsToMany',
        'morphMany',
    ];

    /**
     * Parse a single relationship segment like "tasks:hasMany:Task".
     *
     * @param  array<int, string>  $seenNames
     */
    protected function parseSegment(string $segment, array $seenNames): RelationshipDefinition
    {
        $parts = explode(':', $segment);

        if (count($parts) < 3) {
            throw new InvalidArgumentException(
                "Invalid relationship definition: '{$segment}'. Expected format: name:type:Model[:foreign_key]"
            );
        }

        $name = $parts[0];
        $type = $parts[1];
        $model = $parts[2];
        $foreignKey = $parts[3] ?? null;

        $this->validateName($name, $seenNames);
        $this->validateType($type, $name);
        $this->validateModel($model, $name);

        return new RelationshipDefinition(
            name: $name,
            type: $type,
            relatedModel: $model,
            foreignKey: $foreignKey,
        );
    }

    /**
     * @param  array<int, string>  $seenNames
     */
    protected function validateName(string $name, array $seenNames): void
    {
        if (! preg_match('/^[a-z][a-zA-Z0-9]*$/', $name)) {
            throw new InvalidArgumentException(
                "Relationship name '{$name}' must be camelCase."
            );
        }

        if (in_array($name, $seenNames, true)) {
            throw new InvalidArgumentException(
                "Duplicate relationship name: '{$name}'."
            );
        }
    }

    protected function validateType(string $type, string $name): void
    {
        if (! in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new InvalidArgumentException(
                "Unknown relationship type: '{$type}' for '{$name}'. Supported: ".implode(', ', self::SUPPORTED_TYPES)
            );
        }
    }

    protected function validateModel(string $model, string $name): void
    {
        if (! preg_match('/^[A-Z][a-zA-Z0-9]+$/', $model)) {
            throw new InvalidArgumentException(
                "Model name '{$model}' for relationship '{$name}' must be PascalCase."
            );
        }
    }
}
