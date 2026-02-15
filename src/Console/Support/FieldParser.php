<?php

namespace Aicl\Console\Support;

use InvalidArgumentException;

class FieldParser
{
    use ParsesCommaSeparatedDefinitions;

    /**
     * @var array<int, string>
     */
    protected const SUPPORTED_TYPES = [
        'string',
        'text',
        'integer',
        'float',
        'boolean',
        'date',
        'datetime',
        'enum',
        'json',
        'foreignId',
    ];

    /**
     * @var array<int, string>
     */
    protected const RESERVED_COLUMNS = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * @var array<int, string>
     */
    protected const TYPES_REQUIRING_ARGUMENT = [
        'enum',
        'foreignId',
    ];

    /**
     * Parse a single field segment like "title:string:nullable".
     *
     * @param  array<int, string>  $seenNames
     */
    protected function parseSegment(string $segment, array $seenNames): FieldDefinition
    {
        $parts = explode(':', $segment);

        if (count($parts) < 2) {
            throw new InvalidArgumentException(
                "Invalid field definition: '{$segment}'. Expected format: name:type[:modifier1][:modifier2]"
            );
        }

        $name = $parts[0];
        $type = $parts[1];
        $remaining = array_slice($parts, 2);

        $this->validateName($name, $seenNames);
        $this->validateType($type, $name);

        $typeArgument = null;
        $modifiers = $remaining;

        if (in_array($type, self::TYPES_REQUIRING_ARGUMENT, true)) {
            if (empty($remaining)) {
                $hint = $type === 'enum'
                    ? "{$name}:enum:ClassName"
                    : "{$name}:foreignId:tableName";

                throw new InvalidArgumentException(
                    ucfirst($type)." field '{$name}' requires an argument: {$hint}"
                );
            }

            $typeArgument = array_shift($modifiers);
            $this->validateTypeArgument($type, $name, $typeArgument);
        }

        return $this->buildDefinition($name, $type, $typeArgument, $modifiers);
    }

    /**
     * @param  array<int, string>  $seenNames
     */
    protected function validateName(string $name, array $seenNames): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException(
                "Field name '{$name}' must be snake_case (letters, numbers, underscores)."
            );
        }

        if (in_array($name, self::RESERVED_COLUMNS, true)) {
            throw new InvalidArgumentException(
                "Field name '{$name}' is reserved (id, created_at, updated_at, deleted_at)."
            );
        }

        if (in_array($name, $seenNames, true)) {
            throw new InvalidArgumentException(
                "Duplicate field name: '{$name}'."
            );
        }
    }

    protected function validateType(string $type, string $name): void
    {
        if (! in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new InvalidArgumentException(
                "Unknown field type: '{$type}'. Supported: ".implode(', ', self::SUPPORTED_TYPES)
            );
        }
    }

    protected function validateTypeArgument(string $type, string $name, string $argument): void
    {
        if ($type === 'enum' && ! preg_match('/^[A-Z][a-zA-Z0-9]+$/', $argument)) {
            throw new InvalidArgumentException(
                "Enum class name '{$argument}' for field '{$name}' must be PascalCase."
            );
        }

        if ($type === 'foreignId' && ! preg_match('/^[a-z][a-z0-9_]*$/', $argument)) {
            throw new InvalidArgumentException(
                "Table name '{$argument}' for field '{$name}' must be snake_case."
            );
        }
    }

    /**
     * @param  array<int, string>  $modifiers
     */
    protected function buildDefinition(
        string $name,
        string $type,
        ?string $typeArgument,
        array $modifiers,
    ): FieldDefinition {
        $nullable = false;
        $unique = false;
        $default = null;
        $indexed = false;

        foreach ($modifiers as $modifier) {
            if ($modifier === 'nullable') {
                $nullable = true;
            } elseif ($modifier === 'unique') {
                $unique = true;
            } elseif ($modifier === 'index') {
                $indexed = true;
            } elseif (preg_match('/^default\((.+)\)$/', $modifier, $matches)) {
                $default = $matches[1];
            } else {
                throw new InvalidArgumentException(
                    "Unknown modifier: '{$modifier}'. Supported: nullable, unique, default(value), index"
                );
            }
        }

        // Apply type-specific defaults per design doc
        if (in_array($type, ['text', 'date', 'datetime', 'json'], true) && ! $nullable) {
            $nullable = true;
        }

        if ($type === 'boolean' && $default === null) {
            $default = 'true';
        }

        return new FieldDefinition(
            name: $name,
            type: $type,
            typeArgument: $typeArgument,
            nullable: $nullable,
            unique: $unique,
            default: $default,
            indexed: $indexed,
        );
    }
}
