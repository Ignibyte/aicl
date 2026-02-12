<?php

namespace Aicl\Enums;

enum FailureCategory: string
{
    case Scaffolding = 'scaffolding';
    case Process = 'process';
    case Filament = 'filament';
    case Testing = 'testing';
    case Auth = 'auth';
    case Laravel = 'laravel';
    case Tailwind = 'tailwind';
    case Configuration = 'configuration';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Scaffolding => 'Scaffolding',
            self::Process => 'Process',
            self::Filament => 'Filament',
            self::Testing => 'Testing',
            self::Auth => 'Auth',
            self::Laravel => 'Laravel',
            self::Tailwind => 'Tailwind',
            self::Configuration => 'Configuration',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scaffolding => 'primary',
            self::Process => 'info',
            self::Filament => 'warning',
            self::Testing => 'success',
            self::Auth => 'danger',
            self::Laravel => 'primary',
            self::Tailwind => 'info',
            self::Configuration => 'gray',
            self::Other => 'gray',
        };
    }
}
