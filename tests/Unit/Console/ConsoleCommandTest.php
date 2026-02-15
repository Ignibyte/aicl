<?php

namespace Aicl\Tests\Unit\Console;

use Aicl\Console\Commands\DiscoverPatternsCommand;
use Aicl\Console\Commands\HubSeedCommand;
use Aicl\Console\Commands\InstallCommand;
use Aicl\Console\Commands\MakeEntityCommand;
use Aicl\Console\Commands\PipelineContextCommand;
use Aicl\Console\Commands\RemoveEntityCommand;
use Aicl\Console\Commands\RlmCommand;
use Aicl\Console\Commands\ScoutImportCommand;
use Aicl\Console\Commands\UpgradeCommand;
use Aicl\Console\Commands\ValidateEntityCommand;
use Aicl\Console\Support\BaseSchemaInspector;
use Aicl\Console\Support\FieldDefinition;
use Aicl\Console\Support\RelationshipDefinition;
use Illuminate\Console\Command;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConsoleCommandTest extends TestCase
{
    // ========================================================================
    // Data Provider Tests — All Commands Extend Command
    // ========================================================================

    #[DataProvider('commandProvider')]
    public function test_command_extends_base_class(string $commandClass): void
    {
        $this->assertTrue(is_subclass_of($commandClass, Command::class));
    }

    #[DataProvider('commandProvider')]
    public function test_command_has_handle_method(string $commandClass): void
    {
        $this->assertTrue(method_exists($commandClass, 'handle'));
    }

    #[DataProvider('commandWithSignatureProvider')]
    public function test_command_has_expected_signature(string $commandClass, string $expectedName): void
    {
        $reflection = new \ReflectionClass($commandClass);
        $defaults = $reflection->getDefaultProperties();

        $this->assertArrayHasKey('signature', $defaults);
        $this->assertStringContainsString($expectedName, $defaults['signature']);
    }

    #[DataProvider('commandWithSignatureProvider')]
    public function test_command_has_non_empty_description(string $commandClass): void
    {
        $reflection = new \ReflectionClass($commandClass);
        $defaults = $reflection->getDefaultProperties();

        $this->assertArrayHasKey('description', $defaults);
        $this->assertNotEmpty($defaults['description']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function commandProvider(): array
    {
        return [
            'InstallCommand' => [InstallCommand::class],
            'MakeEntityCommand' => [MakeEntityCommand::class],
            'RemoveEntityCommand' => [RemoveEntityCommand::class],
            'ValidateEntityCommand' => [ValidateEntityCommand::class],
            'RlmCommand' => [RlmCommand::class],
            'DiscoverPatternsCommand' => [DiscoverPatternsCommand::class],
            'PipelineContextCommand' => [PipelineContextCommand::class],
            'HubSeedCommand' => [HubSeedCommand::class],
            'ScoutImportCommand' => [ScoutImportCommand::class],
            'UpgradeCommand' => [UpgradeCommand::class],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function commandWithSignatureProvider(): array
    {
        return [
            'InstallCommand' => [InstallCommand::class, 'aicl:install'],
            'MakeEntityCommand' => [MakeEntityCommand::class, 'aicl:make-entity'],
            'RemoveEntityCommand' => [RemoveEntityCommand::class, 'aicl:remove-entity'],
            'ValidateEntityCommand' => [ValidateEntityCommand::class, 'aicl:validate'],
            'RlmCommand' => [RlmCommand::class, 'aicl:rlm'],
            'DiscoverPatternsCommand' => [DiscoverPatternsCommand::class, 'aicl:discover-patterns'],
            'PipelineContextCommand' => [PipelineContextCommand::class, 'aicl:pipeline-context'],
            'HubSeedCommand' => [HubSeedCommand::class, 'aicl:hub-seed'],
            'ScoutImportCommand' => [ScoutImportCommand::class, 'aicl:scout-import'],
            'UpgradeCommand' => [UpgradeCommand::class, 'aicl:upgrade'],
        ];
    }

    // ========================================================================
    // UpgradeCommand — Structure (not yet tested elsewhere)
    // ========================================================================

    public function test_upgrade_command_has_force_option(): void
    {
        $command = new UpgradeCommand;

        $this->assertTrue($command->getDefinition()->hasOption('force'));
    }

    public function test_upgrade_command_has_section_option(): void
    {
        $command = new UpgradeCommand;

        $this->assertTrue($command->getDefinition()->hasOption('section'));
    }

    public function test_upgrade_command_has_diff_option(): void
    {
        $command = new UpgradeCommand;

        $this->assertTrue($command->getDefinition()->hasOption('diff'));
    }

    public function test_upgrade_command_has_fresh_option(): void
    {
        $command = new UpgradeCommand;

        $this->assertTrue($command->getDefinition()->hasOption('fresh'));
    }

    public function test_upgrade_command_defines_build_initial_state(): void
    {
        $this->assertTrue(method_exists(UpgradeCommand::class, 'buildInitialState'));
    }

    public function test_upgrade_command_build_initial_state_is_static(): void
    {
        $reflection = new \ReflectionMethod(UpgradeCommand::class, 'buildInitialState');

        $this->assertTrue($reflection->isStatic());
    }

    public function test_upgrade_command_defines_process_section_method(): void
    {
        $reflection = new \ReflectionClass(UpgradeCommand::class);

        $this->assertTrue($reflection->hasMethod('processSection'));
    }

    public function test_upgrade_command_defines_handle_overwrite_method(): void
    {
        $reflection = new \ReflectionClass(UpgradeCommand::class);

        $this->assertTrue($reflection->hasMethod('handleOverwrite'));
    }

    public function test_upgrade_command_defines_handle_ensure_absent_method(): void
    {
        $reflection = new \ReflectionClass(UpgradeCommand::class);

        $this->assertTrue($reflection->hasMethod('handleEnsureAbsent'));
    }

    public function test_upgrade_command_defines_handle_ensure_present_method(): void
    {
        $reflection = new \ReflectionClass(UpgradeCommand::class);

        $this->assertTrue($reflection->hasMethod('handleEnsurePresent'));
    }

    // ========================================================================
    // RlmCommand — Options
    // ========================================================================

    public function test_rlm_command_has_action_argument(): void
    {
        $command = new RlmCommand;

        $this->assertTrue($command->getDefinition()->hasArgument('action'));
        $this->assertTrue($command->getDefinition()->getArgument('action')->isRequired());
    }

    public function test_rlm_command_has_query_argument(): void
    {
        $command = new RlmCommand;

        $this->assertTrue($command->getDefinition()->hasArgument('query'));
        $this->assertFalse($command->getDefinition()->getArgument('query')->isRequired());
    }

    public function test_rlm_command_has_agent_option(): void
    {
        $command = new RlmCommand;

        $this->assertTrue($command->getDefinition()->hasOption('agent'));
    }

    public function test_rlm_command_has_phase_option(): void
    {
        $command = new RlmCommand;

        $this->assertTrue($command->getDefinition()->hasOption('phase'));
    }

    public function test_rlm_command_has_entity_option(): void
    {
        $command = new RlmCommand;

        $this->assertTrue($command->getDefinition()->hasOption('entity'));
    }

    public function test_rlm_command_has_type_option(): void
    {
        $command = new RlmCommand;

        $this->assertTrue($command->getDefinition()->hasOption('type'));
    }

    // ========================================================================
    // DiscoverPatternsCommand — Options
    // ========================================================================

    public function test_discover_patterns_has_stale_option(): void
    {
        $command = new DiscoverPatternsCommand;

        $this->assertTrue($command->getDefinition()->hasOption('stale'));
    }

    public function test_discover_patterns_has_min_occurrences_option(): void
    {
        $command = new DiscoverPatternsCommand;

        $this->assertTrue($command->getDefinition()->hasOption('min-occurrences'));
    }

    public function test_discover_patterns_has_min_confidence_option(): void
    {
        $command = new DiscoverPatternsCommand;

        $this->assertTrue($command->getDefinition()->hasOption('min-confidence'));
    }

    public function test_discover_patterns_has_output_option(): void
    {
        $command = new DiscoverPatternsCommand;

        $this->assertTrue($command->getDefinition()->hasOption('output'));
    }

    // ========================================================================
    // MakeEntityCommand — Options
    // ========================================================================

    public function test_make_entity_has_fields_option(): void
    {
        $command = new MakeEntityCommand;

        $this->assertTrue($command->getDefinition()->hasOption('fields'));
    }

    public function test_make_entity_has_states_option(): void
    {
        $command = new MakeEntityCommand;

        $this->assertTrue($command->getDefinition()->hasOption('states'));
    }

    public function test_make_entity_has_relationships_option(): void
    {
        $command = new MakeEntityCommand;

        $this->assertTrue($command->getDefinition()->hasOption('relationships'));
    }

    public function test_make_entity_has_widgets_option(): void
    {
        $command = new MakeEntityCommand;

        $this->assertTrue($command->getDefinition()->hasOption('widgets'));
    }

    public function test_make_entity_has_notifications_option(): void
    {
        $command = new MakeEntityCommand;

        $this->assertTrue($command->getDefinition()->hasOption('notifications'));
    }

    public function test_make_entity_has_pdf_option(): void
    {
        $command = new MakeEntityCommand;

        $this->assertTrue($command->getDefinition()->hasOption('pdf'));
    }

    public function test_make_entity_has_all_option(): void
    {
        $command = new MakeEntityCommand;

        $this->assertTrue($command->getDefinition()->hasOption('all'));
    }

    public function test_make_entity_has_base_option(): void
    {
        $command = new MakeEntityCommand;

        $this->assertTrue($command->getDefinition()->hasOption('base'));
    }

    public function test_make_entity_has_ai_context_option(): void
    {
        $command = new MakeEntityCommand;

        $this->assertTrue($command->getDefinition()->hasOption('ai-context'));
    }

    // ========================================================================
    // PipelineContextCommand — Arguments and Options
    // ========================================================================

    public function test_pipeline_context_has_entity_argument(): void
    {
        $command = new PipelineContextCommand;

        $this->assertTrue($command->getDefinition()->hasArgument('entity'));
        $this->assertTrue($command->getDefinition()->getArgument('entity')->isRequired());
    }

    public function test_pipeline_context_has_phase_option(): void
    {
        $command = new PipelineContextCommand;

        $this->assertTrue($command->getDefinition()->hasOption('phase'));
    }

    public function test_pipeline_context_has_agent_option(): void
    {
        $command = new PipelineContextCommand;

        $this->assertTrue($command->getDefinition()->hasOption('agent'));
    }

    public function test_pipeline_context_has_header_option(): void
    {
        $command = new PipelineContextCommand;

        $this->assertTrue($command->getDefinition()->hasOption('header'));
    }

    // ========================================================================
    // FieldDefinition — Constructor and Properties
    // ========================================================================

    public function test_field_definition_constructor(): void
    {
        $field = new FieldDefinition(
            name: 'title',
            type: 'string',
            typeArgument: null,
            nullable: false,
            unique: false,
            default: null,
            indexed: false,
        );

        $this->assertEquals('title', $field->name);
        $this->assertEquals('string', $field->type);
        $this->assertNull($field->typeArgument);
        $this->assertFalse($field->nullable);
        $this->assertFalse($field->unique);
        $this->assertNull($field->default);
        $this->assertFalse($field->indexed);
    }

    public function test_field_definition_all_properties_settable(): void
    {
        $field = new FieldDefinition(
            name: 'category_id',
            type: 'foreignId',
            typeArgument: 'categories',
            nullable: true,
            unique: true,
            default: 'null',
            indexed: true,
        );

        $this->assertEquals('category_id', $field->name);
        $this->assertEquals('foreignId', $field->type);
        $this->assertEquals('categories', $field->typeArgument);
        $this->assertTrue($field->nullable);
        $this->assertTrue($field->unique);
        $this->assertEquals('null', $field->default);
        $this->assertTrue($field->indexed);
    }

    public function test_field_definition_is_foreign_key(): void
    {
        $fk = new FieldDefinition('user_id', 'foreignId', 'users', false, false, null, false);
        $nonFk = new FieldDefinition('name', 'string', null, false, false, null, false);

        $this->assertTrue($fk->isForeignKey());
        $this->assertFalse($nonFk->isForeignKey());
    }

    public function test_field_definition_is_enum(): void
    {
        $enum = new FieldDefinition('status', 'enum', null, false, false, null, false);
        $nonEnum = new FieldDefinition('name', 'string', null, false, false, null, false);

        $this->assertTrue($enum->isEnum());
        $this->assertFalse($nonEnum->isEnum());
    }

    public function test_field_definition_relationship_method_name(): void
    {
        $field = new FieldDefinition('category_id', 'foreignId', 'categories', false, false, null, false);

        $this->assertEquals('category', $field->relationshipMethodName());
    }

    public function test_field_definition_relationship_method_name_without_id_suffix(): void
    {
        $field = new FieldDefinition('assigned_to', 'foreignId', 'users', false, false, null, false);

        $this->assertEquals('assignedTo', $field->relationshipMethodName());
    }

    public function test_field_definition_relationship_method_name_null_for_non_fk(): void
    {
        $field = new FieldDefinition('name', 'string', null, false, false, null, false);

        $this->assertNull($field->relationshipMethodName());
    }

    public function test_field_definition_related_model_name(): void
    {
        $field = new FieldDefinition('category_id', 'foreignId', 'categories', false, false, null, false);

        $this->assertEquals('Category', $field->relatedModelName());
    }

    public function test_field_definition_related_model_name_from_users(): void
    {
        $field = new FieldDefinition('owner_id', 'foreignId', 'users', false, false, null, false);

        $this->assertEquals('User', $field->relatedModelName());
    }

    public function test_field_definition_related_model_name_null_for_non_fk(): void
    {
        $field = new FieldDefinition('name', 'string', null, false, false, null, false);

        $this->assertNull($field->relatedModelName());
    }

    public function test_field_definition_related_model_name_null_when_no_type_argument(): void
    {
        $field = new FieldDefinition('user_id', 'foreignId', null, false, false, null, false);

        $this->assertNull($field->relatedModelName());
    }

    public function test_field_definition_from_base_schema(): void
    {
        $column = [
            'name' => 'title',
            'type' => 'string',
        ];

        $field = FieldDefinition::fromBaseSchema($column);

        $this->assertEquals('title', $field->name);
        $this->assertEquals('string', $field->type);
        $this->assertFalse($field->nullable);
    }

    public function test_field_definition_from_base_schema_with_modifiers(): void
    {
        $column = [
            'name' => 'description',
            'type' => 'text',
            'modifiers' => ['nullable', 'index'],
        ];

        $field = FieldDefinition::fromBaseSchema($column);

        $this->assertTrue($field->nullable);
        $this->assertTrue($field->indexed);
    }

    public function test_field_definition_from_base_schema_with_default(): void
    {
        $column = [
            'name' => 'priority',
            'type' => 'integer',
            'modifiers' => ['default(0)'],
        ];

        $field = FieldDefinition::fromBaseSchema($column);

        $this->assertEquals('0', $field->default);
    }

    public function test_field_definition_from_base_schema_boolean_defaults_to_true(): void
    {
        $column = [
            'name' => 'is_active',
            'type' => 'boolean',
        ];

        $field = FieldDefinition::fromBaseSchema($column);

        $this->assertEquals('true', $field->default);
    }

    public function test_field_definition_from_base_schema_text_auto_nullable(): void
    {
        $column = [
            'name' => 'notes',
            'type' => 'text',
        ];

        $field = FieldDefinition::fromBaseSchema($column);

        $this->assertTrue($field->nullable);
    }

    public function test_field_definition_from_base_schema_date_auto_nullable(): void
    {
        $column = [
            'name' => 'due_date',
            'type' => 'date',
        ];

        $field = FieldDefinition::fromBaseSchema($column);

        $this->assertTrue($field->nullable);
    }

    public function test_field_definition_from_base_schema_datetime_auto_nullable(): void
    {
        $column = [
            'name' => 'completed_at',
            'type' => 'datetime',
        ];

        $field = FieldDefinition::fromBaseSchema($column);

        $this->assertTrue($field->nullable);
    }

    public function test_field_definition_from_base_schema_json_auto_nullable(): void
    {
        $column = [
            'name' => 'metadata',
            'type' => 'json',
        ];

        $field = FieldDefinition::fromBaseSchema($column);

        $this->assertTrue($field->nullable);
    }

    public function test_field_definition_from_base_schema_with_unique(): void
    {
        $column = [
            'name' => 'code',
            'type' => 'string',
            'modifiers' => ['unique'],
        ];

        $field = FieldDefinition::fromBaseSchema($column);

        $this->assertTrue($field->unique);
    }

    public function test_field_definition_from_base_schema_with_argument(): void
    {
        $column = [
            'name' => 'user_id',
            'type' => 'foreignId',
            'argument' => 'users',
        ];

        $field = FieldDefinition::fromBaseSchema($column);

        $this->assertEquals('users', $field->typeArgument);
    }

    // ========================================================================
    // RelationshipDefinition — Constructor and Methods
    // ========================================================================

    public function test_relationship_definition_constructor(): void
    {
        $rel = new RelationshipDefinition(
            name: 'tasks',
            type: 'hasMany',
            relatedModel: 'Task',
            foreignKey: null,
        );

        $this->assertEquals('tasks', $rel->name);
        $this->assertEquals('hasMany', $rel->type);
        $this->assertEquals('Task', $rel->relatedModel);
        $this->assertNull($rel->foreignKey);
    }

    public function test_relationship_definition_with_foreign_key(): void
    {
        $rel = new RelationshipDefinition(
            name: 'tasks',
            type: 'hasMany',
            relatedModel: 'Task',
            foreignKey: 'project_id',
        );

        $this->assertEquals('project_id', $rel->foreignKey);
    }

    #[DataProvider('eloquentTypeProvider')]
    public function test_relationship_definition_eloquent_type(string $type, string $expectedType): void
    {
        $rel = new RelationshipDefinition('items', $type, 'Item', null);

        $this->assertEquals($expectedType, $rel->eloquentType());
    }

    #[DataProvider('eloquentTypeProvider')]
    public function test_relationship_definition_eloquent_import(string $type, string $expectedType): void
    {
        $rel = new RelationshipDefinition('items', $type, 'Item', null);

        $this->assertEquals(
            "Illuminate\\Database\\Eloquent\\Relations\\{$expectedType}",
            $rel->eloquentImport()
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function eloquentTypeProvider(): array
    {
        return [
            'hasMany' => ['hasMany', 'HasMany'],
            'hasOne' => ['hasOne', 'HasOne'],
            'belongsToMany' => ['belongsToMany', 'BelongsToMany'],
            'morphMany' => ['morphMany', 'MorphMany'],
            'unknown defaults to HasMany' => ['unknownType', 'HasMany'],
        ];
    }

    // ========================================================================
    // BaseSchemaInspector — Structure
    // ========================================================================

    public function test_base_schema_inspector_constructor(): void
    {
        $inspector = new BaseSchemaInspector('App\\Models\\SomeModel');

        $this->assertInstanceOf(BaseSchemaInspector::class, $inspector);
    }

    public function test_base_schema_inspector_has_validate_method(): void
    {
        $this->assertTrue(method_exists(BaseSchemaInspector::class, 'validate'));
    }

    public function test_base_schema_inspector_has_columns_method(): void
    {
        $this->assertTrue(method_exists(BaseSchemaInspector::class, 'columns'));
    }

    public function test_base_schema_inspector_has_traits_method(): void
    {
        $this->assertTrue(method_exists(BaseSchemaInspector::class, 'traits'));
    }

    public function test_base_schema_inspector_has_contracts_method(): void
    {
        $this->assertTrue(method_exists(BaseSchemaInspector::class, 'contracts'));
    }

    public function test_base_schema_inspector_has_fillable_method(): void
    {
        $this->assertTrue(method_exists(BaseSchemaInspector::class, 'fillable'));
    }

    public function test_base_schema_inspector_has_casts_method(): void
    {
        $this->assertTrue(method_exists(BaseSchemaInspector::class, 'casts'));
    }

    public function test_base_schema_inspector_has_relationships_method(): void
    {
        $this->assertTrue(method_exists(BaseSchemaInspector::class, 'relationships'));
    }

    public function test_base_schema_inspector_has_column_method(): void
    {
        $this->assertTrue(method_exists(BaseSchemaInspector::class, 'hasColumn'));
    }

    public function test_base_schema_inspector_has_trait_method(): void
    {
        $this->assertTrue(method_exists(BaseSchemaInspector::class, 'hasTrait'));
    }

    public function test_base_schema_inspector_has_column_type_method(): void
    {
        $this->assertTrue(method_exists(BaseSchemaInspector::class, 'columnType'));
    }

    public function test_base_schema_inspector_short_class_name(): void
    {
        $inspector = new BaseSchemaInspector('App\\Models\\Base\\BaseNetworkDevice');

        $this->assertEquals('BaseNetworkDevice', $inspector->shortClassName());
    }

    public function test_base_schema_inspector_full_class_name(): void
    {
        $inspector = new BaseSchemaInspector('App\\Models\\Base\\BaseNetworkDevice');

        $this->assertEquals('App\\Models\\Base\\BaseNetworkDevice', $inspector->fullClassName());
    }

    public function test_base_schema_inspector_validate_throws_for_nonexistent_class(): void
    {
        $inspector = new BaseSchemaInspector('NonExistent\\Model\\Class');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $inspector->validate();
    }
}
