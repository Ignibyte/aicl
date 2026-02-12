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
    | Entity Defaults
    |--------------------------------------------------------------------------
    |
    | Default traits and behaviors applied to AI-generated entities.
    | These control which base traits are included by default when
    | generating new entities via aicl:make-entity.
    |
    */

    'entity_defaults' => [
        'traits' => [
            'entity_events' => true,
            'audit_trail' => true,
            'standard_scopes' => true,
            'media_collections' => false,
            'searchable_fields' => false,
            'tagging' => false,
        ],
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
        'api' => true,
        'websockets' => env('AICL_WEBSOCKETS', false),
        'scout_driver' => env('AICL_SCOUT_DRIVER', false),
        'hub_search' => (bool) env('AICL_HUB_SEARCH', false),
        'hub_admin' => (bool) env('AICL_HUB_ADMIN', false),
        'rlm_search' => (bool) env('AICL_RLM_SEARCH', true),
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
        'elasticsearch' => [
            'host' => env('ELASTICSEARCH_HOST', 'elasticsearch'),
            'port' => (int) env('ELASTICSEARCH_PORT', 9200),
            'scheme' => env('ELASTICSEARCH_SCHEME', 'http'),
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
                'style-src' => ["'self'", "'unsafe-inline'"],
                'img-src' => ["'self'", 'data:', 'blob:'],
                'font-src' => ["'self'", 'data:'],
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
    | Redis Database Mapping
    |--------------------------------------------------------------------------
    |
    | Separate Redis databases for isolation between cache, sessions, and queues.
    |
    */

    'redis' => [
        'cache' => 0,
        'sessions' => 1,
        'queues' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | RLM Knowledge System
    |--------------------------------------------------------------------------
    |
    | Configuration for the RLM (Recursive Language Model) knowledge system.
    | Uses PostgreSQL as source of truth and Elasticsearch for search/discovery.
    |
    */

    'rlm' => [
        'embeddings' => [
            'driver' => env('AICL_EMBEDDING_DRIVER'),
            'dimension' => 1536,
            'openai' => [
                'api_key' => env('OPENAI_API_KEY'),
                'model' => env('AICL_EMBEDDING_MODEL', 'text-embedding-3-small'),
            ],
            'ollama' => [
                'host' => env('OLLAMA_HOST', 'http://localhost:11434'),
                'model' => env('AICL_OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
            ],
        ],

        'semantic' => [
            'enabled' => (bool) env('AICL_SEMANTIC_ENABLED', false),
            'api_key' => env('AICL_SEMANTIC_API_KEY', env('ANTHROPIC_API_KEY')),
            'model' => env('AICL_SEMANTIC_MODEL', 'claude-haiku-4-5-20251001'),
            'max_tokens' => 1024,
            'timeout' => 30,
            'cache_ttl' => 60 * 60 * 24 * 7,
            'use_cache' => true,
            'confidence_threshold' => 0.3,
            'mode' => env('AICL_SEMANTIC_MODE', 'advisory'),
        ],

        'hub' => [
            'enabled' => (bool) env('AICL_RLM_HUB_ENABLED', false),
            'url' => env('AICL_RLM_HUB_URL'),
            'token' => env('AICL_RLM_HUB_TOKEN'),
            'timeout' => 30,
            'sync_on_validate' => false,
        ],
    ],
];
