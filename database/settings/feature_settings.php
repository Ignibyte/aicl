<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('features.enable_registration', true);
        $this->migrator->add('features.require_email_verification', true);
        $this->migrator->add('features.enable_social_login', false);
        $this->migrator->add('features.require_mfa', false);
        $this->migrator->add('features.enable_api', true);
    }
};
