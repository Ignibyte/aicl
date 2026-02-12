-- AICL Base Failures — Seed Data
-- Generated from .claude/planning/rlm/base-failures.md
-- This file is re-run on install and upgrade. Uses INSERT OR REPLACE to be idempotent.

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-001', 'base', 'scaffolding', 'searchableColumns() defaults include non-existent columns',
 'HasStandardScopes::searchableColumns() defaults to [''name'', ''title'']. Models without a title column get QueryException on search scope.',
 'When using HasStandardScopes, ALWAYS override searchableColumns() to list only columns that exist on the entity''s table.',
 'critical', 1, 'active', '1.0.5');

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-003', 'base', 'scaffolding', 'List page missing CreateAction and table missing standard actions',
 'List page missing CreateAction in header; Table missing ExportAction in headerActions, ViewAction/EditAction in recordActions, and ExportBulkAction/DeleteBulkAction in toolbarActions.',
 'Scaffolding MUST include: (1) CreateAction on List page, (2) ExportAction in table headerActions, (3) ViewAction + EditAction in recordActions, (4) ExportBulkAction + DeleteBulkAction in toolbarActions BulkActionGroup.',
 'critical', 1, 'active', '1.0.5');

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-002', 'base', 'process', 'Agent wired up entity without running validation first',
 'Agent wired up entity (policy binding, observer, API routes, Filament resource discovery) WITHOUT running validation or tests first. Bypassed the validate-then-register pipeline order.',
 'Entity registration (policy binding, observer binding, API routes, Filament resource discovery) MUST happen in a separate phase AFTER validation passes.',
 'critical', 0, 'active', '1.0.5');

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-004', 'base', 'process', 'Context continuation caused pipeline abandonment',
 'After context continuation (token limit truncation), agent abandoned structured pipeline — skipped phase gates, did not update pipeline documents, did not follow phase-gate discipline.',
 'After ANY context continuation, agents MUST re-read all active pipeline documents before resuming work. Pipeline document is the source of truth, not conversational memory.',
 'critical', 0, 'active', '1.0.5');

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-005', 'base', 'filament', 'searchableColumns() returns wrong defaults for Filament search',
 'HasStandardScopes::searchableColumns() returns [''name'', ''title''] by default. Models without title column get 500 errors on search scope.',
 'Always override searchableColumns() to list only columns that exist on the entity''s table.',
 'high', 1, 'active', '1.0.5');

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-006', 'base', 'filament', 'Filament v4 form field HTML id uses form. prefix not data.',
 'Filament v4 form field HTML id uses form. prefix (e.g., id="form.email"), NOT data. wire:model uses data.email but the HTML id is form.email.',
 'In Dusk tests, use form.{field} for #id selectors and data.{field} for wire:model selectors.',
 'medium', 0, 'active', '1.0.5');

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-007', 'base', 'auth', 'Spatie Permission API guard requires dual-guard seeding',
 'Spatie Permission + API guard: actingAs($user, ''api'') changes the default guard to api. Permissions seeded only on web guard won''t be found.',
 'Seed permissions AND roles on BOTH web and api guards. Assign roles on both guards for API tests.',
 'high', 0, 'active', '1.0.5');

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-008', 'base', 'events', 'EntityDeleted event must not use SerializesModels',
 'Entity events with ShouldBroadcast: EntityDeleted must NOT use SerializesModels trait because the model may already be deleted when the event is processed.',
 'Never use SerializesModels in delete event classes.',
 'high', 0, 'active', '1.0.5');

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-009', 'base', 'testing', 'Livewire lifecycle hooks cannot be called directly',
 'Livewire lifecycle hooks (updatedQuery, updatedEntityType) cannot be called directly via ->call() in Livewire tests.',
 'Set the property via ->set() which triggers the hook automatically.',
 'medium', 0, 'active', '1.0.5');

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-010', 'base', 'testing', 'Routes registered in test setUp not in named route lookup',
 'Routes registered in test setUp() are in the route collection but NOT in the named route lookup.',
 'Call app(''router'')->getRoutes()->refreshNameLookups() after registering routes in tests.',
 'medium', 0, 'active', '1.0.5');

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-011', 'base', 'filament', 'DoughnutChartWidget class deprecated in Filament v4',
 'DoughnutChartWidget class is deprecated in Filament v4.',
 'Use ChartWidget with getType() returning ''doughnut''.',
 'medium', 1, 'active', '1.0.5');

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-012', 'base', 'filament', 'Section is in Schemas namespace not Forms namespace',
 'Section is in Filament\Schemas\Components\Section, NOT Filament\Forms\Components\Section. Same for Grid.',
 'Form components (TextInput, Select, Toggle) are in Filament\Forms\Components. Layout components (Section, Grid) are in Filament\Schemas\Components.',
 'critical', 0, 'active', '1.0.5');

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-013', 'base', 'tailwind', 'Tailwind v4 does not support dynamic class interpolation',
 'Tailwind v4 does not support dynamic class interpolation (bg-{{ $color }}-500). Classes must be complete strings at compile time.',
 'Use explicit match expressions that return complete class strings. Never interpolate Tailwind class fragments.',
 'high', 0, 'active', '1.0.5');

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-014', 'base', 'testing', 'phpunit.xml needs BROADCAST_CONNECTION=log for tests',
 'phpunit.xml needs BROADCAST_CONNECTION=log to prevent broadcast driver failures during tests. Without it, tests that trigger broadcastable events will fail.',
 'Always set BROADCAST_CONNECTION=log in test environment config (phpunit.xml or .env.testing).',
 'high', 1, 'active', '1.0.5');

INSERT OR REPLACE INTO failures (failure_id, tier, category, title, description, preventive_rule, severity, scaffolding_fixed, status, aicl_version)
VALUES
('BF-015', 'base', 'migrations', 'Never modify published migrations after release',
 'Framework development consolidated multiple migrations into single files. This works for fresh installs but would break existing databases that already ran the original split migrations.',
 'NEVER modify a published migration after it has been tagged in a release. Always create a new migration for schema changes. Consolidation is only safe before first release or for fresh skeleton builds.',
 'critical', 0, 'active', '1.0.5');
