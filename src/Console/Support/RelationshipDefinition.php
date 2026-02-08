<?php

namespace Aicl\Console\Support;

class RelationshipDefinition
{
    public function __construct(
        public string $name,
        public string $type,
        public string $relatedModel,
        public ?string $foreignKey,
    ) {}

    /**
     * Get the Eloquent relationship return type class name.
     */
    public function eloquentType(): string
    {
        return match ($this->type) {
            'hasMany' => 'HasMany',
            'hasOne' => 'HasOne',
            'belongsToMany' => 'BelongsToMany',
            'morphMany' => 'MorphMany',
            default => 'HasMany',
        };
    }

    /**
     * Get the full Eloquent relationship import path.
     */
    public function eloquentImport(): string
    {
        return "Illuminate\\Database\\Eloquent\\Relations\\{$this->eloquentType()}";
    }
}
