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
        );

        if ($entityName !== null) {
            $patterns = array_merge($patterns, static::registrationPatterns($entityName));
        }

        return $patterns;
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
                name: 'test.refresh_database',
                description: 'Test must use RefreshDatabase trait',
                target: 'test',
                check: 'use RefreshDatabase;',
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
}
