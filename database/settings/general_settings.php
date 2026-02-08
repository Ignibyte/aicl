<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', config('app.name', 'AICL'));
        $this->migrator->add('general.site_description', '');
        $this->migrator->add('general.timezone', config('app.timezone', 'UTC'));
        $this->migrator->add('general.date_format', 'Y-m-d');
        $this->migrator->add('general.items_per_page', 25);
        $this->migrator->add('general.maintenance_mode', false);
    }
};
