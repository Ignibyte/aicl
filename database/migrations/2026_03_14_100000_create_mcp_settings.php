<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mcp.is_enabled', false);
        $this->migrator->add('mcp.exposed_entities', ['*']);
        $this->migrator->add('mcp.custom_tools_enabled', true);
        $this->migrator->add('mcp.rate_limit_per_minute', 60);
        $this->migrator->add('mcp.max_sessions', 10);
        $this->migrator->add('mcp.server_description', null);
    }
};
