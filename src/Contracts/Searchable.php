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

    /**
     * Return permission metadata for the global search index.
     *
     * Used by SearchDocumentBuilder to store access control data in the ES document.
     * Models can override this to provide custom permission context.
     *
     * @return array{owner_id: string|int|null, team_ids: array<int, string|int>}
     */
    public function getSearchPermissionMeta(): array;
}
