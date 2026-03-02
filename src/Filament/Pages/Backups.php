<?php

namespace Aicl\Filament\Pages;

use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;

class Backups extends BaseBackups
{
    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'System';
    }
}
