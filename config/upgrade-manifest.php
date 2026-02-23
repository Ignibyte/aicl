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
    'version' => '3.0.0',

    'sections' => [
        'agents' => [
            'label' => 'Agent Prompts (.claude/commands/)',
            'entries' => [
                // Pipeline-variant agents that ship to client projects
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/generate.md',
                    'source' => 'stubs/commands/generate.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/pm.md',
                    'source' => 'stubs/commands/pm.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/rlm.md',
                    'source' => 'stubs/commands/rlm.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/architect.md',
                    'source' => 'stubs/commands/architect.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/solutions.md',
                    'source' => 'stubs/commands/solutions.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/designer.md',
                    'source' => 'stubs/commands/designer.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/tester.md',
                    'source' => 'stubs/commands/tester.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/docs.md',
                    'source' => 'stubs/commands/docs.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/upgrade-project.md',
                    'source' => 'stubs/commands/upgrade-project.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/project-setup.md',
                    'source' => 'stubs/commands/project-setup.md',
                ],

                // Forge integration agents
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

                // Utility agents that ship to client projects
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/remove-entity.md',
                    'source' => 'stubs/commands/remove-entity.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/scan-all.md',
                    'source' => 'stubs/commands/scan-all.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/scan-phpstan.md',
                    'source' => 'stubs/commands/scan-phpstan.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/scan-semgrep.md',
                    'source' => 'stubs/commands/scan-semgrep.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/scan-snyk.md',
                    'source' => 'stubs/commands/scan-snyk.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/commands/test-dusk.md',
                    'source' => 'stubs/commands/test-dusk.md',
                ],

                // Framework-only agents that should NOT exist in client projects
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/replit.md',
                    'reason' => 'Framework-only agent, not for client projects',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/replit-design.md',
                    'reason' => 'Framework-only agent, not for client projects',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/release.md',
                    'reason' => 'Framework-only agent, not for client projects',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/upgrade-framework.md',
                    'reason' => 'Framework-only agent, not for client projects',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/seeker.md',
                    'reason' => 'Framework-only agent, not for client projects',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/scan-architecture.md',
                    'reason' => 'Framework-only agent, not for client projects',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/scan-duplication.md',
                    'reason' => 'Framework-only agent, not for client projects',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/scan-unused.md',
                    'reason' => 'Framework-only agent, not for client projects',
                ],

                // Pipeline-variant source files (should not exist in client — they become the non-pipeline names)
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/generate-pipeline.md',
                    'reason' => 'Pipeline variant source — client uses generate.md',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/pm-pipeline.md',
                    'reason' => 'Pipeline variant source — client uses pm.md',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/rlm-pipeline.md',
                    'reason' => 'Pipeline variant source — client uses rlm.md',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/architect-pipeline.md',
                    'reason' => 'Pipeline variant source — client uses architect.md',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/solutions-pipeline.md',
                    'reason' => 'Pipeline variant source — client uses solutions.md',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/designer-pipeline.md',
                    'reason' => 'Pipeline variant source — client uses designer.md',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/tester-pipeline.md',
                    'reason' => 'Pipeline variant source — client uses tester.md',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/commands/docs-pipeline.md',
                    'reason' => 'Pipeline variant source — client uses docs.md',
                ],
            ],
        ],

        'rlm' => [
            'label' => 'RLM Patterns (.claude/planning/rlm/)',
            'entries' => [
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/planning/rlm/world-model.md',
                    'source' => 'stubs/rlm/world-model.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/planning/rlm/base-failures.md',
                    'source' => 'stubs/rlm/base-failures.md',
                ],
                // failures.md and scores.md are project-owned — NOT managed
                // framework-scores.md and framework-failures.md are framework-only
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/planning/rlm/framework-scores.md',
                    'reason' => 'Framework-only RLM file',
                ],
                [
                    'strategy' => 'ensure_absent',
                    'target' => '.claude/planning/rlm/framework-failures.md',
                    'reason' => 'Framework-only RLM file',
                ],
            ],
        ],

        'golden_example' => [
            'label' => 'Golden Example (.claude/golden-example/)',
            'entries' => [
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/README.md', 'source' => 'stubs/golden-example/README.md'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/model.php', 'source' => 'stubs/golden-example/model.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/migration.php', 'source' => 'stubs/golden-example/migration.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/factory.php', 'source' => 'stubs/golden-example/factory.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/seeder.php', 'source' => 'stubs/golden-example/seeder.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/policy.php', 'source' => 'stubs/golden-example/policy.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/observer.php', 'source' => 'stubs/golden-example/observer.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/enum.php', 'source' => 'stubs/golden-example/enum.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/state.php', 'source' => 'stubs/golden-example/state.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/filament-resource.php', 'source' => 'stubs/golden-example/filament-resource.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/filament-form.php', 'source' => 'stubs/golden-example/filament-form.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/filament-table.php', 'source' => 'stubs/golden-example/filament-table.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/filament-pages/list.php', 'source' => 'stubs/golden-example/filament-pages/list.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/filament-pages/create.php', 'source' => 'stubs/golden-example/filament-pages/create.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/filament-pages/edit.php', 'source' => 'stubs/golden-example/filament-pages/edit.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/filament-pages/view.php', 'source' => 'stubs/golden-example/filament-pages/view.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/exporter.php', 'source' => 'stubs/golden-example/exporter.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/api-controller.php', 'source' => 'stubs/golden-example/api-controller.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/api-resource.php', 'source' => 'stubs/golden-example/api-resource.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/api-requests.php', 'source' => 'stubs/golden-example/api-requests.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/test.php', 'source' => 'stubs/golden-example/test.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/notifications/assigned.php', 'source' => 'stubs/golden-example/notifications/assigned.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/notifications/status-changed.php', 'source' => 'stubs/golden-example/notifications/status-changed.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/widgets/chart.php', 'source' => 'stubs/golden-example/widgets/chart.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/widgets/stats-overview.php', 'source' => 'stubs/golden-example/widgets/stats-overview.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/widgets/table.php', 'source' => 'stubs/golden-example/widgets/table.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/pdf/single-report.blade.php', 'source' => 'stubs/golden-example/pdf/single-report.blade.php'],
                ['strategy' => 'overwrite', 'target' => '.claude/golden-example/pdf/list-report.blade.php', 'source' => 'stubs/golden-example/pdf/list-report.blade.php'],
            ],
        ],

        'pipeline' => [
            'label' => 'Pipeline Templates (.claude/planning/pipeline/)',
            'entries' => [
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/planning/pipeline/pipeline-template.md',
                    'source' => 'stubs/pipeline/pipeline-template.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/planning/pipeline/project-plan-template.md',
                    'source' => 'stubs/pipeline/project-plan-template.md',
                ],
                [
                    'strategy' => 'overwrite',
                    'target' => '.claude/planning/pipeline/work-pipeline-template.md',
                    'source' => 'stubs/pipeline/work-pipeline-template.md',
                ],
                // active/ and tmp/ are project-owned — NOT managed
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
