<?php

namespace Aicl\Filament\Resources\Users;

use Aicl\Filament\Resources\Users\Pages\CreateUser;
use Aicl\Filament\Resources\Users\Pages\EditUser;
use Aicl\Filament\Resources\Users\Pages\ListUsers;
use Aicl\Filament\Resources\Users\Schemas\UserForm;
use Aicl\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Filament resource for managing User models.
 *
 * Uses the UserForm schema for create/edit forms and UsersTable for the
 * index listing. Registered under the "People" navigation group.
 *
 * @see UserForm  Form schema
 * @see UsersTable  Table configuration
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
