<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mail.from_address', config('mail.from.address', 'noreply@example.com'));
        $this->migrator->add('mail.from_name', config('mail.from.name', config('app.name', 'AICL')));
        $this->migrator->add('mail.reply_to', null);
    }
};
