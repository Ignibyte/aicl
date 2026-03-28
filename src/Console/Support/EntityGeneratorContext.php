<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

use Illuminate\Support\Str;

/**
 * Immutable context DTO shared by all entity generators.
 *
 * Captures all state from the MakeEntityCommand that generators need
 * to produce their output files. Generators receive this DTO via
 * constructor injection and must not modify it.
 */
class EntityGeneratorContext
{
    public function __construct(
        public readonly string $name,
        public readonly string $tableName,
        /** @var array<int, FieldDefinition>|null */
        public readonly ?array $fields,
        /** @var array<int, string> */
        public readonly array $states,
        /** @var array<int, RelationshipDefinition> */
        public readonly array $relationships,
        /** @var array<int, string> */
        public readonly array $traits,
        public readonly bool $smartMode,
        public readonly ?BaseSchemaInspector $baseInspector,
        public readonly ?EntitySpec $entitySpec,
        /** @var array<string, array<int, array{case: string, label: string, color?: string, icon?: string}>> */
        public readonly array $specEnums,
        public readonly bool $generateFilament,
        public readonly bool $generateApi,
        public readonly bool $generateWidgets,
        public readonly bool $generateNotifications,
        public readonly bool $generatePdf,
        public readonly bool $generateAiContext,
    ) {}

    /**
     * Check if the entity has state machine states defined.
     */
    public function hasStates(): bool
    {
        return ! empty($this->states);
    }

    /**
     * Check if any field is an enum that needs generation.
     */
    public function hasEnums(): bool
    {
        if ($this->fields === null) {
            return false;
        }

        foreach ($this->fields as $field) {
            if ($field->isEnum()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the snake_case name.
     */
    public function snakeName(): string
    {
        return Str::snake($this->name);
    }

    /**
     * Get the plural StudlyCase name.
     */
    public function pluralName(): string
    {
        return Str::pluralStudly($this->name);
    }

    /**
     * Check if the base class has a specific column.
     */
    public function baseHasColumn(string $column): bool
    {
        return $this->baseInspector !== null && $this->baseInspector->hasColumn($column);
    }

    /**
     * Check if a field name is explicitly defined in the fields array.
     */
    public function hasExplicitField(string $name): bool
    {
        if ($this->fields === null) {
            return false;
        }

        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the first string field name for display, or 'name' as default.
     */
    public function getDisplayField(): string
    {
        if ($this->fields !== null) {
            foreach ($this->fields as $field) {
                if ($field->type === 'string') {
                    return $field->name;
                }
            }
        }

        return 'name';
    }
}
