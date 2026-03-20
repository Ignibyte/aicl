<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

use Illuminate\Support\Str;

/** Value object representing a parsed entity field definition for code generation. */
class FieldDefinition
{
    public function __construct(
        public string $name,
        public string $type,
        public ?string $typeArgument,
        public bool $nullable,
        public bool $unique,
        public ?string $default,
        public bool $indexed,
    ) {}

    /**
     * Create a FieldDefinition from a base schema column declaration.
     *
     * @param  array{name: string, type: string, modifiers?: array<string>, argument?: string}  $column
     */
    public static function fromBaseSchema(array $column): self
    {
        $modifiers = $column['modifiers'] ?? [];
        $nullable = in_array('nullable', $modifiers, true);
        $unique = in_array('unique', $modifiers, true);
        $indexed = in_array('index', $modifiers, true);
        $default = null;

        foreach ($modifiers as $modifier) {
            if (preg_match('/^default\((.+)\)$/', $modifier, $matches)) {
                $default = $matches[1];
            }
        }

        // Apply type-specific defaults (same as FieldParser)
        if (in_array($column['type'], ['text', 'date', 'datetime', 'json'], true) && ! $nullable) {
            $nullable = true;
        }

        if ($column['type'] === 'boolean' && $default === null) {
            $default = 'true';
        }

        return new self(
            name: $column['name'],
            type: $column['type'],
            typeArgument: $column['argument'] ?? null,
            nullable: $nullable,
            unique: $unique,
            default: $default,
            indexed: $indexed,
        );
    }

    /**
     * Alias for isForeignKey() — used by ViewGenerator and other generators.
     */
    public function isForeignId(): bool
    {
        return $this->type === 'foreignId';
    }

    public function isForeignKey(): bool
    {
        return $this->type === 'foreignId';
    }

    public function isEnum(): bool
    {
        return $this->type === 'enum';
    }

    /**
     * Get a human-readable label for this field.
     * Converts snake_case to Title Case, stripping _id suffix.
     * e.g., assigned_user_id → Assigned User, category_name → Category Name
     */
    public function label(): string
    {
        $name = $this->name;

        // Strip _id suffix for foreign keys to get a cleaner label
        if ($this->isForeignId()) {
            $name = preg_replace('/_id$/', '', $name) ?? $name;
        }

        return Str::of($name)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    /**
     * Get the relationship method name for a foreignId field.
     * Strips _id suffix and converts to camelCase.
     * e.g., assigned_to → assignedTo, category_id → category, owner_id → owner
     */
    public function relationshipMethodName(): ?string
    {
        if (! $this->isForeignKey()) {
            return null;
        }

        $cleaned = preg_replace('/_id$/', '', $this->name) ?? $this->name;

        return Str::camel($cleaned);
    }

    /**
     * Get the related model class name for a foreignId field.
     * Derives from the table name: users → User, categories → Category
     */
    public function relatedModelName(): ?string
    {
        if (! $this->isForeignKey() || $this->typeArgument === null) {
            return null;
        }

        return Str::studly(
            Str::singular($this->typeArgument)
        );
    }
}
