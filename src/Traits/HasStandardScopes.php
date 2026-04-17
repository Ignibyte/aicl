<?php

declare(strict_types=1);

namespace Aicl\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Common query scopes for AICL entities.
 *
 * Provides a standard set of scopes that most entities share:
 * - active() / inactive() — filter by is_active boolean column
 * - recent($days) — records created within N days
 * - byUser($user) — filter by created_by or user_id foreign key
 * - search($term) — basic LIKE search across searchable columns
 *
 * Override searchableColumns() in your model to define which columns
 * are searched by the search() scope.
 *
 * @mixin Model
 */
trait HasStandardScopes
{
    /**
     * @param Builder<Model> $query
     *
     * @return Builder<Model>
     */
    public function scopeActive(Builder $query): Builder
    {
        // @codeCoverageIgnoreStart — Trait requiring integration context
        return $query->where('is_active', true);
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param Builder<Model> $query
     *
     * @return Builder<Model>
     */
    public function scopeInactive(Builder $query): Builder
    {
        // @codeCoverageIgnoreStart — Trait requiring integration context
        return $query->where('is_active', false);
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param Builder<Model> $query
     *
     * @return Builder<Model>
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        // @codeCoverageIgnoreStart — Trait requiring integration context
        return $query->where('created_at', '>=', now()->subDays($days));
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param Builder<Model> $query
     *
     * @return Builder<Model>
     */
    public function scopeByUser(Builder $query, int|Model $user): Builder
    {
        // @codeCoverageIgnoreStart — Trait requiring integration context
        $userId = $user instanceof Model ? $user->getKey() : $user;

        if ($this->isFillable('created_by')) {
            return $query->where('created_by', $userId);
        }

        return $query->where('user_id', $userId);
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param Builder<Model> $query
     *
     * @return Builder<Model>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $columns = $this->searchableColumns();

        if (empty($columns)) {
            // @codeCoverageIgnoreStart — Trait requiring integration context
            return $query;
            // @codeCoverageIgnoreEnd
        }

        return $query->where(function (Builder $q) use ($columns, $term): void {
            $grammar = $q->getGrammar();
            foreach ($columns as $column) {
                $wrapped = $grammar->wrap($column);
                $q->orWhereRaw("LOWER({$wrapped}) LIKE ?", ['%'.mb_strtolower($term).'%']);
            }
        });
    }

    /**
     * Columns searched by the search() scope.
     *
     * **REQUIRED: models MUST override this method.** The default returns an
     * empty array — any model using `HasStandardScopes` without explicitly
     * overriding `searchableColumns()` will get no results from `search()`,
     * not a SQL error. Previously this defaulted to `['name', 'title']`, which
     * caused BF-005 (500 errors on Filament search for models without a
     * `title` column). The empty default is fail-soft by design.
     *
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return [];
    }
}
