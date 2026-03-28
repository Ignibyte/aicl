<?php

declare(strict_types=1);

namespace Aicl\Filament\Pages;

use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;

/**
 * Backups.
 */
class Backups extends BaseBackups
{
    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'System';
    }
}
