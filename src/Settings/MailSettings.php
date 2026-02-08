<?php

namespace Aicl\Settings;

use Spatie\LaravelSettings\Settings;

class MailSettings extends Settings
{
    public string $from_address;

    public string $from_name;

    public ?string $reply_to;

    public static function group(): string
    {
        return 'mail';
    }
}
