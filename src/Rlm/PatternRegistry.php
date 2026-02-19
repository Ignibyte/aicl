<?php

namespace Aicl\Rlm;

/**
 * Registry of all AICL entity validation patterns.
 *
 * Patterns are organized by target file type. The AI-generated code
 * is scored against these patterns to ensure consistency with AICL conventions.
 */
class PatternRegistry
{
    /**
     * Current pattern set version.
     */
    public const VERSION = 'v1';

    /**
     * @return array<int, EntityPattern>
     */
    public static function all(?string $entityName = null): array
    {
        $patterns = array_merge(
            static::modelPatterns(),
            static::migrationPatterns(),
            static::factoryPatterns(),
            static::policyPatterns(),
            static::observerPatterns(),
            static::filamentPatterns(),
            static::testPatterns(),
            static::specPatterns(),
            static::componentPatterns(),
            static::viewPatterns(),
        );

        if ($entityName !== null) {
            $patterns = array_merge($patterns, static::registrationPatterns($entityName));
        }

        return $patterns;
    }

    /**
     * Get patterns filtered by version range.
     *
     * @return array<int, EntityPattern>
     */
    public static function getPatternSet(string $version, ?string $entityName = null): array
    {
        return array_values(array_filter(
            static::all($entityName),
            fn (EntityPattern $p): bool => $p->isActiveInVersion($version),
        ));
    }

    /**
     * Get the current pattern set version string.
     */
    public static function currentVersion(): string
    {
        return static::VERSION;
    }

    /**
     * Registration patterns verify that an entity is properly wired up
     * in AppServiceProvider and routes/api.php. These patterns are
     * entity-name-specific and used in Phase 6 (Re-Validate).
     *
     * @return array<int, EntityPattern>
     */
    public static function registrationPatterns(string $entityName): array
    {
        $snakePlural = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::pluralStudly($entityName));

        return [
            new EntityPattern(
                name: 'registration.policy_bound',
                description: "Policy must be bound: Gate::policy({$entityName}::class",
                target: 'app_service_provider',
                check: 'Gate::policy\\('.$entityName.'::class',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'registration.observer_bound',
                description: "Observer must be bound: {$entityName}::observe({$entityName}Observer::class",
                target: 'app_service_provider',
                check: $entityName.'::observe\\('.$entityName.'Observer::class',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'registration.api_routes',
                description: "API routes must exist: Route::apiResource('{$snakePlural}'",
                target: 'api_routes',
                check: "Route::apiResource\\('".$snakePlural."'",
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'registration.filament_discovery',
                description: 'AdminPanelProvider must have discoverResources() for auto-discovery',
                target: 'admin_panel_provider',
                check: 'discoverResources',
                severity: 'warning',
                weight: 1.0,
            ),
        ];
    }

    /**
     * @return array<int, EntityPattern>
     */
    public static function modelPatterns(): array
    {
        return [
            new EntityPattern(
                name: 'model.namespace',
                description: 'Model must be in App\\Models or Aicl\\Models namespace',
                target: 'model',
                check: 'namespace (App|Aicl)\\\\Models;',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'model.extends',
                description: 'Model must extend Illuminate\\Database\\Eloquent\\Model',
                target: 'model',
                check: 'extends Model',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'model.has_entity_events',
                description: 'Model should use HasEntityEvents trait',
                target: 'model',
                check: 'use HasEntityEvents;',
                severity: 'warning',
                weight: 1.5,
            ),
            new EntityPattern(
                name: 'model.has_audit_trail',
                description: 'Model should use HasAuditTrail trait',
                target: 'model',
                check: 'use HasAuditTrail;',
                severity: 'warning',
                weight: 1.5,
            ),
            new EntityPattern(
                name: 'model.has_standard_scopes',
                description: 'Model should use HasStandardScopes trait',
                target: 'model',
                check: 'use HasStandardScopes;',
                severity: 'warning',
                weight: 1.0,
            ),
            new EntityPattern(
                name: 'model.soft_deletes',
                description: 'Model should use SoftDeletes',
                target: 'model',
                check: 'use SoftDeletes;',
                severity: 'warning',
                weight: 1.0,
            ),
            new EntityPattern(
                name: 'model.has_factory',
                description: 'Model must use HasFactory trait',
                target: 'model',
                check: 'use HasFactory;',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'model.fillable',
                description: 'Model must declare $fillable array',
                target: 'model',
                check: 'protected \\$fillable',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'model.casts_method',
                description: 'Model should use casts() method (Laravel 11 convention)',
                target: 'model',
                check: 'protected function casts\\(\\)',
                severity: 'warning',
                weight: 1.0,
            ),
            new EntityPattern(
                name: 'model.new_factory',
                description: 'Model must define newFactory() for package factory resolution',
                target: 'model',
                check: 'protected static function newFactory\\(\\)',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'model.owner_relationship',
                description: 'Model should define owner() BelongsTo relationship',
                target: 'model',
                check: 'public function owner\\(\\)',
                severity: 'warning',
                weight: 1.0,
            ),
            new EntityPattern(
                name: 'model.return_types',
                description: 'Relationship methods should have return type hints',
                target: 'model',
                check: '\\): (BelongsTo|HasMany|BelongsToMany|HasOne|MorphMany)',
                severity: 'warning',
                weight: 1.0,
            ),
            new EntityPattern(
                name: 'media.gallery_integration',
                description: 'Media-enabled models should use HasMediaCollections or InteractsWithMediaManager trait',
                target: 'model',
                check: 'use (HasMediaCollections|InteractsWithMediaManager);',
                severity: 'warning',
                weight: 0.5,
            ),
            new EntityPattern(
                name: 'media.has_media_interface',
                description: 'Media-enabled models should implement HasMedia interface',
                target: 'model',
                check: 'implements.*HasMedia',
                severity: 'warning',
                weight: 0.5,
            ),
        ];
    }

    /**
     * @return array<int, EntityPattern>
     */
    public static function migrationPatterns(): array
    {
        return [
            new EntityPattern(
                name: 'migration.anonymous_class',
                description: 'Migration must use anonymous class (Laravel 11)',
                target: 'migration',
                check: 'return new class extends Migration',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'migration.timestamps',
                description: 'Migration must include timestamps()',
                target: 'migration',
                check: '\\$table->timestamps\\(\\)',
                severity: 'error',
                weight: 1.5,
            ),
            new EntityPattern(
                name: 'migration.soft_deletes',
                description: 'Migration should include softDeletes()',
                target: 'migration',
                check: '\\$table->softDeletes\\(\\)',
                severity: 'warning',
                weight: 1.0,
            ),
            new EntityPattern(
                name: 'migration.id',
                description: 'Migration must include id() primary key',
                target: 'migration',
                check: '\\$table->id\\(\\)',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'migration.down_method',
                description: 'Migration must include down() method',
                target: 'migration',
                check: 'public function down\\(\\)',
                severity: 'error',
                weight: 1.5,
            ),
        ];
    }

    /**
     * @return array<int, EntityPattern>
     */
    public static function factoryPatterns(): array
    {
        return [
            new EntityPattern(
                name: 'factory.namespace',
                description: 'Factory must be in a Database\\Factories namespace',
                target: 'factory',
                check: 'namespace (Aicl\\\\)?Database\\\\Factories;',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'factory.extends',
                description: 'Factory must extend Factory',
                target: 'factory',
                check: 'extends Factory',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'factory.model_property',
                description: 'Factory must set $model property',
                target: 'factory',
                check: 'protected \\$model',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'factory.definition_method',
                description: 'Factory must implement definition() method',
                target: 'factory',
                check: 'public function definition\\(\\): array',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'factory.uses_fake',
                description: 'Factory should use fake() helper for test data',
                target: 'factory',
                check: 'fake\\(\\)',
                severity: 'warning',
                weight: 1.0,
            ),
        ];
    }

    /**
     * @return array<int, EntityPattern>
     */
    public static function policyPatterns(): array
    {
        return [
            new EntityPattern(
                name: 'policy.extends_base',
                description: 'Policy must extend BasePolicy',
                target: 'policy',
                check: 'extends BasePolicy',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'policy.view_method',
                description: 'Policy must implement view() method',
                target: 'policy',
                check: 'public function view\\(',
                severity: 'error',
                weight: 1.5,
            ),
            new EntityPattern(
                name: 'policy.update_method',
                description: 'Policy must implement update() method',
                target: 'policy',
                check: 'public function update\\(',
                severity: 'error',
                weight: 1.5,
            ),
            new EntityPattern(
                name: 'policy.delete_method',
                description: 'Policy must implement delete() method',
                target: 'policy',
                check: 'public function delete\\(',
                severity: 'error',
                weight: 1.5,
            ),
            new EntityPattern(
                name: 'policy.owner_check',
                description: 'Policy should check owner_id for self-authorization',
                target: 'policy',
                check: 'owner_id',
                severity: 'warning',
                weight: 1.0,
            ),
        ];
    }

    /**
     * @return array<int, EntityPattern>
     */
    public static function observerPatterns(): array
    {
        return [
            new EntityPattern(
                name: 'observer.extends_base',
                description: 'Observer must extend BaseObserver',
                target: 'observer',
                check: 'extends BaseObserver',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'observer.created_method',
                description: 'Observer should implement created() method',
                target: 'observer',
                check: 'public function created\\(',
                severity: 'warning',
                weight: 1.0,
            ),
            new EntityPattern(
                name: 'observer.activity_logging',
                description: 'Observer should log activity via activity()',
                target: 'observer',
                check: 'activity\\(\\)',
                severity: 'warning',
                weight: 1.0,
            ),
        ];
    }

    /**
     * @return array<int, EntityPattern>
     */
    public static function filamentPatterns(): array
    {
        return [
            new EntityPattern(
                name: 'filament.extends_resource',
                description: 'Filament resource must extend Resource',
                target: 'filament',
                check: 'extends Resource',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'filament.model_property',
                description: 'Filament resource must set $model property',
                target: 'filament',
                check: 'protected static \\?string \\$model',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'filament.navigation_icon',
                description: 'Filament resource must set navigation icon',
                target: 'filament',
                check: '\\$navigationIcon',
                severity: 'warning',
                weight: 1.0,
            ),
            new EntityPattern(
                name: 'filament.navigation_group',
                description: 'Filament resource must set navigation group',
                target: 'filament',
                check: '\\$navigationGroup',
                severity: 'warning',
                weight: 1.0,
            ),
            new EntityPattern(
                name: 'filament.form_method',
                description: 'Filament resource must define form() method',
                target: 'filament',
                check: 'public static function form\\(',
                severity: 'error',
                weight: 1.5,
            ),
            new EntityPattern(
                name: 'filament.table_method',
                description: 'Filament resource must define table() method',
                target: 'filament',
                check: 'public static function table\\(',
                severity: 'error',
                weight: 1.5,
            ),
            new EntityPattern(
                name: 'filament.infolist_method',
                description: 'Filament resource must define infolist() method for View page card display',
                target: 'filament',
                check: 'public static function infolist\\(',
                severity: 'warning',
                weight: 1.5,
            ),
            new EntityPattern(
                name: 'filament.sub_navigation',
                description: 'Filament resource must define getRecordSubNavigation() for View↔Edit tabs',
                target: 'filament',
                check: 'public static function getRecordSubNavigation\\(',
                severity: 'warning',
                weight: 1.0,
            ),
            new EntityPattern(
                name: 'filament.sub_navigation_position',
                description: 'Filament resource must set $subNavigationPosition for tab placement',
                target: 'filament',
                check: '\\$subNavigationPosition',
                severity: 'warning',
                weight: 0.5,
            ),
            new EntityPattern(
                name: 'filament.section_column_span',
                description: 'Form sections must use ->columnSpanFull() (Filament v4 layout requirement)',
                target: 'form',
                check: 'columnSpanFull\\(\\)',
                severity: 'warning',
                weight: 1.0,
            ),
            new EntityPattern(
                name: 'filament.infolist_text_entry',
                description: 'Infolist schema must use TextEntry components for data display',
                target: 'infolist',
                check: 'TextEntry::make\\(',
                severity: 'warning',
                weight: 1.0,
            ),
            new EntityPattern(
                name: 'filament.infolist_section_layout',
                description: 'Infolist sections must use ->columnSpanFull() (Filament v4 layout requirement)',
                target: 'infolist',
                check: 'columnSpanFull\\(\\)',
                severity: 'warning',
                weight: 0.5,
            ),
        ];
    }

    /**
     * @return array<int, EntityPattern>
     */
    public static function testPatterns(): array
    {
        return [
            new EntityPattern(
                name: 'test.extends_testcase',
                description: 'Test must extend TestCase',
                target: 'test',
                check: 'extends TestCase',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'test.database_transactions',
                description: 'Test must use DatabaseTransactions trait (not RefreshDatabase which destroys data)',
                target: 'test',
                check: 'use DatabaseTransactions;',
                severity: 'error',
                weight: 2.0,
            ),
            new EntityPattern(
                name: 'test.creation_test',
                description: 'Test must include a creation test',
                target: 'test',
                check: 'test_.*can_be_created',
                severity: 'error',
                weight: 1.5,
            ),
            new EntityPattern(
                name: 'test.policy_test',
                description: 'Test should include policy authorization tests',
                target: 'test',
                check: 'test_.*(can_view|can_manage|owner)',
                severity: 'warning',
                weight: 1.0,
            ),
        ];
    }

    /**
     * Spec file patterns (P-043 through P-046).
     *
     * These are ADDITIVE and OPTIONAL — they only apply when a
     * .entity.md spec file exists for the entity. Entities generated
     * without spec files still pass the original 42 base patterns.
     *
     * @return array<int, EntityPattern>
     */
    public static function specPatterns(): array
    {
        return [
            new EntityPattern(
                name: 'spec.file_exists',
                description: 'Entity has a corresponding .entity.md spec file in specs/',
                target: 'spec',
                check: '# [A-Z][a-zA-Z0-9]+',
                severity: 'warning',
                weight: 0.5,
            ),
            new EntityPattern(
                name: 'spec.matches_code',
                description: 'Generated code matches spec (fields, types, states are consistent)',
                target: 'spec',
                check: '## Fields',
                severity: 'warning',
                weight: 1.0,
            ),
            new EntityPattern(
                name: 'spec.has_business_rules',
                description: 'Spec includes a Business Rules section documenting domain constraints',
                target: 'spec',
                check: '## Business Rules',
                severity: 'warning',
                weight: 0.5,
            ),
            new EntityPattern(
                name: 'spec.has_description',
                description: 'Spec includes a description paragraph after the entity name header',
                target: 'spec',
                check: '# [A-Z].*\n\n[A-Za-z]',
                severity: 'warning',
                weight: 0.5,
            ),
        ];
    }

    /**
     * Component patterns (C01-C10) validate that Blade views use the
     * AICL component library correctly. Only scored for entities with
     * --views or custom Blade views.
     *
     * Targets: blade_view (public views), blade_widget (widget views)
     *
     * @return array<int, EntityPattern>
     */
    public static function componentPatterns(): array
    {
        return [
            // C01: Views should use registered AICL components, not raw HTML for known patterns
            new EntityPattern(
                name: 'component.uses_aicl_components',
                description: 'View uses <x-aicl-*> library components for UI patterns',
                target: 'blade_view',
                check: '<x-aicl-',
                severity: 'warning',
                weight: 1.5,
            ),
            // C02: Status display should use status-badge or badge, not inline styling
            new EntityPattern(
                name: 'component.status_uses_badge',
                description: 'Status display uses <x-aicl-status-badge> or <x-aicl-badge>, not raw HTML',
                target: 'blade_view',
                check: '<x-aicl-(status-badge|badge)',
                severity: 'warning',
                weight: 1.0,
            ),
            // C03: Metric displays should use stat/kpi/progress/trend cards
            new EntityPattern(
                name: 'component.metrics_use_cards',
                description: 'Metric displays use <x-aicl-stat-card>, <x-aicl-kpi-card>, or <x-aicl-progress-card>',
                target: 'blade_widget',
                check: '<x-aicl-(stat-card|kpi-card|progress-card|trend-card)',
                severity: 'warning',
                weight: 1.0,
            ),
            // C04: Stats row only contains metric-category children
            new EntityPattern(
                name: 'component.statsrow_children',
                description: 'Stats row contains metric components (stat-card, kpi-card, progress-card, trend-card)',
                target: 'blade_widget',
                check: '<x-aicl-stats-row',
                severity: 'warning',
                weight: 0.5,
            ),
            // C05: Data tables should use the data-table component
            new EntityPattern(
                name: 'component.collection_uses_table',
                description: 'Collection/list views use <x-aicl-data-table> for tabular data',
                target: 'blade_view',
                check: '<x-aicl-data-table',
                severity: 'warning',
                weight: 1.0,
            ),
            // C06: Empty states should have an action CTA
            new EntityPattern(
                name: 'component.empty_state_has_cta',
                description: 'Empty state component includes actionLabel for user guidance',
                target: 'blade_view',
                check: '<x-aicl-empty-state',
                severity: 'warning',
                weight: 0.5,
            ),
            // C07: Views should support dark mode via dark: classes
            new EntityPattern(
                name: 'component.dark_mode_support',
                description: 'Blade views include dark: variant classes for dark mode support',
                target: 'blade_view',
                check: 'dark:',
                severity: 'warning',
                weight: 1.0,
            ),
            // C08: Responsive grid layout
            new EntityPattern(
                name: 'component.responsive_grid',
                description: 'Grid layouts use responsive classes (sm:, md:, lg: grid columns)',
                target: 'blade_view',
                check: '(sm:|md:|lg:)(grid-cols|col-span)',
                severity: 'warning',
                weight: 1.0,
            ),
            // C09: Layout uses split-layout or card-grid for structure
            new EntityPattern(
                name: 'component.layout_structure',
                description: 'Show views use <x-aicl-split-layout> or <x-aicl-card-grid> for page structure',
                target: 'blade_view',
                check: '<x-aicl-(split-layout|card-grid)',
                severity: 'warning',
                weight: 1.0,
            ),
            // C10: Widget views use AICL components, not raw HTML metrics
            new EntityPattern(
                name: 'component.widget_uses_components',
                description: 'Widget Blade views use <x-aicl-*> components for metric display',
                target: 'blade_widget',
                check: '<x-aicl-',
                severity: 'warning',
                weight: 1.0,
            ),
        ];
    }

    /**
     * View patterns validate generated public Blade views (V01-V08).
     *
     * @return array<int, EntityPattern>
     */
    public static function viewPatterns(): array
    {
        return [
            // V01: Index view extends a layout and has proper page structure
            new EntityPattern(
                name: 'view.blade_structure',
                description: 'Blade view extends a layout or uses a component-based page wrapper',
                target: 'blade_index',
                check: '(<x-|@extends|@section)',
                severity: 'error',
                weight: 2.0,
            ),
            // V02: Alpine component has x-data for interactive behavior
            new EntityPattern(
                name: 'view.alpine_component',
                description: 'Interactive views use x-data for Alpine.js component state',
                target: 'blade_index',
                check: 'x-data',
                severity: 'warning',
                weight: 1.0,
            ),
            // V03: Views compose AICL library components (not raw HTML)
            new EntityPattern(
                name: 'view.component_composition',
                description: 'Views compose <x-aicl-*> components for reusable UI patterns',
                target: 'blade_show',
                check: '<x-aicl-',
                severity: 'warning',
                weight: 1.5,
            ),
            // V04: Tailwind classes use token-friendly values (not arbitrary)
            new EntityPattern(
                name: 'view.tailwind_tokens',
                description: 'Views use standard Tailwind classes, not arbitrary values like [#hex]',
                target: 'blade_show',
                check: '(bg-|text-|border-|rounded-)',
                severity: 'warning',
                weight: 1.0,
            ),
            // V05: Accessibility — semantic HTML and ARIA attributes present
            new EntityPattern(
                name: 'view.accessibility',
                description: 'Views use semantic HTML (main, section, article, nav) or ARIA attributes',
                target: 'blade_index',
                check: '(<main|<section|<article|<nav|aria-|role=)',
                severity: 'warning',
                weight: 1.5,
            ),
            // V06: Echo/Reverb binding for real-time updates
            new EntityPattern(
                name: 'view.echo_binding',
                description: 'Real-time views use Echo.channel() or wire:poll for live updates',
                target: 'blade_index',
                check: '(Echo\\.channel|wire:poll|x-init)',
                severity: 'info',
                weight: 0.5,
            ),
            // V07: View has a paired controller
            new EntityPattern(
                name: 'view.controller_pair',
                description: 'ViewController exists with index() and show() methods',
                target: 'view_controller',
                check: 'function (index|show)\\(',
                severity: 'error',
                weight: 2.0,
            ),
            // V08: Responsive layout classes in views
            new EntityPattern(
                name: 'view.responsive_layout',
                description: 'Views use responsive breakpoint classes (sm:, md:, lg:) for mobile-first layout',
                target: 'blade_index',
                check: '(sm:|md:|lg:)',
                severity: 'warning',
                weight: 1.0,
            ),
        ];
    }
}
