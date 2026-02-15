<?php

namespace Aicl\Console\Support;

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

    public function isForeignKey(): bool
    {
        return $this->type === 'foreignId';
    }

    public function isEnum(): bool
    {
        return $this->type === 'enum';
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

        $cleaned = preg_replace('/_id$/', '', $this->name);

        return \Illuminate\Support\Str::camel($cleaned);
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

        return \Illuminate\Support\Str::studly(
            \Illuminate\Support\Str::singular($this->typeArgument)
        );
    }
}
