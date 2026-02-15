<?php

namespace Aicl\Database\Seeders;

use Aicl\Models\RlmLesson;
use App\Models\User;
use Illuminate\Database\Seeder;

class RlmLessonSeeder extends Seeder
{
    public function run(): void
    {
        $ownerId = User::first()?->id ?? 1;

        foreach ($this->curatedLessons() as $lesson) {
            RlmLesson::query()->updateOrCreate(
                ['topic' => $lesson['topic'], 'subtopic' => $lesson['subtopic']],
                $lesson + [
                    'owner_id' => $ownerId,
                    'source' => 'base-seeder',
                    'confidence' => 1.0,
                    'is_verified' => true,
                    'is_active' => true,
                    'view_count' => 0,
                ]
            );
        }
    }

    /**
     * Curated lessons distilled from base failures (BF-001 through BF-015).
     *
     * @return array<int, array<string, mixed>>
     */
    private function curatedLessons(): array
    {
        return [
            [
                'topic' => 'scaffolder',
                'subtopic' => 'models',
                'summary' => 'When your model lacks a name or title column, override searchableColumns() to list only columns that exist on the table.',
                'detail' => 'HasStandardScopes::searchableColumns() returns [name, title] by default. Models without a title column get QueryException on the search scope. BF-001 and BF-005 both stem from this default. The scaffolder now generates the override automatically, but manually created models must still override it.',
                'tags' => 'searchableColumns, HasStandardScopes, QueryException',
                'context_tags' => ['scaffolder', 'models', 'search', 'BF-001', 'BF-005'],
            ],
            [
                'topic' => 'filament',
                'subtopic' => 'namespaces',
                'summary' => 'Section and Grid are in Filament\Schemas\Components, NOT Filament\Forms\Components. Form inputs (TextInput, Select) stay in Forms.',
                'detail' => 'Filament v4 moved layout components to a separate Schemas namespace. Using the wrong import silently fails or throws class-not-found errors. This is the #1 most common Filament mistake. BF-012.',
                'tags' => 'Filament, namespaces, Section, Grid, Schemas',
                'context_tags' => ['filament', 'namespaces', 'forms', 'BF-012'],
            ],
            [
                'topic' => 'filament',
                'subtopic' => 'charts',
                'summary' => 'DoughnutChartWidget is deprecated in Filament v4. Use ChartWidget with getType() returning doughnut.',
                'detail' => 'Filament v4 consolidated chart widgets into a single ChartWidget class with configurable chart types via getType(). Using the old DoughnutChartWidget causes deprecation warnings. BF-011.',
                'tags' => 'Filament, charts, DoughnutChartWidget, ChartWidget',
                'context_tags' => ['filament', 'charts', 'widgets', 'BF-011'],
            ],
            [
                'topic' => 'filament',
                'subtopic' => 'dusk',
                'summary' => 'Filament v4 form field HTML id uses form.{field} prefix. wire:model uses data.{field}. Do not mix them in Dusk selectors.',
                'detail' => 'Filament v4 changed the HTML id generation to use form. prefix (e.g., id="form.email") instead of data. prefix. Tests using #data.email will fail — use #form.email for id selectors and data.email for wire:model selectors. BF-006.',
                'tags' => 'Filament, Dusk, selectors, form, wire:model',
                'context_tags' => ['filament', 'dusk', 'testing', 'BF-006'],
            ],
            [
                'topic' => 'auth',
                'subtopic' => 'guards',
                'summary' => 'Spatie Permission scopes checks to the current guard. When using API guard, seed permissions on BOTH web AND api guards.',
                'detail' => 'actingAs($user, "api") changes the default guard to api. Permissions seeded only on the web guard become invisible. Always seed permissions AND roles on both guards. Assign roles on both guards for API tests. BF-007.',
                'tags' => 'Spatie, Permission, guards, API, web',
                'context_tags' => ['auth', 'guards', 'api', 'permissions', 'BF-007'],
            ],
            [
                'topic' => 'laravel',
                'subtopic' => 'events',
                'summary' => 'Never use SerializesModels in delete event classes — the model may already be gone when the event processes.',
                'detail' => 'SerializesModels attempts to re-fetch the model from the database during serialization. For EntityDeleted events with ShouldBroadcast, the model may be soft-deleted or hard-deleted, causing fetch failure. Store needed attributes as primitive properties instead. BF-008.',
                'tags' => 'SerializesModels, events, delete, broadcast',
                'context_tags' => ['laravel', 'events', 'broadcasting', 'BF-008'],
            ],
            [
                'topic' => 'testing',
                'subtopic' => 'livewire',
                'summary' => 'Livewire lifecycle hooks (updatedQuery, etc.) fire on property change, not direct ->call(). Use ->set() to trigger them.',
                'detail' => 'Livewire lifecycle hooks are triggered by property changes, not direct method calls. Using ->call("updatedQuery") bypasses the property change detection and the hook never fires. Use ->set("query", "value") which triggers the hook automatically. BF-009.',
                'tags' => 'Livewire, lifecycle, hooks, testing, set',
                'context_tags' => ['testing', 'livewire', 'BF-009'],
            ],
            [
                'topic' => 'testing',
                'subtopic' => 'routes',
                'summary' => 'Routes registered in test setUp() are in the collection but NOT in the named route lookup — call refreshNameLookups() after registering.',
                'detail' => 'Laravel caches the named route lookup. Routes added after initial compilation are in the collection but not discoverable by name. Call app("router")->getRoutes()->refreshNameLookups() after registering routes in test setUp(). BF-010.',
                'tags' => 'routes, testing, setUp, refreshNameLookups',
                'context_tags' => ['testing', 'routes', 'BF-010'],
            ],
            [
                'topic' => 'tailwind',
                'subtopic' => 'classes',
                'summary' => 'Tailwind v4 does not support dynamic class interpolation. Use match expressions returning complete class strings.',
                'detail' => 'Tailwind v4 uses a JIT compiler that scans source files for complete class names. Dynamic interpolation like bg-{{ $color }}-500 produces class names that do not exist at compile time. Use explicit match expressions that return complete class strings instead. BF-013.',
                'tags' => 'Tailwind, dynamic classes, JIT, interpolation',
                'context_tags' => ['tailwind', 'classes', 'BF-013'],
            ],
            [
                'topic' => 'testing',
                'subtopic' => 'config',
                'summary' => 'Set BROADCAST_CONNECTION=log in phpunit.xml to prevent broadcast driver failures during tests.',
                'detail' => 'The default broadcast driver may not be available in test environments. Broadcastable events dispatched during tests fail if the driver cannot connect. Always set BROADCAST_CONNECTION=log in phpunit.xml or .env.testing. BF-014.',
                'tags' => 'broadcast, testing, phpunit, config',
                'context_tags' => ['testing', 'config', 'broadcast', 'BF-014'],
            ],
            [
                'topic' => 'process',
                'subtopic' => 'migrations',
                'summary' => 'Never modify a published migration after release. Always create a new migration for schema changes.',
                'detail' => 'Merging or modifying migrations assumes no database has ever run the original versions. Existing databases track migration names and would not re-run consolidated versions. Consolidation is only safe before first release or for fresh skeleton builds. BF-015.',
                'tags' => 'migrations, releases, schema, consolidation',
                'context_tags' => ['process', 'migrations', 'releases', 'BF-015'],
            ],
            [
                'topic' => 'process',
                'subtopic' => 'pipeline',
                'summary' => 'After context continuation, re-read ALL pipeline documents before resuming. Pipeline doc is the source of truth, not memory.',
                'detail' => 'Context continuation (token limit truncation) causes agents to lose track of pipeline state. Without re-reading pipeline documents, agents may skip phase gates, not update tracking docs, or abandon structured pipeline discipline. Broad human directives compound the issue. BF-004.',
                'tags' => 'pipeline, context continuation, phase gates',
                'context_tags' => ['process', 'pipeline', 'agents', 'BF-004'],
            ],
            [
                'topic' => 'process',
                'subtopic' => 'registration',
                'summary' => 'Entity registration (policy, observer, routes, resource) MUST happen AFTER validation passes. Never wire up before Phase 5.',
                'detail' => 'Registering an entity (binding policy, observer, API routes, Filament resource discovery) before running validation and tests bypasses the validate-then-register pipeline order. The pipeline enforces this via phase gate checks — Phase 5 requires Phase 4 RLM and Tester both PASS. BF-002.',
                'tags' => 'pipeline, registration, phase gates, validation',
                'context_tags' => ['process', 'registration', 'pipeline', 'BF-002'],
            ],
            [
                'topic' => 'scaffolder',
                'subtopic' => 'actions',
                'summary' => 'Scaffolded list pages MUST include CreateAction, and tables MUST include Export, View, Edit, and bulk actions.',
                'detail' => 'MakeEntityCommand must generate list pages with getHeaderActions() containing CreateAction, tables with ExportAction in headerActions, ViewAction + EditAction in recordActions, and ExportBulkAction + DeleteBulkAction in toolbarActions BulkActionGroup. Without these, entities pass validation but fail user acceptance. BF-003.',
                'tags' => 'scaffolder, actions, Filament, list page, table',
                'context_tags' => ['scaffolder', 'actions', 'filament', 'BF-003'],
            ],
            [
                'topic' => 'testing',
                'subtopic' => 'notifications',
                'summary' => 'Never use Notification::fake() in observer tests — it breaks NotificationDispatcher.',
                'detail' => 'Notification::fake() replaces the notification channel manager, which breaks the NotificationDispatcher service that observers depend on. In observer tests, use Event::fake() or mock the specific notification channel instead. This is a persistent gotcha with the AICL notification architecture.',
                'tags' => 'Notification, fake, observer, NotificationDispatcher',
                'context_tags' => ['testing', 'notifications', 'observers'],
            ],
            [
                'topic' => 'database',
                'subtopic' => 'postgresql',
                'summary' => 'PostgreSQL LIKE is case-sensitive and MAX(uuid) fails. Use LOWER() for LIKE and groupBy + latest() for UUID aggregation.',
                'detail' => 'Two common PostgreSQL gotchas: (1) LIKE is case-sensitive unlike MySQL — use LOWER(column) LIKE LOWER(pattern). (2) MAX(uuid) fails because UUIDs are not orderable by value — use groupBy + latest("created_at") for "latest per group" queries with UUID primary keys.',
                'tags' => 'PostgreSQL, LIKE, UUID, MAX, case-sensitive',
                'context_tags' => ['database', 'postgresql', 'queries'],
            ],
            [
                'topic' => 'testing',
                'subtopic' => 'http-fakes',
                'summary' => 'Http::assertSentCount() includes ALL faked requests including Scout/ES. Use Http::assertSent() with URL filter instead.',
                'detail' => 'When Http::fake() is active, ALL HTTP requests are captured — including internal requests to Elasticsearch from Scout. Http::assertSentCount() counts these too. Use Http::assertSent() with a URL pattern filter to isolate the requests you care about.',
                'tags' => 'Http, fake, assertSentCount, Scout, Elasticsearch',
                'context_tags' => ['testing', 'http', 'scout', 'elasticsearch'],
            ],
        ];
    }
}
