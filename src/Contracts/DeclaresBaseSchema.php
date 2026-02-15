<?php

namespace Aicl\Contracts;

interface DeclaresBaseSchema
{
    /**
     * Declare the schema this base class provides.
     *
     * The scaffolder uses this to deduplicate fields, traits, and contracts
     * when generating child entities with --base=.
     *
     * @return array{
     *     columns: array<int, array{name: string, type: string, modifiers?: array<string>, argument?: string}>,
     *     traits: array<int, string>,
     *     contracts: array<int, string>,
     *     fillable: array<int, string>,
     *     casts: array<string, string>,
     *     relationships: array<int, array{name: string, type: string, model: string, foreignKey?: string}>,
     * }
     */
    public static function baseSchema(): array;
}
