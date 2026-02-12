<?php

namespace Aicl\Database\Seeders;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Models\RlmFailure;
use Aicl\States\RlmFailure\Confirmed;
use App\Models\User;
use Illuminate\Database\Seeder;

class BaseFailureSeeder extends Seeder
{
    public function run(): void
    {
        $ownerId = User::first()?->id ?? 1;

        foreach ($this->baseFailures() as $failure) {
            RlmFailure::query()->updateOrCreate(
                ['failure_code' => $failure['failure_code']],
                $failure + [
                    'owner_id' => $ownerId,
                    'status' => Confirmed::getMorphClass(),
                    'is_active' => true,
                    'promoted_to_base' => true,
                    'promoted_at' => now(),
                    'aicl_version' => '1.1.0',
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function baseFailures(): array
    {
        return [
            [
                'failure_code' => 'BF-001',
                'category' => FailureCategory::Scaffolding,
                'severity' => FailureSeverity::High,
                'title' => 'HasStandardScopes::searchableColumns() defaults to [name, title]',
                'description' => 'Models without a title column get QueryException: Unknown column on search scope.',
                'root_cause' => 'searchableColumns() returns [name, title] by default. Models that lack a title column fail when the search scope builds a LIKE query against a non-existent column.',
                'preventive_rule' => 'When using HasStandardScopes, ALWAYS override searchableColumns() to list only columns that exist on the entity\'s table.',
                'scaffolding_fixed' => true,
            ],
            [
                'failure_code' => 'BF-002',
                'category' => FailureCategory::Process,
                'severity' => FailureSeverity::Critical,
                'title' => 'Entity registered before validation — pipeline order bypassed',
                'description' => 'Agent wired up entity (policy binding, observer, API routes, Filament resource discovery) WITHOUT running validation or tests first. Bypassed the validate→register pipeline order.',
                'root_cause' => 'No pipeline enforcement existed. The Architect agent was invoked independently and went straight to registration because it saw unregistered code.',
                'preventive_rule' => 'Entity registration (policy binding, observer binding, API routes, Filament resource discovery) MUST happen in a separate phase AFTER validation passes. The pipeline enforces this via phase gate checks.',
                'scaffolding_fixed' => false,
            ],
            [
                'failure_code' => 'BF-003',
                'category' => FailureCategory::Scaffolding,
                'severity' => FailureSeverity::High,
                'title' => 'List page missing CreateAction; Table missing Export/record/bulk actions',
                'description' => 'List page missing CreateAction in header; Table missing ExportAction in headerActions, ViewAction/EditAction in recordActions, and ExportBulkAction/DeleteBulkAction in toolbarActions. Entity passed RLM validation and all tests but failed user acceptance testing.',
                'root_cause' => 'MakeEntityCommand scaffolding generated a bare ListRecords page without getHeaderActions() with CreateAction, and a table without headerActions, recordActions, or toolbarActions.',
                'preventive_rule' => 'Scaffolding MUST include: (1) CreateAction on List page, (2) ExportAction in table headerActions, (3) ViewAction + EditAction in recordActions, (4) ExportBulkAction + DeleteBulkAction in toolbarActions BulkActionGroup.',
                'scaffolding_fixed' => true,
            ],
            [
                'failure_code' => 'BF-004',
                'category' => FailureCategory::Process,
                'severity' => FailureSeverity::Critical,
                'title' => 'Context continuation caused agent to abandon pipeline',
                'description' => 'After context continuation (token limit truncation), agent abandoned structured pipeline — skipped phase gates, did not update pipeline documents, did not follow phase-gate discipline. Broad human directive compounded the issue.',
                'root_cause' => 'No instruction existed for detecting and recovering from context truncation. Human gave broad directive without requesting explicit phase-gate checkpoints.',
                'preventive_rule' => 'After ANY context continuation, agents MUST re-read all active pipeline documents before resuming work. Pipeline document is the source of truth, not conversational memory. Multi-entity projects process ONE entity at a time with human checkpoints.',
                'scaffolding_fixed' => false,
            ],
            [
                'failure_code' => 'BF-005',
                'category' => FailureCategory::Filament,
                'severity' => FailureSeverity::High,
                'title' => 'searchableColumns() defaults break Filament search',
                'description' => 'HasStandardScopes::searchableColumns() returns [name, title] by default. Models without title column get 500 errors on search scope in Filament admin.',
                'root_cause' => 'Same root cause as BF-001, but manifests in Filament global search context specifically.',
                'preventive_rule' => 'Always override searchableColumns() to list only columns that exist on the entity\'s table.',
                'scaffolding_fixed' => true,
            ],
            [
                'failure_code' => 'BF-006',
                'category' => FailureCategory::Filament,
                'severity' => FailureSeverity::Medium,
                'title' => 'Filament v4 form field HTML id uses form. prefix, not data.',
                'description' => 'Filament v4 form field HTML id uses form. prefix (e.g., id="form.email"), NOT data. wire:model uses data.email but the HTML id is form.email.',
                'root_cause' => 'Filament v4 changed the HTML id generation to use form. prefix instead of data. prefix.',
                'preventive_rule' => 'In Dusk tests, use form.{field} for #id selectors and data.{field} for wire:model selectors.',
                'scaffolding_fixed' => false,
            ],
            [
                'failure_code' => 'BF-007',
                'category' => FailureCategory::Auth,
                'severity' => FailureSeverity::High,
                'title' => 'Spatie Permission + API guard: permissions not found across guards',
                'description' => 'actingAs($user, \'api\') changes the default guard to api. Permissions seeded only on web guard won\'t be found.',
                'root_cause' => 'Spatie Permission scopes permission checks to the current guard. When actingAs changes to api guard, permissions seeded only on web guard are invisible.',
                'preventive_rule' => 'Seed permissions AND roles on BOTH web and api guards. Assign roles on both guards for API tests.',
                'scaffolding_fixed' => false,
            ],
            [
                'failure_code' => 'BF-008',
                'category' => FailureCategory::Laravel,
                'severity' => FailureSeverity::Medium,
                'title' => 'EntityDeleted event must not use SerializesModels',
                'description' => 'Entity events with ShouldBroadcast: EntityDeleted must NOT use SerializesModels trait because the model may already be deleted when the event is processed.',
                'root_cause' => 'SerializesModels attempts to re-fetch the model from the database during serialization. If the model is soft-deleted or hard-deleted, this fails.',
                'preventive_rule' => 'Never use SerializesModels in delete event classes.',
                'scaffolding_fixed' => false,
            ],
            [
                'failure_code' => 'BF-009',
                'category' => FailureCategory::Testing,
                'severity' => FailureSeverity::Medium,
                'title' => 'Livewire lifecycle hooks cannot be called directly via ->call()',
                'description' => 'Livewire lifecycle hooks (updatedQuery, updatedEntityType) cannot be called directly via ->call() in Livewire tests.',
                'root_cause' => 'Livewire lifecycle hooks are triggered by property changes, not direct method calls. Using ->call() on a lifecycle hook bypasses the property change detection.',
                'preventive_rule' => 'Set the property via ->set() which triggers the hook automatically.',
                'scaffolding_fixed' => false,
            ],
            [
                'failure_code' => 'BF-010',
                'category' => FailureCategory::Testing,
                'severity' => FailureSeverity::Medium,
                'title' => 'Routes registered in test setUp() missing from named route lookup',
                'description' => 'Routes registered in test setUp() are in the route collection but NOT in the named route lookup.',
                'root_cause' => 'Laravel caches the named route lookup. Routes added after initial compilation are in the collection but not discoverable by name.',
                'preventive_rule' => 'Call app(\'router\')->getRoutes()->refreshNameLookups() after registering routes in tests.',
                'scaffolding_fixed' => false,
            ],
            [
                'failure_code' => 'BF-011',
                'category' => FailureCategory::Filament,
                'severity' => FailureSeverity::Medium,
                'title' => 'DoughnutChartWidget class is deprecated in Filament v4',
                'description' => 'DoughnutChartWidget class is deprecated in Filament v4. Using it causes deprecation warnings.',
                'root_cause' => 'Filament v4 consolidated chart widgets into a single ChartWidget class with configurable chart types.',
                'preventive_rule' => 'Use ChartWidget with getType() returning \'doughnut\'.',
                'scaffolding_fixed' => true,
            ],
            [
                'failure_code' => 'BF-012',
                'category' => FailureCategory::Filament,
                'severity' => FailureSeverity::Critical,
                'title' => 'Section/Grid namespace is Filament\Schemas\Components, not Forms\Components',
                'description' => 'Section is in Filament\Schemas\Components\Section, NOT Filament\Forms\Components\Section. Same for Grid.',
                'root_cause' => 'Filament v4 moved layout components to a separate Schemas namespace while keeping form input components in Forms.',
                'preventive_rule' => 'Form components (TextInput, Select, Toggle) are in Filament\Forms\Components. Layout components (Section, Grid) are in Filament\Schemas\Components.',
                'scaffolding_fixed' => false,
            ],
            [
                'failure_code' => 'BF-013',
                'category' => FailureCategory::Tailwind,
                'severity' => FailureSeverity::High,
                'title' => 'Tailwind v4 does not support dynamic class interpolation',
                'description' => 'Tailwind v4 does not support dynamic class interpolation (bg-{{ $color }}-500). Classes must be complete strings at compile time.',
                'root_cause' => 'Tailwind v4 uses a JIT compiler that scans source files for complete class names. Dynamic interpolation produces class names that don\'t exist at compile time.',
                'preventive_rule' => 'Use explicit match expressions that return complete class strings. Never interpolate Tailwind class fragments.',
                'scaffolding_fixed' => false,
            ],
            [
                'failure_code' => 'BF-014',
                'category' => FailureCategory::Testing,
                'severity' => FailureSeverity::Medium,
                'title' => 'phpunit.xml needs BROADCAST_CONNECTION=log for tests',
                'description' => 'phpunit.xml needs BROADCAST_CONNECTION=log to prevent broadcast driver failures during tests. Without it, tests that trigger broadcastable events will fail.',
                'root_cause' => 'Default broadcast driver may not be available in test environment. Broadcastable events dispatched during tests fail if the driver cannot connect.',
                'preventive_rule' => 'Always set BROADCAST_CONNECTION=log in test environment config (phpunit.xml or .env.testing).',
                'scaffolding_fixed' => true,
            ],
            [
                'failure_code' => 'BF-015',
                'category' => FailureCategory::Configuration,
                'severity' => FailureSeverity::High,
                'title' => 'Never modify published migrations after release',
                'description' => 'Framework development consolidated multiple migrations into single files. This works for fresh installs but would break existing databases that already ran the original split migrations.',
                'root_cause' => 'Merging migrations assumes no database has ever run the original versions. Existing databases track migration names and would not re-run consolidated versions.',
                'preventive_rule' => 'NEVER modify a published migration after it has been tagged in a release. Always create a new migration for schema changes. Consolidation is only safe before first release or for fresh skeleton builds.',
                'scaffolding_fixed' => false,
            ],
        ];
    }
}
