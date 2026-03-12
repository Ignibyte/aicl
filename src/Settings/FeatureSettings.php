<?php

namespace Aicl\Settings;

use Spatie\LaravelSettings\Settings;

class FeatureSettings extends Settings
{
    public bool $enable_registration;

    public bool $require_email_verification;

    public bool $enable_social_login;

    public bool $require_mfa;

    public bool $enable_saml;

    public bool $enable_api;

    public static function group(): string
    {
        return 'features';
    }
}
