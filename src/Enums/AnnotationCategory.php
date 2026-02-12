<?php

namespace Aicl\Enums;

enum AnnotationCategory: string
{
    case Model = 'model';
    case Migration = 'migration';
    case Factory = 'factory';
    case Policy = 'policy';
    case Observer = 'observer';
    case Filament = 'filament';
    case Api = 'api';
    case Test = 'test';
    case Notification = 'notification';
    case Pdf = 'pdf';

    public function label(): string
    {
        return match ($this) {
            self::Model => 'Model',
            self::Migration => 'Migration',
            self::Factory => 'Factory',
            self::Policy => 'Policy',
            self::Observer => 'Observer',
            self::Filament => 'Filament',
            self::Api => 'API',
            self::Test => 'Test',
            self::Notification => 'Notification',
            self::Pdf => 'PDF',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Model => 'primary',
            self::Migration => 'info',
            self::Factory => 'success',
            self::Policy => 'warning',
            self::Observer => 'danger',
            self::Filament => 'primary',
            self::Api => 'info',
            self::Test => 'success',
            self::Notification => 'warning',
            self::Pdf => 'gray',
        };
    }
}
