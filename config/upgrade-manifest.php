<?php

/**
 * AICL Upgrade Manifest
 *
 * Declares what files should exist (and not exist) in a properly
 * configured AICL project at this package version. The aicl:upgrade
 * command reads this manifest to synchronize project-level files
 * that live outside the Composer-managed package directory.
 *
 * Strategies:
 *   - overwrite:       Replace entirely with the package stub version
 *   - ensure_absent:   Delete if present (framework-only files that should not exist in client projects)
 *   - ensure_present:  Copy if missing, skip if already exists (user may have customized)
 */

return [
    'version' => '1.0.3',

    'sections' => [
        'agents' => [
            'label' => 'Agent Prompts (.claude/commands/)',
            'entries' => [
                // Only Forge integration agents ship with the package.
                // All other agents are delivered dynamically via Forge MCP.
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/forge-connect.md',
                    'source' => 'stubs/commands/forge-connect.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/init_help.md',
                    'source' => 'stubs/commands/init_help.md',
                ],

                // Previously shipped agents — remove on upgrade (now via Forge MCP)
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/generate.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/pm.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/rlm.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/architect.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/solutions.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/designer.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/tester.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/docs.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/upgrade-project.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/project-setup.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/remove-entity.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/scan-all.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/scan-phpstan.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/scan-semgrep.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/scan-snyk.md', 'reason' => 'Now delivered via Forge MCP'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/test-dusk.md', 'reason' => 'Now delivered via Forge MCP'],

                // Framework-only agents (always absent from client projects)
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/replit.md', 'reason' => 'Framework-only'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/replit-design.md', 'reason' => 'Framework-only'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/release.md', 'reason' => 'Framework-only'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/upgrade-framework.md', 'reason' => 'Framework-only'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/seeker.md', 'reason' => 'Framework-only'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/scan-architecture.md', 'reason' => 'Framework-only'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/scan-duplication.md', 'reason' => 'Framework-only'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/scan-unused.md', 'reason' => 'Framework-only'],

                // Pipeline-variant source files (always absent from client projects)
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/generate-pipeline.md', 'reason' => 'Pipeline variant source'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/pm-pipeline.md', 'reason' => 'Pipeline variant source'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/rlm-pipeline.md', 'reason' => 'Pipeline variant source'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/architect-pipeline.md', 'reason' => 'Pipeline variant source'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/solutions-pipeline.md', 'reason' => 'Pipeline variant source'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/designer-pipeline.md', 'reason' => 'Pipeline variant source'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/tester-pipeline.md', 'reason' => 'Pipeline variant source'],
                ['strategy' => 'ensure_absent', 'target' => '.claude/commands/docs-pipeline.md', 'reason' => 'Pipeline variant source'],
            ],
        ],

        'rlm' => [
            'label' => 'RLM (removed — now in Forge database)',
            'entries' => [
                // RLM patterns, world model, and failures are now in the Forge database.
                // Remove the entire local directory on upgrade.
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/planning/rlm/',
                    'reason' => 'RLM data moved to Forge database — accessed via Forge MCP tools',
                ],
            ],
        ],

        'golden_example' => [
            'label' => 'Golden Example (removed — now served via Forge MCP)',
            'entries' => [
                // Golden examples are now served via Forge MCP `search-patterns` tool.
                // Remove the local directory on upgrade.
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/golden-example/',
                    'reason' => 'Golden examples moved to Forge MCP — use search-patterns tool instead',
                ],
            ],
        ],

        'pipeline' => [
            'label' => 'Pipeline Templates (removed — now via Forge MCP)',
            'entries' => [
                // Pipeline templates are now delivered via Forge MCP.
                // Remove local copies on upgrade.
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/planning/pipeline/',
                    'reason' => 'Pipeline templates moved to Forge MCP',
                ],
            ],
        ],

        'tests' => [
            'label' => 'Test Infrastructure',
            'entries' => [
                [
                    'strategy' => 'overwrite',
                    'target' => 'tests/TestCase.php',
                    'source' => 'stubs/tests/TestCase.php',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => 'tests/DuskTestCase.php',
                    'source' => 'stubs/tests/DuskTestCase.php',
                ],

                // Framework/scaffolder tests should NOT exist in client projects
                // Package tests live in packages/aicl/tests/ (dev repo only, never shipped)
                [
                    'strategy' => 'ensure_absent',
                    'target' => 'tests/Framework/',
                    'reason' => 'Framework scaffolder tests — dev repo only',
                ],
            ],
        ],

        'config' => [
            'label' => 'Project Configuration',
            'entries' => [
                [
                    'strategy' => 'ensure_present',
                    'target' => '.env.dusk.local',
                    'source' => 'stubs/env.dusk.local',
                ],
                // config/aicl-project.php — project-owned overlay, never overwritten.
                // Copied once if missing so new projects know it exists.
                [
                    'strategy' => 'ensure_present',
                    'target' => 'config/aicl-project.php',
                    'source' => 'config/aicl-project.php',
                ],
                // config/aicl.php is NOT managed — client customizes it. New keys
                // are handled by mergeConfigFrom() in the service provider (package
                // defaults act as fallbacks for keys not in the published config).
                // phpunit.xml is NOT managed — entity suites are project-specific
                // .ddev/ is NOT managed — too project-specific
            ],
        ],

        'claude' => [
            'label' => 'Claude Configuration',
            'entries' => [
                [
                    'strategy' => 'overwrite',
                    'target' => 'CLAUDE.md',
                    'source' => 'stubs/CLAUDE.md',
                ],
            ],
        ],

        'planning' => [
            'label' => 'Planning Structure (.claude/)',
            'entries' => [
                // Framework planning directory should NOT exist in client projects
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/planning/framework/',
                    'reason' => 'Framework planning — internal development only',
                ],
                // Architecture directory should NOT exist in client projects
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/architecture/',
                    'reason' => 'Architecture docs — internal development only',
                ],
            ],
        ],
    ],

    // ─── Future Strategy: ensure_contains ───────────────────────────────
    // An `ensure_contains` strategy (marker-bounded code blocks in user-
    // editable files like AppServiceProvider.php or routes/api.php) was
    // considered but deferred. Currently ALL framework registrations live
    // in AiclServiceProvider (inside the Composer package), so project
    // files don't contain any AICL-managed code blocks. If a future
    // version requires injecting framework code into user-editable files,
    // implement `handleEnsureContains()` in UpgradeCommand with marker
    // comments (e.g. `// === AICL START ===` / `// === AICL END ===`).
];
