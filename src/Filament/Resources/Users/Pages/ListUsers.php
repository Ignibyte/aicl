<?php

namespace Aicl\Filament\Resources\Users\Pages;

use Aicl\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

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
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    protected function modifyQueryUsing(Builder $query): Builder
    {
        return $query->with('roles');
    }
}
