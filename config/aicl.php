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
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Toggle package features on/off. Disabled features are not loaded.
    |
    */

    'features' => [
        'mfa' => true,
        'social_login' => env('AICL_SOCIAL_LOGIN', false),
        'saml' => env('AICL_SAML', false),
        'allow_registration' => env('AICL_ALLOW_REGISTRATION', false),
        'api' => true,
        'websockets' => env('AICL_WEBSOCKETS', true),
        'scout_driver' => env('AICL_SCOUT_DRIVER', false),
        'horizon' => env('AICL_HORIZON', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Engine Configuration
    |--------------------------------------------------------------------------
    |
    | When AICL_SCOUT_DRIVER is set to 'elasticsearch', Scout will use
    | Elasticsearch instead of the default database driver. The engine
    | package must be installed separately (see suggest in composer.json).
    |
    | Supported: false (database driver), 'elasticsearch'
    |
    */

    'search' => [
        'enabled' => env('AICL_SEARCH_ENABLED', false),
        'index' => env('AICL_SEARCH_INDEX', 'aicl_global_search'),
        'min_query_length' => 2,

        'elasticsearch' => [
            'host' => env('ELASTICSEARCH_HOST', 'elasticsearch'),
            'port' => (int) env('ELASTICSEARCH_PORT', 9200),
            'scheme' => env('ELASTICSEARCH_SCHEME', 'http'),
            'api_key' => env('ELASTICSEARCH_API_KEY'),
            'username' => env('ELASTICSEARCH_USERNAME'),
            'password' => env('ELASTICSEARCH_PASSWORD'),
        ],

        // Entity types registered for global search indexing.
        // Key = model FQCN, value = config array with fields, label, visibility, etc.
        'entities' => [],

        'analytics' => [
            'enabled' => env('AICL_SEARCH_ANALYTICS', true),
            'retention_days' => (int) env('AICL_SEARCH_RETENTION_DAYS', 90),
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
        'idp_name' => env('SAML_IDP_NAME', 'SSO'),
        'auto_create_users' => env('SAML_AUTO_CREATE_USERS', true),
        'default_role' => env('SAML_DEFAULT_ROLE', 'viewer'),
        'role_sync_mode' => env('SAML_ROLE_SYNC_MODE', 'sync'), // 'sync' or 'additive'
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
            'enabled' => env('AICL_SECURITY_HEADERS', true),
            'hsts' => true,
            'hsts_max_age' => 31536000,
        ],

        'csp' => [
            'enabled' => env('AICL_CSP_ENABLED', true),
            'report_only' => env('AICL_CSP_REPORT_ONLY', true),

            // Filament/admin panel CSP — permissive for Livewire/Alpine
            'filament_directives' => [
                'default-src' => ["'self'"],
                'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
                'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
                'img-src' => ["'self'", 'data:', 'blob:', env('APP_URL', '')],
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

        'api_logging' => env('AICL_API_LOGGING', true),

        'trusted_proxies' => env('TRUSTED_PROXIES', '*'),
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
        'brand_name' => env('AICL_BRAND_NAME', 'IGNIBYTE'),
        'logo' => env('AICL_LOGO_PATH', 'vendor/aicl/images/logo.png'),
        'favicon' => env('AICL_FAVICON_PATH', 'vendor/aicl/images/favicon.png'),
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
        'history_retention_days' => (int) env('AICL_SCHEDULER_RETENTION_DAYS', 30),
        'output_max_bytes' => (int) env('AICL_SCHEDULER_OUTPUT_MAX_BYTES', 10240),
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
    | AI Assistant
    |--------------------------------------------------------------------------
    |
    | Configuration for the AI assistant chat feature. Supports OpenAI,
    | Anthropic, and Ollama providers via NeuronAI.
    |
    */

    'ai' => [
        'provider' => env('AICL_AI_PROVIDER', 'openai'),

        'tools_enabled' => env('AICL_AI_TOOLS_ENABLED', true),

        'tools' => [
            // Additional tools registered by client projects:
            // App\AI\Tools\MyCustomTool::class,
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('AICL_AI_MODEL', 'gpt-4o-mini'),
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('AICL_AI_MODEL', 'claude-haiku-4-5-20251001'),
        ],

        'ollama' => [
            'host' => env('OLLAMA_HOST', 'http://localhost:11434'),
            'model' => env('AICL_AI_MODEL', 'llama3.2'),
        ],

        'system_prompt' => 'You are a helpful assistant for this application. Answer questions clearly and concisely.',
        'max_prompt_length' => 2000,

        'rate_limit' => [
            'max_attempts' => 10,
            'decay_minutes' => 1,
        ],

        'streaming' => [
            'queue' => env('AICL_AI_QUEUE', 'default'),
            'timeout' => (int) env('AICL_AI_TIMEOUT', 120),
            'max_concurrent_per_user' => 2,
            'reverb' => [
                'host' => env('VITE_REVERB_HOST', env('REVERB_HOST', 'localhost')),
                'port' => (int) env('VITE_REVERB_PORT', env('REVERB_PORT', 8080)),
                'scheme' => env('VITE_REVERB_SCHEME', env('REVERB_SCHEME', 'http')),
            ],
        ],
    ],

];
