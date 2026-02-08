<?php

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
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasStandardScopes
{
    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeByUser(Builder $query, int|Model $user): Builder
    {
        $userId = $user instanceof Model ? $user->getKey() : $user;

        if ($this->isFillable('created_by')) {
            return $query->where('created_by', $userId);
        }

        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $columns = $this->searchableColumns();

        if (empty($columns)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($columns, $term): void {
            foreach ($columns as $column) {
                $q->orWhereRaw("LOWER({$column}) LIKE ?", ['%'.mb_strtolower($term).'%']);
            }
        });
    }

    /**
     * Columns searched by the search() scope.
     * Override in your model to customize.
     *
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'title'];
    }
}
