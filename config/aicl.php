<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Resource Class Mapping
    |--------------------------------------------------------------------------
    |
    | Map resource keys to Filament Resource class names. Client projects can
    | override these to swap in extended Resource classes without modifying
    | package code.
    |
    */

    'resources' => [
        // 'user' => \App\Filament\Resources\Users\UserResource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Observer Class Mapping
    |--------------------------------------------------------------------------
    |
    | Map model class names to their Observer classes. Client projects can
    | register additional observers or override package observers here.
    |
    */

    'observers' => [
        // \App\Models\User::class => \App\Observers\UserObserver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Site
    |--------------------------------------------------------------------------
    |
    | Site-level metadata. The site name and timezone are read from Laravel's
    | own app.name and app.timezone config keys — no duplication here.
    |
    */

    'site' => [
        'description' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Display
    |--------------------------------------------------------------------------
    |
    | Default display preferences for the admin panel.
    |
    */

    'display' => [
        'date_format' => 'Y-m-d',
        'items_per_page' => 25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail
    |--------------------------------------------------------------------------
    |
    | AICL-specific mail settings. The from address and name are read from
    | Laravel's mail.from config — no duplication here.
    |
    */

    'mail' => [
        'reply_to' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Toggle package features on/off. Disabled features are not loaded.
    | Override in your project's config/aicl.php or config/local.php.
    |
    */

    'features' => [
        'mfa' => true,
        'require_mfa' => false,
        'require_email_verification' => true,
        'social_login' => false,
        'saml' => false,
        'allow_registration' => false,
        'api' => true,
        'websockets' => true,
        'scout_driver' => false,
        'horizon' => true,
        'mcp' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Engine Configuration
    |--------------------------------------------------------------------------
    |
    | When scout_driver is 'elasticsearch', Scout uses Elasticsearch instead
    | of the default database driver. The engine package must be installed
    | separately (see suggest in composer.json).
    |
    | Supported: false (database driver), 'elasticsearch'
    |
    */

    'search' => [
        'enabled' => false,
        'index' => 'aicl_global_search',
        'min_query_length' => 2,

        'elasticsearch' => [
            'host' => 'elasticsearch',
            'port' => 9200,
            'scheme' => 'http',
            'api_key' => null,
            'username' => null,
            'password' => null,
        ],

        // Entity types registered for global search indexing.
        // Key = model FQCN, value = config array with fields, label, visibility, etc.
        'entities' => [],

        'analytics' => [
            'enabled' => true,
            'retention_days' => 90,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Login Providers
    |--------------------------------------------------------------------------
    |
    | List of enabled social login providers. Each provider must also be
    | configured in config/services.php with client_id, client_secret,
    | and redirect values.
    |
    */

    'social_providers' => ['google', 'github'],

    /*
    |--------------------------------------------------------------------------
    | SAML SSO Configuration
    |--------------------------------------------------------------------------
    |
    | Configure SAML 2.0 SSO attribute mapping, role mapping, and behavior.
    | Client projects override these defaults in their own config/aicl.php.
    |
    | The mapper supports three layers:
    | 1. Default attribute map (built into the package)
    | 2. Config-based overrides (this section)
    | 3. Custom mapper class (for complex logic)
    |
    */

    'saml' => [
        'idp_name' => 'SSO',
        'auto_create_users' => true,
        'default_role' => 'viewer',
        'role_sync_mode' => 'sync', // 'sync' or 'additive'
        'mapper_class' => null, // Override with App\Auth\CustomSamlMapper::class

        // Attribute mapping overrides — client sets in their config/aicl.php
        'attribute_map' => [],

        // Role mapping — client configures in their config/aicl.php
        'role_map' => [
            'source_attribute' => 'groups',
            'map' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | OWASP-aligned security configuration for headers, CSP, rate limiting,
    | proxy trust, and API request logging.
    |
    */

    'security' => [
        'headers' => [
            'enabled' => true,
            'hsts' => true,
            'hsts_max_age' => 31536000,
        ],

        'csp' => [
            'enabled' => true,
            'report_only' => true,

            // Filament/admin panel CSP — permissive for Livewire/Alpine
            'filament_directives' => [
                'default-src' => ["'self'"],
                'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
                'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
                'img-src' => ["'self'", 'data:', 'blob:'],
                'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
                'connect-src' => ["'self'", 'ws:', 'wss:'],
                'frame-ancestors' => ["'none'"],
            ],

            // API CSP — strict
            'api_directives' => [
                'default-src' => ["'none'"],
                'frame-ancestors' => ["'none'"],
            ],
        ],

        'api_logging' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme & Branding
    |--------------------------------------------------------------------------
    |
    | Default Ignibyte brand identity. Client projects override these values
    | in their own config/aicl.php to rebrand without modifying the package.
    |
    */

    'theme' => [
        'brand_name' => 'IGNIBYTE',
        'logo' => 'vendor/aicl/images/logo.png',
        'favicon' => 'vendor/aicl/images/favicon.png',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Configuration for the notification channel driver system.
    | External channel drivers (Slack, PagerDuty, etc.) use these settings
    | for retry behavior, queue routing, and extension points.
    |
    */

    'notifications' => [
        'default_channels' => ['database', 'mail', 'broadcast'],

        // Optional custom resolver classes (set to FQCN string or null)
        'channel_resolver' => null,
        'recipient_resolver' => null,

        'retry' => [
            'max_attempts' => 5,
            'base_delay' => 1, // seconds
        ],

        'queue' => 'notifications',

        // Template rendering (3.2)
        'templates' => [
            // HTML escaping enabled by default (prevents XSS in email output)
            'escape_html' => true,

            // Maximum template length (prevents abuse in admin-edited templates)
            'max_length' => 2000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Browser
    |--------------------------------------------------------------------------
    |
    | Directories to expose in the Document Browser Filament page.
    | Each entry has a label (shown in the sidebar) and a path (relative
    | to base_path()). Only .md files are listed.
    |
    */

    'docs' => [
        'paths' => [
            ['label' => 'Architecture', 'path' => '.claude/architecture'],
            ['label' => 'Project Docs', 'path' => 'docs/architecture'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler
    |--------------------------------------------------------------------------
    |
    | Configuration for the scheduled task monitoring system.
    |
    */

    'scheduler' => [
        'history_retention_days' => 30,
        'output_max_bytes' => 10240,
        'health_degraded_minutes' => 5,
        'health_down_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Checks
    |--------------------------------------------------------------------------
    |
    | Configuration for the Live Ops Panel health check system.
    |
    */

    'health' => [
        'queues' => ['default', 'notifications', 'high', 'low'],
        'failed_jobs_threshold' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Server
    |--------------------------------------------------------------------------
    |
    | Configuration for the Model Context Protocol server. When enabled,
    | external AI agents can discover and interact with your application's
    | entities via the MCP standard protocol.
    |
    */

    'mcp' => [
        'path' => '/mcp',
        'middleware' => ['api', 'auth:api', 'throttle:api'],
        'exposed_entities' => ['*'],
        'custom_tools_enabled' => true,
        'rate_limit_per_minute' => 60,
        'max_sessions' => 10,
        'server_info' => [
            'name' => null,
            'version' => '1.0.0',
            'description' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Assistant
    |--------------------------------------------------------------------------
    |
    | Configuration for the AI assistant chat feature. Supports OpenAI,
    | Anthropic, and Ollama providers via NeuronAI. API keys must be
    | set in config/local.php.
    |
    */

    'ai' => [
        'provider' => 'openai',

        'tools_enabled' => true,

        'tools' => [
            // Additional tools registered by client projects:
            // App\AI\Tools\MyCustomTool::class,
        ],

        'openai' => [
            'api_key' => null, // Set in config/local.php
            'model' => 'gpt-4o-mini',
        ],

        'anthropic' => [
            'api_key' => null, // Set in config/local.php
            'model' => 'claude-haiku-4-5-20251001',
        ],

        'ollama' => [
            'host' => 'http://localhost:11434',
            'model' => 'llama3.2',
        ],

        'system_prompt' => 'You are a helpful assistant for this application. Answer questions clearly and concisely.',
        'max_prompt_length' => 2000,

        'rate_limit' => [
            'max_attempts' => 10,
            'decay_minutes' => 1,
        ],

        'streaming' => [
            'queue' => 'default',
            'timeout' => 120,
            'max_concurrent_per_user' => 2,
            'reverb' => [
                'host' => 'localhost',
                'port' => 8080,
                'scheme' => 'http',
            ],
        ],

        'assistant' => [
            'enabled' => false,
            'keyboard_shortcut' => 'cmd+j',
            'default_agent' => null,
            'allowed_roles' => ['super_admin', 'admin'],
            'compaction_threshold' => 50,
            'compaction_delete_old_messages' => false,
            'token_budget_daily' => null,
            'context_injection' => true,
        ],
    ],

];
