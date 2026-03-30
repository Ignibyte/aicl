<?php

declare(strict_types=1);

namespace Aicl\Filament\Resources\Users\Pages;

use Aicl\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

/**
 * List Users page for the admin panel.
 *
 * Eager-loads roles and breezySessions to prevent N+1 queries
 * and lazy-load violations under Model::shouldBeStrict().
 *
 * @codeCoverageIgnore Filament Livewire rendering
 */
class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Eager load roles relationship to prevent N+1 queries
     * on the roles.name table column.
     *
     * @param Builder<User> $query
     *
     * @return Builder<User>
     */
    protected function modifyQueryUsing(Builder $query): Builder
    {
        return $query->with(['roles', 'breezySessions', 'breezySession']);
    }
}
