<?php

namespace Aicl\Contracts;

/**
 * Model is indexed for full-text search.
 * Implemented by the HasSearchableFields trait.
 *
 * @return array<string, mixed>
 */
interface Searchable
{
    public function toSearchableArray(): array;
}
