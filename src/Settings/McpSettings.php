<?php

namespace Aicl\Settings;

use Spatie\LaravelSettings\Settings;

class McpSettings extends Settings
{
    public bool $is_enabled;

    public array $exposed_entities;

    public bool $custom_tools_enabled;

    public int $rate_limit_per_minute;

    public int $max_sessions;

    public ?string $server_description;

    public static function group(): string
    {
        return 'mcp';
    }
}
