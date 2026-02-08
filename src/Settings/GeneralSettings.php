<?php

namespace Aicl\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public ?string $site_description;

    public string $timezone;

    public string $date_format;

    public int $items_per_page;

    public bool $maintenance_mode;

    public static function group(): string
    {
        return 'general';
    }
}
