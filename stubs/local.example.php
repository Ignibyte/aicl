<?php

/**
 * Local Configuration Overrides
 *
 * This is the single source of truth for instance-specific configuration.
 * Copy this file to config/local.php and customize for your environment.
 *
 * config/local.php is GITIGNORED — never commit it.
 *
 * Uses dot-notation keys to override any config value at any depth.
 * Precedence: package defaults < project config < local.php (final authority).
 *
 * DDEV defaults are baked into the config files — this file only needs
 * secrets and environment-specific overrides.
 */

return [

    // -------------------------------------------------------------------------
    // Core Application (required — generate with: php artisan key:generate --show)
    // -------------------------------------------------------------------------

    // 'app.key' => 'base64:your-generated-key-here',

    // -------------------------------------------------------------------------
    // Environment Overrides (optional — DDEV defaults work out of the box)
    // -------------------------------------------------------------------------

    // 'app.name'     => 'My Application',
    // 'app.env'      => 'production',
    // 'app.debug'    => false,
    // 'app.url'      => 'https://myapp.example.com',
    // 'app.timezone' => 'America/Chicago',

    // -------------------------------------------------------------------------
    // Database (override for non-DDEV environments)
    // -------------------------------------------------------------------------

    // 'database.connections.pgsql.host'     => '127.0.0.1',
    // 'database.connections.pgsql.port'     => 5432,
    // 'database.connections.pgsql.database' => 'myapp',
    // 'database.connections.pgsql.username' => 'myapp',
    // 'database.connections.pgsql.password' => 'secret',

    // -------------------------------------------------------------------------
    // Redis (override for non-DDEV environments)
    // -------------------------------------------------------------------------

    // 'database.redis.default.host'     => '127.0.0.1',
    // 'database.redis.default.password' => 'secret',

    // -------------------------------------------------------------------------
    // Mail (override for non-DDEV environments)
    // -------------------------------------------------------------------------

    // 'mail.default'               => 'ses',
    // 'mail.from.address'          => 'noreply@myapp.com',
    // 'mail.from.name'             => 'My Application',

    // -------------------------------------------------------------------------
    // Broadcasting / Reverb (override keys for production)
    // -------------------------------------------------------------------------

    // 'broadcasting.connections.reverb.key'    => 'your-production-key',
    // 'broadcasting.connections.reverb.secret' => 'your-production-secret',
    // 'broadcasting.connections.reverb.app_id' => 'your-production-app-id',

    // -------------------------------------------------------------------------
    // AI Provider Keys (secrets — required for AI features)
    // -------------------------------------------------------------------------

    // 'aicl.ai.openai.api_key'    => 'sk-...',
    // 'aicl.ai.anthropic.api_key' => 'sk-ant-...',

    // -------------------------------------------------------------------------
    // CMS AI Keys (secrets — required for CMS AI content generation)
    // -------------------------------------------------------------------------

    // 'aicl-cms.ai.claude.api_key' => 'sk-ant-...',
    // 'aicl-cms.ai.openai.api_key' => 'sk-...',

    // -------------------------------------------------------------------------
    // AICL Feature Flags (override package/project defaults)
    // -------------------------------------------------------------------------

    // 'aicl.features.allow_registration'        => true,
    // 'aicl.features.require_email_verification' => false,
    // 'aicl.features.require_mfa'               => true,
    // 'aicl.features.social_login'              => true,
    // 'aicl.features.saml'                      => true,
    // 'aicl.features.mcp'                       => true,
    // 'aicl.features.websockets'                => false,

    // -------------------------------------------------------------------------
    // SAML SSO (secrets — required when aicl.features.saml is true)
    // -------------------------------------------------------------------------

    // 'services.saml2.metadata'       => 'https://idp.example.com/metadata',
    // 'services.saml2.entityid'       => 'https://idp.example.com',
    // 'services.saml2.certificate'    => '-----BEGIN CERTIFICATE-----...',
    // 'services.saml2.sp_certificate' => '-----BEGIN CERTIFICATE-----...',
    // 'services.saml2.sp_private_key' => '-----BEGIN PRIVATE KEY-----...',

    // -------------------------------------------------------------------------
    // Search / Elasticsearch
    // -------------------------------------------------------------------------

    // 'aicl.search.enabled'                => true,
    // 'aicl.search.elasticsearch.host'     => 'elasticsearch',
    // 'aicl.search.elasticsearch.api_key'  => null,

    // -------------------------------------------------------------------------
    // Theme & Branding
    // -------------------------------------------------------------------------

    // 'aicl.theme.brand_name' => 'My Brand',
    // 'aicl.theme.logo'       => 'images/logo.png',
    // 'aicl.theme.favicon'    => 'images/favicon.png',

];
