<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('features.enable_registration', true);
        $this->migrator->add('features.enable_social_login', false);
        $this->migrator->add('features.enable_mfa', true);
        $this->migrator->add('features.enable_api', true);
    }
};
