<?php

namespace Aicl\Console\Commands;

use Aicl\Console\Generators\BroadcastEventGenerator;
use Aicl\Console\Generators\EnumGenerator;
use Aicl\Console\Generators\MigrationGenerator;
use Aicl\Console\Generators\PolicyGenerator;
use Aicl\Console\Generators\StateMachineGenerator;
use Aicl\Console\Generators\ViewGenerator;
use Aicl\Console\Support\BaseSchemaInspector;
use Aicl\Console\Support\EntityGeneratorContext;
use Aicl\Console\Support\EntitySpec;
use Aicl\Console\Support\FieldDefinition;
use Aicl\Console\Support\FieldParser;
use Aicl\Console\Support\NotificationSpec;
use Aicl\Console\Support\NotificationTemplateResolver;
use Aicl\Console\Support\ObserverRuleSpec;
use Aicl\Console\Support\RelationshipDefinition;
use Aicl\Console\Support\RelationshipParser;
use Aicl\Console\Support\ReportColumnSpec;
use Aicl\Console\Support\ReportFieldSpec;
use Aicl\Console\Support\ReportSectionSpec;
use Aicl\Console\Support\SpecFileParser;
use Aicl\Console\Support\WidgetQueryParser;
use Aicl\Console\Support\WidgetSpec;
use Aicl\Support\RlmBridge;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

class MakeEntityCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'aicl:make-entity
        {name? : The name of the entity (e.g., Task, Invoice)}
        {--fields= : Field definitions (name:type[:modifier], comma-separated)}
        {--states= : State machine states (comma-separated, first is default)}
        {--relationships= : Non-FK relationships (name:type:Model[:foreign_key], comma-separated)}
        {--traits=* : Override default trait selection}
        {--widgets : Generate widget stubs}
        {--notifications : Generate notification stubs}
        {--pdf : Generate PDF template stubs}
        {--ai-context : Include HasAiContext trait with field-aware override}
        {--base= : Base class to extend (must implement DeclaresBaseSchema)}
        {--views : Generate public Blade views (index, show, card, filters, ViewController, web routes)}
        {--all : Shorthand for --widgets --notifications --pdf --ai-context}
        {--from-spec : Generate from spec file (default: specs/{Name}.entity.md)}
        {--spec-path= : Explicit path to spec file (alternative to --from-spec)}
        {--cleanup : Run Pint on all generated files after scaffolding}';

    /**
     * @var string
     */
    protected $description = 'Scaffold a new AICL entity with model, migration, factory, policy, observer, and Filament resource.';

    /**
     * @var array<string, string>
     */
    protected array $stubs = [];

    /**
     * Parsed field definitions (null = legacy mode).
     *
     * @var array<int, FieldDefinition>|null
     */
    protected ?array $fields = null;

    /**
     * Parsed relationship definitions.
     *
     * @var array<int, RelationshipDefinition>
     */
    protected array $relationships = [];

    /**
     * Parsed state names (empty = no state machine).
     *
     * @var array<int, string>
     */
    protected array $states = [];

    protected bool $smartMode = false;

    protected ?BaseSchemaInspector $baseInspector = null;

    protected ?EntitySpec $entitySpec = null;

    /** @var \Rlm\EntitySignature|null */
    protected ?object $entitySignature = null;

    /**
     * Rich enum definitions from spec file.
     *
     * @var array<string, array<int, array{case: string, label: string, color?: string, icon?: string}>>
     */
    protected array $specEnums = [];

    public function handle(): int
    {
        // Handle --from-spec mode first
        if ($this->option('from-spec') || $this->option('spec-path') !== null) {
            return $this->handleFromSpec();
        }

        $name = $this->argument('name') ?? text(
            label: 'What is the entity name?',
            placeholder: 'e.g., Task, Invoice, Customer',
            required: true,
            validate: function (string $value): ?string {
                if (! preg_match('/^[A-Z][a-zA-Z]+$/', $value)) {
                    return 'Entity name must be PascalCase (e.g., Task, ProjectTask).';
                }

                return null;
            }
        );

        $name = Str::studly($name);
        $tableName = Str::snake(Str::pluralStudly($name));

        // Parse smart scaffolder options (fail-fast before any file writes)
        if (! $this->parseSmartOptions($name, $tableName)) {
            return self::FAILURE;
        }

        // Build entity feature signature from parsed options (requires RLM)
        if (RlmBridge::installed()) {
            $this->entitySignature = $this->buildEntitySignature($name);
        }

        $traits = $this->selectTraits();
        $generateFilament = $this->shouldGenerateFilament();
        $generateApi = $this->shouldGenerateApi();

        // Resolve --all flag
        $generateWidgets = $this->option('widgets') || $this->option('all');
        $generateNotifications = $this->option('notifications') || $this->option('all');
        $generatePdf = $this->option('pdf') || $this->option('all');
        $generateAiContext = $this->option('ai-context') || $this->option('all');
        $generateViews = (bool) $this->option('views');

        $modeLabel = $this->smartMode ? ' (smart mode)' : '';
        $baseLabel = $this->baseInspector !== null ? " extends {$this->baseInspector->shortClassName()}" : '';
        $this->components->info("Scaffolding entity: {$name}{$baseLabel}{$modeLabel}");
        $this->newLine();

        $files = $this->scaffoldEntityFiles(
            name: $name,
            tableName: $tableName,
            traits: $traits,
            generateAiContext: $generateAiContext,
            generateFilament: $generateFilament,
            generateApi: $generateApi,
            generateWidgets: $generateWidgets && $this->smartMode,
            generateNotifications: $generateNotifications && $this->smartMode,
            generatePdf: $generatePdf && $this->smartMode,
            generateEnums: $this->smartMode,
            generateViews: $generateViews && $this->smartMode,
        );

        // Cleanup: run Pint on generated files
        if ($this->option('cleanup')) {
            $this->runCleanup($files);
        }

        $this->newLine();
        $this->components->info("Entity {$name} scaffolded successfully!");
        $this->newLine();

        $this->components->bulletList($files);

        $this->newLine();
        $this->components->warn('Next steps:');

        if ($this->smartMode) {
            $this->components->bulletList([
                'Run: php artisan migrate',
                "Customize business logic in {$name} model and observer",
                'Customize widget queries in app/Filament/Widgets/',
                'Run: php artisan test --filter='.$name.'Test',
                'Run: php artisan aicl:validate '.$name,
            ]);
        } else {
            $this->components->bulletList([
                "Edit the migration to add {$name}-specific columns",
                'Run: php artisan migrate',
                "Edit the model to configure casts and relationships for {$name}",
                'Edit the factory with meaningful test data',
                "Update the Filament Resource form and table schemas for {$name}",
                'Run: php artisan test --filter='.$name.'Test',
            ]);
        }

        \Aicl\Services\EntityRegistry::flush();

        return self::SUCCESS;
    }

    /**
     * Handle entity generation from a .entity.md spec file.
     */
    protected function handleFromSpec(): int
    {
        // Reject conflicting CLI flags
        $conflicting = ['fields', 'states', 'relationships', 'base'];

        foreach ($conflicting as $flag) {
            if ($this->option($flag) !== null) {
                $this->components->error("Cannot use --{$flag} with --from-spec. The spec file defines all entity properties.");

                return self::FAILURE;
            }
        }

        // Resolve spec file path
        $specPath = $this->option('spec-path');
        $name = $this->argument('name');

        if ($specPath === null || $specPath === '') {
            // Default path: specs/{Name}.entity.md
            if ($name === null) {
                $this->components->error('Provide the entity name or use --spec-path=path/to/file.entity.md');

                return self::FAILURE;
            }

            $specPath = base_path("specs/{$name}.entity.md");
        }

        // Parse the spec file
        $parser = new SpecFileParser;

        try {
            $spec = $parser->parse($specPath);
        } catch (InvalidArgumentException $e) {
            $this->components->error("Spec file error: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->entitySpec = $spec;

        // Use spec name if no argument was given
        if ($name === null) {
            $name = $spec->name;
        }

        $name = Str::studly($name);
        $tableName = Str::snake(Str::pluralStudly($name));

        // Transfer spec data to internal command state
        $this->smartMode = true;
        $this->fields = $spec->fields;
        $this->relationships = $spec->relationships;
        $this->specEnums = $spec->enums;

        // Ensure default state is first in the array (state machine generator uses [0] as default)
        $states = $spec->states;
        if (! empty($states) && $spec->defaultState !== '' && $states[0] !== $spec->defaultState) {
            $states = array_values(array_diff($states, [$spec->defaultState]));
            array_unshift($states, $spec->defaultState);
        }
        $this->states = $states;

        // Handle --base from spec
        if ($spec->baseClass !== null) {
            try {
                $this->baseInspector = new BaseSchemaInspector($spec->baseClass);
                $this->baseInspector->validate();
            } catch (InvalidArgumentException $e) {
                $this->components->error("Base class error: {$e->getMessage()}");

                return self::FAILURE;
            }

            // Deduplicate fields from base schema
            $errors = [];
            $this->fields = $this->deduplicateBaseFields($this->fields, $errors);

            if (! empty($errors)) {
                foreach ($errors as $error) {
                    $this->components->error($error);
                }

                return self::FAILURE;
            }
        }

        // Status field / --states conflict
        if (! empty($this->states)) {
            $this->fields = array_values(array_filter(
                $this->fields,
                function (FieldDefinition $field): bool {
                    if ($field->name === 'status' && ($field->isEnum() || $field->type === 'string')) {
                        $this->components->warn("Field 'status' conflicts with states. State machine takes precedence.");

                        return false;
                    }

                    return true;
                }
            ));
        }

        // Resolve traits from spec
        $traits = ! empty($spec->traits)
            ? $spec->traits
            : ['HasEntityEvents', 'HasAuditTrail', 'HasStandardScopes'];

        // Resolve generation flags from spec options
        $generateFilament = $spec->wantsFilament();
        $generateApi = $spec->wantsApi();
        $generateWidgets = $spec->wantsWidgets();
        $generateNotifications = $spec->wantsNotifications();
        $generatePdf = $spec->wantsPdf();
        $generateAiContext = $spec->wantsAiContext();

        $baseLabel = $this->baseInspector !== null ? " extends {$this->baseInspector->shortClassName()}" : '';
        $this->components->info("Scaffolding entity from spec: {$name}{$baseLabel}");
        $this->components->info("Description: {$spec->description}");
        $this->newLine();

        $generateViews = $spec->wantsViews() || (bool) $this->option('views');

        // Build entity feature signature from spec-parsed options (requires RLM)
        if (RlmBridge::installed()) {
            $this->entitySignature = $this->buildEntitySignature($name);
        }

        $files = $this->scaffoldEntityFiles(
            name: $name,
            tableName: $tableName,
            traits: $traits,
            generateAiContext: $generateAiContext,
            generateFilament: $generateFilament,
            generateApi: $generateApi,
            generateWidgets: $generateWidgets,
            generateNotifications: $generateNotifications,
            generatePdf: $generatePdf,
            generateEnums: true,
            generateViews: $generateViews,
        );

        // Cleanup: run Pint on generated files
        if ($this->option('cleanup')) {
            $this->runCleanup($files);
        }

        $this->newLine();
        $this->components->info("Entity {$name} scaffolded from spec successfully!");
        $this->newLine();

        $this->components->bulletList($files);

        $this->newLine();
        $this->components->warn('Next steps:');
        $this->components->bulletList([
            'Run: php artisan migrate',
            "Customize business logic in {$name} model and observer",
            'Run: php artisan test --filter='.$name.'Test',
            'Run: php artisan aicl:validate '.$name,
        ]);

        \Aicl\Services\EntityRegistry::flush();

        return self::SUCCESS;
    }

    /**
     * Build an EntityGeneratorContext DTO from current command state.
     *
     * @param  array<int, string>  $traits
     */
    protected function buildContext(
        string $name,
        string $tableName,
        array $traits,
        bool $generateAiContext,
        bool $generateFilament,
        bool $generateApi,
        bool $generateWidgets,
        bool $generateNotifications,
        bool $generatePdf,
    ): EntityGeneratorContext {
        return new EntityGeneratorContext(
            name: $name,
            tableName: $tableName,
            fields: $this->fields,
            states: $this->states,
            relationships: $this->relationships,
            traits: $traits,
            smartMode: $this->smartMode,
            baseInspector: $this->baseInspector,
            entitySpec: $this->entitySpec,
            specEnums: $this->specEnums,
            generateFilament: $generateFilament,
            generateApi: $generateApi,
            generateWidgets: $generateWidgets,
            generateNotifications: $generateNotifications,
            generatePdf: $generatePdf,
            generateAiContext: $generateAiContext,
        );
    }

    /**
     * Scaffold all entity files (enums, states, model, migration, factory, seeder, policy, observer, etc.).
     *
     * Centralizes the file-generation loop used by both interactive and spec-based scaffolding paths.
     * Uses extracted generator classes where available, falls back to internal methods for the rest.
     *
     * @param  array<int, string>  $traits
     * @return array<int, string> List of generated file paths (relative to base_path)
     */
    protected function scaffoldEntityFiles(
        string $name,
        string $tableName,
        array $traits,
        bool $generateAiContext,
        bool $generateFilament,
        bool $generateApi,
        bool $generateWidgets,
        bool $generateNotifications,
        bool $generatePdf,
        bool $generateEnums,
        bool $generateViews = false,
    ): array {
        $files = [];

        $ctx = $this->buildContext(
            $name, $tableName, $traits, $generateAiContext,
            $generateFilament, $generateApi, $generateWidgets,
            $generateNotifications, $generatePdf,
        );

        // Enum generation (before model, so model can reference enum)
        if ($generateEnums && $ctx->hasEnums()) {
            $gen = new EnumGenerator($ctx);
            $this->components->task($gen->label(), function () use ($gen, &$files): void {
                $files = array_merge($files, $gen->generate());
            });
        }

        // State machine generation (before model)
        if ($ctx->hasStates()) {
            $gen = new StateMachineGenerator($ctx);
            $this->components->task($gen->label(), function () use ($gen, &$files): void {
                $files = array_merge($files, $gen->generate());
            });
        }

        // Model (not yet extracted — uses internal method)
        $this->components->task("Creating model: {$name}", function () use ($name, $tableName, $traits, $generateAiContext, &$files): void {
            $files[] = $this->generateModel($name, $tableName, $traits, $generateAiContext);
        });

        // Migration
        $migrationGen = new MigrationGenerator($ctx);
        $this->components->task($migrationGen->label(), function () use ($migrationGen, &$files): void {
            $files = array_merge($files, $migrationGen->generate());
        });

        // Factory (not yet extracted — uses internal method)
        $this->components->task("Creating factory: {$name}Factory", function () use ($name, &$files): void {
            $files[] = $this->generateFactory($name);
        });

        // Seeder (not yet extracted — uses internal method)
        $this->components->task("Creating seeder: {$name}Seeder", function () use ($name, &$files): void {
            $files[] = $this->generateSeeder($name);
        });

        // Policy
        $policyGen = new PolicyGenerator($ctx);
        $this->components->task($policyGen->label(), function () use ($policyGen, &$files): void {
            $files = array_merge($files, $policyGen->generate());
        });

        // Observer (not yet extracted — uses internal method)
        $this->components->task("Creating observer: {$name}Observer", function () use ($name, &$files): void {
            $files[] = $this->generateObserver($name);
        });

        // Broadcast Events
        $broadcastGen = new BroadcastEventGenerator($ctx);
        $this->components->task($broadcastGen->label(), function () use ($broadcastGen, &$files): void {
            $files = array_merge($files, $broadcastGen->generate());
        });

        // Filament Resource (not yet extracted — uses internal method)
        if ($generateFilament) {
            $this->components->task("Creating Filament resource: {$name}Resource", function () use ($name, $traits, &$files): void {
                $files = array_merge($files, $this->generateFilamentResource($name, $traits));
            });

            $this->components->task("Creating exporter: {$name}Exporter", function () use ($name, &$files): void {
                $files[] = $this->generateExporter($name);
            });
        }

        // API layer (not yet extracted — uses internal method)
        if ($generateApi) {
            $this->components->task("Creating API controller: {$name}Controller", function () use ($name, $tableName, &$files): void {
                $files = array_merge($files, $this->generateApiLayer($name, $tableName));
            });
        }

        // Test (not yet extracted — uses internal method)
        $this->components->task("Creating test: {$name}Test", function () use ($name, $traits, &$files): void {
            $files[] = $this->generateTest($name, $traits);
        });

        // Widget stubs (not yet extracted — uses internal method)
        if ($generateWidgets) {
            $this->components->task("Creating widgets for: {$name}", function () use ($name, &$files): void {
                $files = array_merge($files, $this->generateWidgets($name));
            });
        }

        // Notification stubs (not yet extracted — uses internal method)
        if ($generateNotifications) {
            $this->components->task("Creating notifications for: {$name}", function () use ($name, &$files): void {
                $files = array_merge($files, $this->generateNotifications($name));
            });
        }

        // PDF stubs (not yet extracted — uses internal method)
        if ($generatePdf) {
            $this->components->task("Creating PDF templates for: {$name}", function () use ($name, &$files): void {
                $files = array_merge($files, $this->generatePdfTemplates($name));
            });
        }

        // Public Blade views (registry-driven)
        if ($generateViews) {
            $viewGen = new ViewGenerator($ctx);
            $this->components->task($viewGen->label(), function () use ($viewGen, &$files): void {
                $files = array_merge($files, $viewGen->generate());
            });
        }

        return $files;
    }

    /**
     * Run Pint formatting on generated PHP files.
     *
     * @param  array<int, string>  $files  Relative file paths from scaffolding
     */
    protected function runCleanup(array $files): void
    {
        $phpFiles = [];

        foreach ($files as $file) {
            $absolute = base_path($file);

            if (str_ends_with($file, '.php') && file_exists($absolute)) {
                $phpFiles[] = $absolute;
            }
        }

        if (empty($phpFiles)) {
            return;
        }

        $this->newLine();
        $this->components->task('Running Pint on generated files', function () use ($phpFiles): void {
            $pintBin = base_path('vendor/bin/pint');

            if (! file_exists($pintBin)) {
                $this->components->warn('Pint not found at vendor/bin/pint — skipping cleanup.');

                return;
            }

            $process = new \Symfony\Component\Process\Process(
                array_merge([$pintBin, '--quiet'], $phpFiles),
                base_path()
            );

            $process->setTimeout(60);
            $process->run();
        });
    }

    /**
     * Parse --fields, --states, --relationships, --base options. Returns false on error.
     */
    protected function parseSmartOptions(string $name, string $tableName): bool
    {
        $fieldsOption = $this->option('fields');
        $statesOption = $this->option('states');
        $relationshipsOption = $this->option('relationships');
        $baseOption = $this->option('base');

        // --base enables smart mode even without --fields
        if ($fieldsOption === null && $statesOption === null && $relationshipsOption === null && $baseOption === null) {
            return true;
        }

        $this->smartMode = true;
        $errors = [];

        // Validate --base first (fail-fast)
        if ($baseOption !== null) {
            try {
                $this->baseInspector = new BaseSchemaInspector($baseOption);
                $this->baseInspector->validate();
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Parse fields
        if ($fieldsOption !== null) {
            try {
                $this->fields = (new FieldParser)->parse($fieldsOption);
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        } else {
            $this->fields = [];
        }

        // Parse states
        if ($statesOption !== null) {
            $stateNames = array_map('trim', explode(',', $statesOption));
            $stateNames = array_filter($stateNames, fn (string $s): bool => $s !== '');

            if (empty($stateNames)) {
                $errors[] = '--states requires at least one state name.';
            } else {
                foreach ($stateNames as $state) {
                    if (! preg_match('/^[a-z][a-z0-9_]*$/', $state)) {
                        $errors[] = "State name '{$state}' must be snake_case.";
                    }
                }
                $this->states = $stateNames;
            }
        }

        // Parse relationships
        if ($relationshipsOption !== null) {
            try {
                $this->relationships = (new RelationshipParser)->parse($relationshipsOption);
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Base schema field deduplication
        if ($this->baseInspector !== null && $this->fields !== null && empty($errors)) {
            $this->fields = $this->deduplicateBaseFields($this->fields, $errors);
        }

        // Base schema + --states conflict check
        if ($this->baseInspector !== null && ! empty($this->states) && $this->baseInspector->hasColumn('status')) {
            $errors[] = "Cannot use --states when base class already declares 'status' column.";
        }

        // Conflict resolution: status field + --states
        if (! empty($this->states) && $this->fields !== null) {
            foreach ($this->fields as $key => $field) {
                if ($field->name === 'status' && ($field->isEnum() || $field->type === 'string')) {
                    $this->components->warn("Field 'status' conflicts with --states. State machine takes precedence.");
                    unset($this->fields[$key]);
                    $this->fields = array_values($this->fields);

                    break;
                }
            }
        }

        if (! empty($errors)) {
            foreach ($errors as $error) {
                $this->components->error($error);
            }

            return false;
        }

        return true;
    }

    /**
     * Build an EntitySignature from the current parsed command state.
     *
     * @return \Rlm\EntitySignature
     */
    protected function buildEntitySignature(string $name): object
    {
        $fields = [];
        if ($this->fields) {
            foreach ($this->fields as $field) {
                $fields[$field->name] = $field->type;
            }
        }

        $states = $this->states ?? [];

        $relationships = [];
        if ($this->relationships) {
            foreach ($this->relationships as $rel) {
                $relationships[] = "{$rel->type}:{$rel->relatedModel}";
            }
        }

        $features = [];
        if ($this->option('widgets') || $this->option('all')) {
            $features[] = 'widgets';
        }
        if ($this->option('notifications') || $this->option('all')) {
            $features[] = 'notifications';
        }
        if ($this->option('pdf') || $this->option('all')) {
            $features[] = 'pdf';
        }
        if ($this->option('ai-context') || $this->option('all')) {
            $features[] = 'ai_context';
        }
        if ($this->option('views')) {
            $features[] = 'views';
        }

        return new \Rlm\EntitySignature(
            entityName: $name,
            fields: $fields,
            states: $states,
            relationships: $relationships,
            features: $features,
        );
    }

    /**
     * Get the entity signature built during scaffolding.
     *
     * @return \Rlm\EntitySignature|null
     */
    public function getEntitySignature(): ?object
    {
        return $this->entitySignature;
    }

    /**
     * Remove fields that are already declared by the base class schema.
     *
     * @param  array<int, FieldDefinition>  $fields
     * @param  array<int, string>  $errors
     * @return array<int, FieldDefinition>
     */
    protected function deduplicateBaseFields(array $fields, array &$errors): array
    {
        $filtered = [];

        foreach ($fields as $field) {
            if (! $this->baseInspector->hasColumn($field->name)) {
                $filtered[] = $field;

                continue;
            }

            $baseType = $this->baseInspector->columnType($field->name);

            if ($baseType !== $field->type) {
                $errors[] = "Field '{$field->name}' defined as '{$field->type}' in --fields but '{$baseType}' in base class. Remove it from --fields or change the base class.";

                continue;
            }

            $this->components->warn("Field '{$field->name}' already defined by base class, skipping.");
        }

        return $filtered;
    }

    /**
     * @return array<int, string>
     */
    protected function selectTraits(): array
    {
        $explicitTraits = $this->option('traits');

        if (! empty($explicitTraits)) {
            $traits = $explicitTraits;
        } elseif ($this->option('no-interaction')) {
            $traits = ['HasEntityEvents', 'HasAuditTrail', 'HasStandardScopes'];
        } else {
            $traits = multiselect(
                label: 'Which traits should the entity use?',
                options: [
                    'HasEntityEvents' => 'HasEntityEvents — Lifecycle event dispatching',
                    'HasAuditTrail' => 'HasAuditTrail — Activity logging (who changed what when)',
                    'HasStandardScopes' => 'HasStandardScopes — active/inactive/recent/search scopes',
                    'HasTagging' => 'HasTagging — Polymorphic tagging system',
                    'HasSearchableFields' => 'HasSearchableFields — Full-text search via Scout',
                ],
                default: ['HasEntityEvents', 'HasAuditTrail', 'HasStandardScopes'],
            );
        }

        return $traits;
    }

    protected function shouldGenerateFilament(): bool
    {
        if ($this->option('no-interaction')) {
            return true;
        }

        return confirm(
            label: 'Generate Filament admin resource?',
            default: true,
        );
    }

    protected function shouldGenerateApi(): bool
    {
        if ($this->option('no-interaction')) {
            return true;
        }

        return confirm(
            label: 'Generate API controller and routes?',
            default: false,
        );
    }

    protected function generateModel(string $name, string $tableName, array $traits, bool $aiContext = false): string
    {
        if ($this->smartMode) {
            return $this->generateSmartModel($name, $tableName, $traits, $aiContext);
        }

        return $this->generateLegacyModel($name, $tableName, $traits, $aiContext);
    }

    protected function generateLegacyModel(string $name, string $tableName, array $traits, bool $aiContext = false): string
    {
        ['traitImports' => $traitImports, 'traitUses' => $traitUses, 'interfaces' => $interfaces, 'interfaceImports' => $interfaceImports] =
            $this->resolveTraitsAndInterfaces($traits, $aiContext);

        $implementsStr = ! empty($interfaces) ? ' implements '.implode(', ', $interfaces) : '';
        $importsStr = implode("\n", array_merge($interfaceImports, $traitImports));
        $traitsStr = implode("\n", $traitUses);

        // Determine extends clause and model import
        $baseSoftDeletes = $this->baseInspector !== null && $this->baseInspector->hasTrait('SoftDeletes');
        $softDeletesUse = $baseSoftDeletes ? '' : "\n    use SoftDeletes;";
        $softDeletesImport = $baseSoftDeletes ? '' : "\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;";

        if ($this->baseInspector !== null) {
            $extendsClass = $this->baseInspector->shortClassName();
            $modelImport = "use {$this->baseInspector->fullClassName()};";
        } else {
            $extendsClass = 'Model';
            $modelImport = 'use Illuminate\\Database\\Eloquent\\Model;';
        }

        $hasStandardScopes = in_array('HasStandardScopes', $traits);
        $searchableColumnsMethod = $hasStandardScopes ? <<<'SEARCH'

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name'];
    }
SEARCH : '';

        $aiContextMethod = $aiContext ? <<<'AICONTEXT'


    /**
     * @return array<int, string>
     */
    protected function aiContextFields(): array
    {
        return ['name', 'description', 'is_active', 'owner_id'];
    }
AICONTEXT : '';

        $content = <<<PHP
<?php

namespace App\\Models;

{$importsStr}
use Database\\Factories\\{$name}Factory;
use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
{$modelImport}
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;{$softDeletesImport}

class {$name} extends {$extendsClass}{$implementsStr}
{
    /** @use HasFactory<{$name}Factory> */
    use HasFactory;
{$traitsStr}{$softDeletesUse}

    /**
     * @var list<string>
     */
    protected \$fillable = [
        'name',
        'description',
        'is_active',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, \$this>
     */
    public function owner(): BelongsTo
    {
        return \$this->belongsTo(User::class, 'owner_id');
    }{$searchableColumnsMethod}{$aiContextMethod}

    protected static function newFactory(): {$name}Factory
    {
        return {$name}Factory::new();
    }
}
PHP;

        $path = app_path("Models/{$name}.php");
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        return "app/Models/{$name}.php";
    }

    protected function generateSmartModel(string $name, string $tableName, array $traits, bool $aiContext = false): string
    {
        ['traitImports' => $traitImports, 'traitUses' => $traitUses, 'interfaces' => $interfaces, 'interfaceImports' => $interfaceImports] =
            $this->resolveTraitsAndInterfaces($traits, $aiContext);

        $relationImports = ['use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;'];

        // State machine imports
        if (! empty($this->states)) {
            $traitImports[] = 'use Spatie\\ModelStates\\HasStates;';
            $traitUses[] = '    use HasStates;';
            $interfaceImports[] = "use App\\States\\{$name}State;";
        }

        // Relationship imports from --relationships
        foreach ($this->relationships as $rel) {
            $import = "use Illuminate\\Database\\Eloquent\\Relations\\{$rel->eloquentType()};";
            if (! in_array($import, $relationImports)) {
                $relationImports[] = $import;
            }
        }

        $implementsStr = ! empty($interfaces) ? ' implements '.implode(', ', $interfaces) : '';

        // Check if base class provides is_active / owner_id
        $baseHasIsActive = $this->baseInspector !== null && $this->baseInspector->hasColumn('is_active');
        $baseHasOwnerId = $this->baseInspector !== null && $this->baseInspector->hasColumn('owner_id');

        // Build fillable array (child fields only — base fillable is on the base class)
        $fillableFields = [];
        foreach ($this->fields as $field) {
            $fillableFields[] = "        '{$field->name}',";
        }

        $hasExplicitIsActive = false;
        $hasExplicitOwnerId = false;

        foreach ($this->fields as $field) {
            if ($field->name === 'is_active') {
                $hasExplicitIsActive = true;
            }
            if ($field->name === 'owner_id') {
                $hasExplicitOwnerId = true;
            }
        }

        if (! empty($this->states)) {
            $fillableFields[] = "        'status',";
        }
        if (! $hasExplicitIsActive && ! $baseHasIsActive) {
            $fillableFields[] = "        'is_active',";
        }
        if (! $hasExplicitOwnerId && ! $baseHasOwnerId) {
            $fillableFields[] = "        'owner_id',";
        }

        $fillableStr = implode("\n", $fillableFields);

        // Build casts (child casts only)
        $casts = [];
        foreach ($this->fields as $field) {
            $cast = $this->getCastForField($field, $name);
            if ($cast !== null) {
                $casts[] = "            '{$field->name}' => {$cast},";
            }
        }

        if (! empty($this->states)) {
            $casts[] = "            'status' => {$name}State::class,";
        }
        if (! $hasExplicitIsActive && ! $baseHasIsActive) {
            $casts[] = "            'is_active' => 'boolean',";
        }

        $castsStr = implode("\n", $casts);

        // Build relationships (child only — base relationships are inherited)
        $relationshipMethods = '';

        // BelongsTo from foreignId fields
        foreach ($this->fields as $field) {
            if ($field->isForeignKey()) {
                $methodName = $field->relationshipMethodName();
                $modelName = $field->relatedModelName();
                $relationshipMethods .= <<<PHP


    /**
     * @return BelongsTo<{$modelName}, \$this>
     */
    public function {$methodName}(): BelongsTo
    {
        return \$this->belongsTo({$modelName}::class, '{$field->name}');
    }
PHP;
            }
        }

        // Add owner only if not explicit foreignId AND not from base class
        if (! $hasExplicitOwnerId && ! $baseHasOwnerId) {
            $relationshipMethods .= <<<'PHP'


    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
PHP;
        }

        // Relationships from --relationships
        foreach ($this->relationships as $rel) {
            $returnType = $rel->eloquentType();
            $fkParam = $rel->foreignKey ? ", '{$rel->foreignKey}'" : '';
            $relationshipMethods .= <<<PHP


    /**
     * @return {$returnType}<\\App\\Models\\{$rel->relatedModel}, \$this>
     */
    public function {$rel->name}(): {$returnType}
    {
        return \$this->{$rel->type}(\\App\\Models\\{$rel->relatedModel}::class{$fkParam});
    }
PHP;
        }

        // Searchable columns (always on child — child-specific columns)
        $hasStandardScopes = in_array('HasStandardScopes', $traits);
        $searchableColumnsMethod = '';
        if ($hasStandardScopes) {
            $stringFields = array_filter($this->fields, fn (FieldDefinition $f): bool => $f->type === 'string');
            $searchCols = array_map(fn (FieldDefinition $f): string => "'{$f->name}'", $stringFields);
            $searchColsStr = ! empty($searchCols) ? implode(', ', $searchCols) : '';
            $searchableColumnsMethod = <<<PHP


    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return [{$searchColsStr}];
    }
PHP;
        }

        // AI context fields method
        $aiContextMethod = '';
        if ($aiContext) {
            $contextFields = array_map(fn (FieldDefinition $f): string => "'{$f->name}'", $this->fields);
            $contextFieldsStr = implode(', ', $contextFields);
            $aiContextMethod = <<<PHP


    /**
     * @return array<int, string>
     */
    protected function aiContextFields(): array
    {
        return [{$contextFieldsStr}];
    }
PHP;
        }

        // Enum imports
        $enumImports = [];
        foreach ($this->fields as $field) {
            if ($field->isEnum()) {
                $enumImports[] = "use App\\Enums\\{$field->typeArgument};";
            }
        }

        // Model imports for foreignId relationships
        $modelImports = [];
        if (! $baseHasOwnerId) {
            $modelImports[] = 'use App\\Models\\User;';
        }
        foreach ($this->fields as $field) {
            if ($field->isForeignKey()) {
                $modelName = $field->relatedModelName();
                if ($modelName !== 'User' || $baseHasOwnerId) {
                    $import = "use App\\Models\\{$modelName};";
                    if (! in_array($import, $modelImports)) {
                        $modelImports[] = $import;
                    }
                }
            }
        }

        // Determine extends clause
        $baseSoftDeletes = $this->baseInspector !== null && $this->baseInspector->hasTrait('SoftDeletes');

        if ($this->baseInspector !== null) {
            $extendsClass = $this->baseInspector->shortClassName();
            $modelImportLine = "use {$this->baseInspector->fullClassName()};";
        } else {
            $extendsClass = 'Model';
            $modelImportLine = 'use Illuminate\\Database\\Eloquent\\Model;';
        }

        $allImports = array_merge(
            $interfaceImports,
            $traitImports,
            $enumImports,
            ["use Database\\Factories\\{$name}Factory;"],
            ['use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;'],
            [$modelImportLine],
            $relationImports,
            $modelImports,
        );

        if (! $baseSoftDeletes) {
            $allImports[] = 'use Illuminate\\Database\\Eloquent\\SoftDeletes;';
        }

        sort($allImports);
        $allImports = array_unique($allImports);
        $importsStr = implode("\n", $allImports);
        $traitsStr = implode("\n", $traitUses);

        $softDeletesUse = $baseSoftDeletes ? '' : "\n    use SoftDeletes;";

        $content = <<<PHP
<?php

namespace App\\Models;

{$importsStr}

class {$name} extends {$extendsClass}{$implementsStr}
{
    /** @use HasFactory<{$name}Factory> */
    use HasFactory;
{$traitsStr}{$softDeletesUse}

    /**
     * @var list<string>
     */
    protected \$fillable = [
{$fillableStr}
    ];

    protected function casts(): array
    {
        return [
{$castsStr}
        ];
    }{$relationshipMethods}{$searchableColumnsMethod}{$aiContextMethod}

    protected static function newFactory(): {$name}Factory
    {
        return {$name}Factory::new();
    }
}
PHP;

        $path = app_path("Models/{$name}.php");
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        return "app/Models/{$name}.php";
    }

    protected function getCastForField(FieldDefinition $field, string $entityName): ?string
    {
        return match ($field->type) {
            'float' => "'decimal:2'",
            'boolean' => "'boolean'",
            'date' => "'date'",
            'datetime' => "'datetime'",
            'enum' => "{$field->typeArgument}::class",
            'json' => "'array'",
            default => null,
        };
    }

    protected function generateMigration(string $name, string $tableName): string
    {
        $timestamp = now()->format('Y_m_d_His');
        $filename = "{$timestamp}_create_{$tableName}_table.php";

        if ($this->smartMode) {
            $columns = $this->buildSmartMigrationColumns($name, $tableName);
        } else {
            $columns = <<<'COLS'
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
COLS;
        }

        $content = <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
{$columns}
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP;

        $path = database_path("migrations/{$filename}");
        file_put_contents($path, $content);

        return "database/migrations/{$filename}";
    }

    protected function buildSmartMigrationColumns(string $name, string $tableName): string
    {
        $lines = [];
        $lines[] = '            $table->id();';

        // Check if base class provides is_active / owner_id
        $baseHasIsActive = $this->baseInspector !== null && $this->baseInspector->hasColumn('is_active');
        $baseHasOwnerId = $this->baseInspector !== null && $this->baseInspector->hasColumn('owner_id');

        $hasExplicitIsActive = false;
        $hasExplicitOwnerId = false;

        // Child fields only (base fields are in the base migration)
        foreach ($this->fields as $field) {
            if ($field->name === 'is_active') {
                $hasExplicitIsActive = true;
            }
            if ($field->name === 'owner_id') {
                $hasExplicitOwnerId = true;
            }
            $lines[] = '            '.$this->getMigrationColumnForField($field);
        }

        if (! empty($this->states)) {
            $defaultState = $this->states[0];
            $lines[] = "            \$table->string('status')->default('{$defaultState}');";
        }

        if (! $hasExplicitIsActive && ! $baseHasIsActive) {
            $lines[] = "            \$table->boolean('is_active')->default(true);";
        }
        if (! $hasExplicitOwnerId && ! $baseHasOwnerId) {
            $lines[] = "            \$table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();";
        }

        $lines[] = '            $table->timestamps();';
        $lines[] = '            $table->softDeletes();';

        return implode("\n", $lines);
    }

    protected function getMigrationColumnForField(FieldDefinition $field): string
    {
        $col = match ($field->type) {
            'string' => "\$table->string('{$field->name}')",
            'text' => "\$table->text('{$field->name}')",
            'integer' => "\$table->integer('{$field->name}')",
            'float' => "\$table->decimal('{$field->name}', 12, 2)",
            'boolean' => "\$table->boolean('{$field->name}')",
            'date' => "\$table->date('{$field->name}')",
            'datetime' => "\$table->dateTime('{$field->name}')",
            'enum' => "\$table->string('{$field->name}')",
            'json' => "\$table->json('{$field->name}')",
            'foreignId' => "\$table->foreignId('{$field->name}')->constrained('{$field->typeArgument}')->cascadeOnDelete()",
            default => "\$table->string('{$field->name}')",
        };

        if ($field->nullable && $field->type !== 'foreignId') {
            $col .= '->nullable()';
        }
        if ($field->unique) {
            $col .= '->unique()';
        }
        if ($field->indexed) {
            $col .= '->index()';
        }
        if ($field->default !== null && $field->type !== 'foreignId') {
            $defaultVal = match (true) {
                $field->default === 'true' => 'true',
                $field->default === 'false' => 'false',
                is_numeric($field->default) => $field->default,
                default => "'{$field->default}'",
            };
            $col .= "->default({$defaultVal})";
        }

        return $col.';';
    }

    protected function generateFactory(string $name): string
    {
        if ($this->smartMode) {
            return $this->generateSmartFactory($name);
        }

        $content = <<<'PHP'
<?php

namespace Database\Factories;

use App\Models\__NAME__;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<__NAME__>
 */
class __NAME__Factory extends Factory
{
    protected $model = __NAME__::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'is_active' => true,
            'owner_id' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
PHP;

        $content = str_replace('__NAME__', $name, $content);

        $path = database_path("factories/{$name}Factory.php");
        file_put_contents($path, $content);

        return "database/factories/{$name}Factory.php";
    }

    protected function generateSmartFactory(string $name): string
    {
        $imports = ["use App\\Models\\{$name};"];
        $definitions = [];
        $stateMethods = '';

        // Check if base class provides is_active / owner_id
        $baseHasIsActive = $this->baseInspector !== null && $this->baseInspector->hasColumn('is_active');
        $baseHasOwnerId = $this->baseInspector !== null && $this->baseInspector->hasColumn('owner_id');

        // Include base field fakers in the factory definition
        if ($this->baseInspector !== null) {
            foreach ($this->baseInspector->columns() as $baseField) {
                $fakerCall = $this->getFakerForField($baseField);
                $definitions[] = "            '{$baseField->name}' => {$fakerCall},";

                if ($baseField->isForeignKey()) {
                    $modelName = $baseField->relatedModelName();
                    $import = "use App\\Models\\{$modelName};";
                    if (! in_array($import, $imports)) {
                        $imports[] = $import;
                    }
                }
                if ($baseField->isEnum()) {
                    $imports[] = "use App\\Enums\\{$baseField->typeArgument};";
                }
            }
        }

        $hasExplicitIsActive = false;
        $hasExplicitOwnerId = false;

        foreach ($this->fields as $field) {
            if ($field->name === 'is_active') {
                $hasExplicitIsActive = true;
            }
            if ($field->name === 'owner_id') {
                $hasExplicitOwnerId = true;
            }

            $fakerCall = $this->getFakerForField($field);
            $definitions[] = "            '{$field->name}' => {$fakerCall},";

            if ($field->isForeignKey()) {
                $modelName = $field->relatedModelName();
                $import = "use App\\Models\\{$modelName};";
                if (! in_array($import, $imports)) {
                    $imports[] = $import;
                }
            }
            if ($field->isEnum()) {
                $imports[] = "use App\\Enums\\{$field->typeArgument};";
            }
        }

        if (! $hasExplicitIsActive && ! $baseHasIsActive) {
            $definitions[] = "            'is_active' => true,";
        }
        if (! $hasExplicitOwnerId && ! $baseHasOwnerId) {
            $imports[] = 'use App\\Models\\User;';
            $definitions[] = "            'owner_id' => User::factory(),";
        }

        $definitionsStr = implode("\n", $definitions);

        // State machine factory methods
        if (! empty($this->states)) {
            foreach ($this->states as $state) {
                $methodName = Str::camel($state);
                $stateMethods .= <<<PHP


    public function {$methodName}(): static
    {
        return \$this->state(fn (array \$attributes): array => [
            'status' => '{$state}',
        ]);
    }
PHP;
            }
        }

        // Inactive state method
        $inactiveMethod = <<<'PHP'


    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
PHP;

        sort($imports);
        $imports = array_unique($imports);
        $importsStr = implode("\n", $imports);

        $content = <<<PHP
<?php

namespace Database\\Factories;

{$importsStr}
use Illuminate\\Database\\Eloquent\\Factories\\Factory;

/**
 * @extends Factory<{$name}>
 */
class {$name}Factory extends Factory
{
    protected \$model = {$name}::class;

    public function definition(): array
    {
        return [
{$definitionsStr}
        ];
    }{$inactiveMethod}{$stateMethods}
}
PHP;

        $path = database_path("factories/{$name}Factory.php");
        file_put_contents($path, $content);

        return "database/factories/{$name}Factory.php";
    }

    protected function getFakerForField(FieldDefinition $field): string
    {
        return match ($field->type) {
            'string' => 'fake()->sentence(3)',
            'text' => 'fake()->paragraph()',
            'integer' => 'fake()->numberBetween(1, 100)',
            'float' => 'fake()->optional(0.7)->randomFloat(2, 1000, 500000)',
            'boolean' => $field->default === 'false' ? 'false' : 'true',
            'date' => "fake()->dateTimeBetween('-6 months', '+6 months')",
            'datetime' => "fake()->dateTimeBetween('-6 months', '+6 months')",
            'enum' => "fake()->randomElement({$field->typeArgument}::cases())",
            'json' => 'null',
            'foreignId' => "{$field->relatedModelName()}::factory()",
            default => 'fake()->word()',
        };
    }

    protected function generateSeeder(string $name): string
    {
        $content = <<<'PHP'
<?php

namespace Database\Seeders;

use App\Models\__NAME__;
use App\Models\User;
use Illuminate\Database\Seeder;

class __NAME__Seeder extends Seeder
{
    public function run(): void
    {
        $owner = User::first() ?? User::factory()->create();

        __NAME__::factory()
            ->count(5)
            ->create(['owner_id' => $owner->id]);
    }
}
PHP;

        $content = str_replace('__NAME__', $name, $content);

        $path = database_path("seeders/{$name}Seeder.php");
        file_put_contents($path, $content);

        return "database/seeders/{$name}Seeder.php";
    }

    protected function generatePolicy(string $name): string
    {
        $content = <<<'PHP'
<?php

namespace App\Policies;

use Aicl\Policies\BasePolicy;
use App\Models\__NAME__;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

/**
 * Policy for __NAME__ entity.
 *
 * - Owner always has access to their own records
 * - Falls back to Shield permission checks via BasePolicy
 */
class __NAME__Policy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return '__NAME__';
    }

    public function view(User $user, Model $record): bool
    {
        /** @var __NAME__ $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::view($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        /** @var __NAME__ $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var __NAME__ $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::delete($user, $record);
    }
}
PHP;

        $content = str_replace('__NAME__', $name, $content);

        $path = app_path("Policies/{$name}Policy.php");
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        return "app/Policies/{$name}Policy.php";
    }

    protected function generateObserver(string $name): string
    {
        if ($this->smartMode) {
            return $this->generateSmartObserver($name);
        }

        return $this->generateLegacyObserver($name);
    }

    protected function generateLegacyObserver(string $name): string
    {
        $content = <<<'PHP'
<?php

namespace App\Observers;

use Aicl\Observers\BaseObserver;
use App\Models\__NAME__;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer for __NAME__ entity lifecycle events.
 */
class __NAME__Observer extends BaseObserver
{
    public function created(Model $model): void
    {
        /** @var __NAME__ $model */
        activity()
            ->performedOn($model)
            ->log('__NAME__ "' . $model->name . '" was created');
    }

    public function deleted(Model $model): void
    {
        /** @var __NAME__ $model */
        activity()
            ->performedOn($model)
            ->log('__NAME__ "' . $model->name . '" was deleted');
    }
}
PHP;

        $content = str_replace('__NAME__', $name, $content);

        $path = app_path("Observers/{$name}Observer.php");
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        return "app/Observers/{$name}Observer.php";
    }

    protected function generateSmartObserver(string $name): string
    {
        // Priority 1: Observer Rules spec — full declarative observer
        if ($this->entitySpec !== null && $this->entitySpec->hasObserverRules()) {
            return $this->generateObserverFromRules($name, $this->entitySpec);
        }

        // Priority 2: Notification specs — dispatch logic without full observer control
        if ($this->entitySpec !== null && $this->entitySpec->hasStructuredNotifications()) {
            return $this->generateStructuredObserver($name, $this->entitySpec);
        }

        // Priority 3: Legacy — TODO stubs
        return $this->generateSmartObserverLegacy($name);
    }

    /**
     * Generate observer with real notification dispatch logic from structured specs.
     */
    protected function generateStructuredObserver(string $name, EntitySpec $spec): string
    {
        $snakeName = Str::snake($name);
        $displayField = $this->getDisplayField();
        $resolver = new NotificationTemplateResolver($name);

        // Collect notifications by trigger type
        $createdNotifs = [];
        $fieldChangeNotifs = [];
        $deletedNotifs = [];

        foreach ($spec->notificationSpecs as $notifSpec) {
            $type = $notifSpec->triggerType();
            if ($type === 'created') {
                $createdNotifs[] = $notifSpec;
            } elseif ($type === 'field_change' || $type === 'state_transition') {
                $fieldChangeNotifs[] = $notifSpec;
            } elseif ($type === 'deleted') {
                $deletedNotifs[] = $notifSpec;
            }
        }

        // Build imports
        $imports = [
            'use Aicl\\Observers\\BaseObserver;',
            "use App\\Models\\{$name};",
            'use Illuminate\\Database\\Eloquent\\Model;',
        ];

        foreach ($spec->notificationSpecs as $notifSpec) {
            $className = $name.$notifSpec->name.'Notification';
            $imports[] = "use App\\Notifications\\{$className};";
        }

        sort($imports);
        $importsStr = implode("\n", $imports);

        // Build created() method
        $createdDispatches = '';
        foreach ($createdNotifs as $notifSpec) {
            $className = $name.$notifSpec->name.'Notification';
            $recipientCode = $resolver->resolveRecipient($notifSpec->recipient);
            $createdDispatches .= <<<PHP

        {$recipientCode}?->notify(new {$className}(\$model, auth()->user()));
PHP;
        }

        // Build updating() method for status transitions
        $updatingMethod = '';
        if (! empty($this->states)) {
            $updatingMethod = <<<PHP

    public function updating(Model \$model): void
    {
        /** @var {$name} \$model */
        if (\$model->isDirty('status')) {
            \$oldStatus = \$model->getOriginal('status');
            \$newStatus = \$model->status;

            activity()
                ->performedOn(\$model)
                ->withProperties([
                    'old_status' => \$oldStatus ? (string) \$oldStatus : null,
                    'new_status' => (string) \$newStatus,
                ])
                ->log('{$name} "' . \$model->{$displayField} . '" status changed from ' . (\$oldStatus ? (string) \$oldStatus : 'none') . ' to ' . (string) \$newStatus);
        }
    }
PHP;
        }

        // Build updated() method with real dispatch logic
        $updatedMethod = '';
        if (! empty($fieldChangeNotifs)) {
            $checks = [];
            foreach ($fieldChangeNotifs as $notifSpec) {
                $field = $notifSpec->watchedField();
                $className = $name.$notifSpec->name.'Notification';
                $recipientCode = $resolver->resolveRecipient($notifSpec->recipient);
                $isStatusChange = $field === 'status' && ! empty($this->states);

                if ($isStatusChange) {
                    $checks[] = <<<PHP
        if (\$model->isDirty('status')) {
            \$oldStatus = \$model->getOriginal('status');
            \$newStatus = \$model->status;
            {$recipientCode}?->notify(new {$className}(\$model, \$oldStatus, \$newStatus, auth()->user()));
        }
PHP;
                } else {
                    $checks[] = <<<PHP
        if (\$model->isDirty('{$field}') && \$model->{$field}) {
            {$recipientCode}?->notify(new {$className}(\$model, auth()->user()));
        }
PHP;
                }
            }
            $checksStr = implode("\n\n", $checks);

            $updatedMethod = <<<PHP

    public function updated(Model \$model): void
    {
        /** @var {$name} \$model */
{$checksStr}
    }
PHP;
        }

        // Build deleted() method
        $deletedDispatches = '';
        foreach ($deletedNotifs as $notifSpec) {
            $className = $name.$notifSpec->name.'Notification';
            $recipientCode = $resolver->resolveRecipient($notifSpec->recipient);
            $deletedDispatches .= <<<PHP

        {$recipientCode}?->notify(new {$className}(\$model, auth()->user()));
PHP;
        }

        $content = <<<PHP
<?php

namespace App\\Observers;

{$importsStr}

/**
 * Observer for {$name} entity lifecycle events.
 */
class {$name}Observer extends BaseObserver
{
    public function created(Model \$model): void
    {
        /** @var {$name} \$model */
        activity()
            ->performedOn(\$model)
            ->log('{$name} "' . \$model->{$displayField} . '" was created');{$createdDispatches}
    }{$updatingMethod}{$updatedMethod}

    public function deleted(Model \$model): void
    {
        /** @var {$name} \$model */
        activity()
            ->performedOn(\$model)
            ->log('{$name} "' . \$model->{$displayField} . '" was deleted');{$deletedDispatches}
    }
}
PHP;

        $path = app_path("Observers/{$name}Observer.php");
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        return "app/Observers/{$name}Observer.php";
    }

    /**
     * Generate observer entirely from ## Observer Rules spec.
     *
     * This is the highest-priority observer generation method — when Observer Rules
     * are defined, they completely control the observer's behavior (both logging
     * and notification dispatch).
     */
    protected function generateObserverFromRules(string $name, EntitySpec $spec): string
    {
        $snakeName = Str::snake($name);
        $displayField = $this->getDisplayField();
        $resolver = new NotificationTemplateResolver($name);

        // Group rules by event
        $rulesByEvent = ['created' => [], 'updated' => [], 'deleted' => []];

        foreach ($spec->observerRules as $rule) {
            if (isset($rulesByEvent[$rule->event])) {
                $rulesByEvent[$rule->event][] = $rule;
            }
        }

        // Collect notification class imports from notify rules
        $imports = [
            'use Aicl\\Observers\\BaseObserver;',
            "use App\\Models\\{$name};",
            'use Illuminate\\Database\\Eloquent\\Model;',
        ];

        foreach ($spec->observerRules as $rule) {
            if ($rule->isNotify()) {
                $parsed = $rule->parseNotifyDetails();

                if ($parsed['class'] !== '') {
                    $imports[] = "use App\\Notifications\\{$name}{$parsed['class']}Notification;";
                }
            }
        }

        $imports = array_unique($imports);
        sort($imports);
        $importsStr = implode("\n", $imports);

        // Build created() method
        $createdBody = $this->buildObserverMethodBody($name, $rulesByEvent['created'], $displayField, $resolver);

        // Build updating() method (for status activity logging) — only if we have status watch rules with log action
        $updatingMethod = '';
        $hasStatusLog = false;

        foreach ($rulesByEvent['updated'] as $rule) {
            if ($rule->isLog() && $rule->watchField === 'status' && ! empty($this->states)) {
                $hasStatusLog = true;

                break;
            }
        }

        if ($hasStatusLog) {
            $updatingMethod = <<<PHP

    public function updating(Model \$model): void
    {
        /** @var {$name} \$model */
        if (\$model->isDirty('status')) {
            \$oldStatus = \$model->getOriginal('status');
            \$newStatus = \$model->status;

            activity()
                ->performedOn(\$model)
                ->withProperties([
                    'old_status' => \$oldStatus ? (string) \$oldStatus : null,
                    'new_status' => (string) \$newStatus,
                ])
                ->log('{$name} "' . \$model->{$displayField} . '" status changed from ' . (\$oldStatus ? (string) \$oldStatus : 'none') . ' to ' . (string) \$newStatus);
        }
    }
PHP;
        }

        // Build updated() method from update rules
        $updatedMethod = '';
        $updateNotifyRules = array_filter($rulesByEvent['updated'], fn ($r) => $r->isNotify());

        if (! empty($updateNotifyRules)) {
            $checks = [];

            foreach ($updateNotifyRules as $rule) {
                $parsed = $rule->parseNotifyDetails();
                $recipientCode = $resolver->resolveRecipient($parsed['recipient']);
                $className = $name.$parsed['class'].'Notification';
                $field = $rule->watchField;
                $isStatusChange = $field === 'status' && ! empty($this->states);

                if ($isStatusChange) {
                    $check = <<<PHP
        if (\$model->isDirty('status')) {
            \$oldStatus = \$model->getOriginal('status');
            \$newStatus = \$model->status;
            {$recipientCode}?->notify(new {$className}(\$model, \$oldStatus, \$newStatus, auth()->user()));
        }
PHP;
                } elseif ($field !== null) {
                    $conditionParts = ["\$model->isDirty('{$field}')"];

                    if ($parsed['condition'] !== null) {
                        $conditionParts[] = $this->resolveRuleCondition($parsed['condition'], $field);
                    } else {
                        $conditionParts[] = "\$model->{$field}";
                    }

                    $conditionStr = implode(' && ', $conditionParts);
                    $check = <<<PHP
        if ({$conditionStr}) {
            {$recipientCode}?->notify(new {$className}(\$model, auth()->user()));
        }
PHP;
                } else {
                    $check = "        {$recipientCode}?->notify(new {$className}(\$model, auth()->user()));";
                }

                $checks[] = $check;
            }

            $checksStr = implode("\n\n", $checks);

            $updatedMethod = <<<PHP

    public function updated(Model \$model): void
    {
        /** @var {$name} \$model */
{$checksStr}
    }
PHP;
        }

        // Build deleted() method
        $deletedBody = $this->buildObserverMethodBody($name, $rulesByEvent['deleted'], $displayField, $resolver);

        $content = <<<PHP
<?php

namespace App\\Observers;

{$importsStr}

/**
 * Observer for {$name} entity lifecycle events.
 */
class {$name}Observer extends BaseObserver
{
    public function created(Model \$model): void
    {
        /** @var {$name} \$model */
{$createdBody}
    }{$updatingMethod}{$updatedMethod}

    public function deleted(Model \$model): void
    {
        /** @var {$name} \$model */
{$deletedBody}
    }
}
PHP;

        $path = app_path("Observers/{$name}Observer.php");
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        return "app/Observers/{$name}Observer.php";
    }

    /**
     * Build the method body for a created() or deleted() observer method from rules.
     *
     * @param  array<int, ObserverRuleSpec>  $rules
     */
    protected function buildObserverMethodBody(
        string $name,
        array $rules,
        string $displayField,
        NotificationTemplateResolver $resolver,
    ): string {
        $lines = [];

        foreach ($rules as $rule) {
            if ($rule->isLog()) {
                $logMessage = $this->resolveLogTemplate($rule->details, $name, $displayField);
                $lines[] = <<<PHP
        activity()
            ->performedOn(\$model)
            ->log({$logMessage});
PHP;
            } elseif ($rule->isNotify()) {
                $parsed = $rule->parseNotifyDetails();
                $recipientCode = $resolver->resolveRecipient($parsed['recipient']);
                $className = $name.$parsed['class'].'Notification';

                $notifyLine = "        {$recipientCode}?->notify(new {$className}(\$model, auth()->user()));";

                if ($parsed['condition'] !== null) {
                    $conditionCode = $this->resolveRuleCondition($parsed['condition'], null);
                    $lines[] = <<<PHP
        if ({$conditionCode}) {
            {$recipientCode}?->notify(new {$className}(\$model, auth()->user()));
        }
PHP;
                } else {
                    $lines[] = $notifyLine;
                }
            }
        }

        if (empty($lines)) {
            $lines[] = '        //';
        }

        return implode("\n\n", $lines);
    }

    /**
     * Resolve a log template string to a PHP expression.
     *
     * Replaces:
     *   {Name} → entity name literal
     *   {model.field} → $model->field interpolation
     *   {new.status.label} → $model->status label interpolation
     */
    protected function resolveLogTemplate(string $template, string $name, string $displayField): string
    {
        // Replace {Name} with the entity name
        $template = str_replace('{Name}', $name, $template);

        // Replace {model.field} → "' . $model->field . '"
        $hasModelRef = preg_match('/\{model\.(\w+)\}/', $template);

        if ($hasModelRef) {
            $template = preg_replace_callback(
                '/\{model\.(\w+)\}/',
                fn ($m) => "' . \$model->{$m[1]} . '",
                $template
            );

            return "'{$template}'";
        }

        // Replace {new.status.label} with status casting
        if (str_contains($template, '{new.status.label}')) {
            $template = str_replace('{new.status.label}', "' . (string) \$model->status . '", $template);

            return "'{$template}'";
        }

        return "'{$template}'";
    }

    /**
     * Resolve a rule condition to a PHP expression.
     *
     * "owner_id set" → $model->owner_id
     * "field set" → $model->field
     */
    protected function resolveRuleCondition(string $condition, ?string $contextField): string
    {
        // "{field} set" → $model->{field}
        if (preg_match('/^(\w+)\s+set$/', $condition, $m)) {
            return "\$model->{$m[1]}";
        }

        // Plain field name
        if (preg_match('/^\w+$/', $condition)) {
            return "\$model->{$condition}";
        }

        return "// TODO: condition: {$condition}";
    }

    /**
     * Generate smart observer with TODO stubs (no structured notification specs).
     */
    protected function generateSmartObserverLegacy(string $name): string
    {
        $snakeName = Str::snake($name);
        $displayField = $this->getDisplayField();

        // Build updating() stub for status transitions
        $updatingMethod = '';
        if (! empty($this->states)) {
            $updatingMethod = <<<PHP

    public function updating(Model \$model): void
    {
        /** @var {$name} \$model */
        if (\$model->isDirty('status')) {
            \$oldStatus = \$model->getOriginal('status');
            \$newStatus = \$model->status;

            activity()
                ->performedOn(\$model)
                ->withProperties([
                    'old_status' => \$oldStatus ? (string) \$oldStatus : null,
                    'new_status' => (string) \$newStatus,
                ])
                ->log('{$name} "' . \$model->{$displayField} . '" status changed from ' . (\$oldStatus ? (string) \$oldStatus : 'none') . ' to ' . (string) \$newStatus);
        }
    }
PHP;
        }

        // Build updated() stub for ownership change notifications
        $updatedMethod = '';
        $assignableFkFields = [];
        if ($this->fields !== null) {
            foreach ($this->fields as $field) {
                if ($field->isForeignKey() && (str_contains($field->name, 'assigned') || str_contains($field->name, 'owner'))) {
                    $assignableFkFields[] = $field;
                }
            }
        }

        if (! empty($assignableFkFields)) {
            $checks = [];
            foreach ($assignableFkFields as $field) {
                $methodName = $field->relationshipMethodName() ?? Str::camel(str_replace('_id', '', $field->name));
                $checks[] = <<<PHP
        if (\$model->isDirty('{$field->name}') && \$model->{$field->name}) {
            // TODO: Dispatch notification to new {$methodName}
            // \$model->{$methodName}->notify(new {$name}AssignedNotification(\$model, auth()->user()));
        }
PHP;
            }
            $checksStr = implode("\n\n", $checks);

            $updatedMethod = <<<PHP

    public function updated(Model \$model): void
    {
        /** @var {$name} \$model */
{$checksStr}
    }
PHP;
        }

        $content = <<<PHP
<?php

namespace App\\Observers;

use Aicl\\Observers\\BaseObserver;
use App\\Models\\{$name};
use Illuminate\\Database\\Eloquent\\Model;

/**
 * Observer for {$name} entity lifecycle events.
 */
class {$name}Observer extends BaseObserver
{
    public function created(Model \$model): void
    {
        /** @var {$name} \$model */
        activity()
            ->performedOn(\$model)
            ->log('{$name} "' . \$model->{$displayField} . '" was created');
    }{$updatingMethod}{$updatedMethod}

    public function deleted(Model \$model): void
    {
        /** @var {$name} \$model */
        activity()
            ->performedOn(\$model)
            ->log('{$name} "' . \$model->{$displayField} . '" was deleted');
    }
}
PHP;

        $path = app_path("Observers/{$name}Observer.php");
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        return "app/Observers/{$name}Observer.php";
    }

    /**
     * Get the first string field name for activity log display.
     */
    protected function getDisplayField(): string
    {
        if ($this->fields !== null) {
            foreach ($this->fields as $field) {
                if ($field->type === 'string') {
                    return $field->name;
                }
            }
        }

        return 'name';
    }

    /**
     * Generate broadcast events extending BaseBroadcastEvent.
     *
     * @return array<int, string>
     */
    protected function generateBroadcastEvents(string $name): array
    {
        $snakeName = Str::snake($name);
        $files = [];

        $actions = [
            'Created' => 'created',
            'Updated' => 'updated',
            'Deleted' => 'deleted',
        ];

        foreach ($actions as $suffix => $action) {
            $className = "{$name}{$suffix}";
            $content = $this->buildBroadcastEventContent($name, $className, $snakeName, $action);

            $path = app_path("Events/{$className}.php");
            $this->ensureDirectoryExists(dirname($path));
            file_put_contents($path, $content);

            $files[] = "app/Events/{$className}.php";
        }

        return $files;
    }

    protected function buildBroadcastEventContent(string $name, string $className, string $snakeName, string $action): string
    {
        if ($action === 'deleted') {
            return <<<PHP
<?php

namespace App\Events;

use Aicl\Broadcasting\BaseBroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Model;

class {$className} extends BaseBroadcastEvent
{
    public int|string \$entityId;

    public string \$entityType;

    public function __construct(Model \$entity)
    {
        parent::__construct();

        \$this->entityId = \$entity->getKey();
        \$this->entityType = class_basename(\$entity);
    }

    public static function eventType(): string
    {
        return '{$snakeName}.{$action}';
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'id' => \$this->entityId,
            'type' => \$this->entityType,
            'action' => '{$action}',
        ];
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        \$type = strtolower(\$this->entityType);

        return [
            new PrivateChannel('dashboard'),
            new PrivateChannel("{\$type}s.{\$this->entityId}"),
        ];
    }
}
PHP;
        }

        return <<<PHP
<?php

namespace App\Events;

use Aicl\Broadcasting\BaseBroadcastEvent;
use App\Models\\{$name};
use Illuminate\Database\Eloquent\Model;

class {$className} extends BaseBroadcastEvent
{
    public function __construct(
        public {$name} \$entity,
    ) {
        parent::__construct();
    }

    public static function eventType(): string
    {
        return '{$snakeName}.{$action}';
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'id' => \$this->entity->getKey(),
            'type' => class_basename(\$this->entity),
            'action' => '{$action}',
        ];
    }

    public function getEntity(): ?Model
    {
        return \$this->entity;
    }
}
PHP;
    }

    /**
     * @param  array<int, string>  $traits
     * @return array<int, string>
     */
    protected function generateFilamentResource(string $name, array $traits = []): array
    {
        $pluralName = Str::pluralStudly($name);
        $files = [];

        // Resource directory
        $baseDir = app_path("Filament/Resources/{$pluralName}");
        $this->ensureDirectoryExists("{$baseDir}/Pages");
        $this->ensureDirectoryExists("{$baseDir}/Schemas");
        $this->ensureDirectoryExists("{$baseDir}/Tables");

        // Resource class
        $content = <<<PHP
<?php

namespace App\\Filament\\Resources\\{$pluralName};

use App\\Models\\{$name};
use App\\Filament\\Resources\\{$pluralName}\\Pages\\Create{$name};
use App\\Filament\\Resources\\{$pluralName}\\Pages\\Edit{$name};
use App\\Filament\\Resources\\{$pluralName}\\Pages\\List{$pluralName};
use App\\Filament\\Resources\\{$pluralName}\\Pages\\View{$name};
use App\\Filament\\Resources\\{$pluralName}\\Schemas\\{$name}Form;
use App\\Filament\\Resources\\{$pluralName}\\Schemas\\{$name}Infolist;
use App\\Filament\\Resources\\{$pluralName}\\Tables\\{$pluralName}Table;
use BackedEnum;
use Filament\\Pages\\Enums\\SubNavigationPosition;
use Filament\\Resources\\Pages\\Page;
use Filament\\Resources\\Resource;
use Filament\\Schemas\\Schema;
use Filament\\Support\\Icons\\Heroicon;
use Filament\\Tables\\Table;
use UnitEnum;

class {$name}Resource extends Resource
{
    protected static ?string \$model = {$name}::class;

    // Icon is set on the NavigationGroup in AdminPanelProvider, not on child resources.
    protected static string|BackedEnum|null \$navigationIcon = null;

    protected static string|UnitEnum|null \$navigationGroup = 'Content';

    protected static ?int \$navigationSort = 10;

    protected static ?string \$recordTitleAttribute = 'name';

    protected static ?SubNavigationPosition \$subNavigationPosition = SubNavigationPosition::Top;

    public static function form(Schema \$schema): Schema
    {
        return {$name}Form::configure(\$schema);
    }

    public static function infolist(Schema \$schema): Schema
    {
        return {$name}Infolist::configure(\$schema);
    }

    public static function table(Table \$table): Table
    {
        return {$pluralName}Table::configure(\$table);
    }

    public static function getRecordSubNavigation(Page \$page): array
    {
        return \$page->generateNavigationItems([
            Pages\\View{$name}::class,
            Pages\\Edit{$name}::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => List{$pluralName}::route('/'),
            'create' => Create{$name}::route('/create'),
            'view' => View{$name}::route('/{record}'),
            'edit' => Edit{$name}::route('/{record}/edit'),
        ];
    }
}
PHP;

        file_put_contents("{$baseDir}/{$name}Resource.php", $content);
        $files[] = "app/Filament/Resources/{$pluralName}/{$name}Resource.php";

        // Form schema
        if ($this->smartMode) {
            $formBody = $this->buildSmartFormSchema($name);
            // Gather imports needed for smart form
            $smartFormImports = $this->getSmartFormImports($name);
        } else {
            $formBody = <<<PHP
            Section::make('{$name} Details')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    RichEditor::make('description')
                        ->columnSpanFull(),
                ]),

            Section::make('Settings')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('owner_id')
                        ->relationship('owner', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Toggle::make('is_active')
                        ->default(true),
                ]),
PHP;
            $smartFormImports = <<<'PHP'
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
PHP;
        }

        $formContent = <<<PHP
<?php

namespace App\\Filament\\Resources\\{$pluralName}\\Schemas;

{$smartFormImports}

class {$name}Form
{
    public static function configure(Schema \$schema): Schema
    {
        return \$schema->components([
{$formBody}
        ]);
    }
}
PHP;

        file_put_contents("{$baseDir}/Schemas/{$name}Form.php", $formContent);
        $files[] = "app/Filament/Resources/{$pluralName}/Schemas/{$name}Form.php";

        // Infolist schema (for View page — card-based data display)
        if ($this->smartMode) {
            $infolistBody = $this->buildSmartInfolistSchema($name);
            $smartInfolistImports = $this->getSmartInfolistImports($name);
        } else {
            $infolistBody = <<<PHP
            Section::make('{$name} Details')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('owner.name')
                        ->label('Owner'),
                    IconEntry::make('is_active')
                        ->boolean(),
                    TextEntry::make('description')
                        ->html()
                        ->columnSpanFull(),
                ]),
PHP;
            $smartInfolistImports = <<<'PHP'
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
PHP;
        }

        $infolistContent = <<<PHP
<?php

namespace App\\Filament\\Resources\\{$pluralName}\\Schemas;

{$smartInfolistImports}

class {$name}Infolist
{
    public static function configure(Schema \$schema): Schema
    {
        return \$schema->components([
{$infolistBody}
        ]);
    }
}
PHP;

        file_put_contents("{$baseDir}/Schemas/{$name}Infolist.php", $infolistContent);
        $files[] = "app/Filament/Resources/{$pluralName}/Schemas/{$name}Infolist.php";

        // Table
        $snakeName = Str::snake($name);

        if ($this->smartMode) {
            $smartTableData = $this->buildSmartTableColumns($name);
            $parts = explode("\nfilters:", $smartTableData);
            $smartColumns = substr($parts[0], strlen('columns:'));
            $smartFilters = $parts[1] ?? '';
            $smartTableImports = $this->getSmartTableImports($name);
        } else {
            $smartColumns = <<<'PHP'
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
PHP;
            $smartFilters = <<<'PHP'
                TernaryFilter::make('is_active')
                    ->label('Active'),
PHP;
            $smartTableImports = <<<'PHP'
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
PHP;
        }

        $tableContent = <<<PHP
<?php

namespace App\\Filament\\Resources\\{$pluralName}\\Tables;

use App\\Filament\\Exporters\\{$name}Exporter;
use Filament\\Actions\\BulkActionGroup;
use Filament\\Actions\\DeleteBulkAction;
use Filament\\Actions\\EditAction;
use Filament\\Actions\\ExportAction;
use Filament\\Actions\\ExportBulkAction;
use Filament\\Actions\\ViewAction;
{$smartTableImports}
use Filament\\Tables\\Table;

class {$pluralName}Table
{
    public static function configure(Table \$table): Table
    {
        return \$table
            ->columns([
{$smartColumns}
            ])
            ->filters([
{$smartFilters}
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter({$name}Exporter::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter({$name}Exporter::class),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
PHP;

        file_put_contents("{$baseDir}/Tables/{$pluralName}Table.php", $tableContent);
        $files[] = "app/Filament/Resources/{$pluralName}/Tables/{$pluralName}Table.php";

        // Pages — List
        $listContent = <<<PHP
<?php

namespace App\\Filament\\Resources\\{$pluralName}\\Pages;

use App\\Filament\\Resources\\{$pluralName}\\{$name}Resource;
use Filament\\Actions\\CreateAction;
use Filament\\Resources\\Pages\\ListRecords;

class List{$pluralName} extends ListRecords
{
    protected static string \$resource = {$name}Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
PHP;

        file_put_contents("{$baseDir}/Pages/List{$pluralName}.php", $listContent);
        $files[] = "app/Filament/Resources/{$pluralName}/Pages/List{$pluralName}.php";

        // Pages — Create
        $createContent = <<<PHP
<?php

namespace App\\Filament\\Resources\\{$pluralName}\\Pages;

use App\\Filament\\Resources\\{$pluralName}\\{$name}Resource;
use Filament\\Resources\\Pages\\CreateRecord;

class Create{$name} extends CreateRecord
{
    protected static string \$resource = {$name}Resource::class;
}
PHP;

        file_put_contents("{$baseDir}/Pages/Create{$name}.php", $createContent);
        $files[] = "app/Filament/Resources/{$pluralName}/Pages/Create{$name}.php";

        // Pages — View (with header actions and sub-navigation label)
        $viewContent = <<<PHP
<?php

namespace App\\Filament\\Resources\\{$pluralName}\\Pages;

use App\\Filament\\Resources\\{$pluralName}\\{$name}Resource;
use Filament\\Actions\\EditAction;
use Filament\\Resources\\Pages\\ViewRecord;

class View{$name} extends ViewRecord
{
    protected static string \$resource = {$name}Resource::class;

    protected static ?string \$navigationLabel = 'Details';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
PHP;

        file_put_contents("{$baseDir}/Pages/View{$name}.php", $viewContent);
        $files[] = "app/Filament/Resources/{$pluralName}/Pages/View{$name}.php";

        // Pages — Edit (with header actions and sub-navigation label)
        $editContent = <<<PHP
<?php

namespace App\\Filament\\Resources\\{$pluralName}\\Pages;

use App\\Filament\\Resources\\{$pluralName}\\{$name}Resource;
use Filament\\Actions\\DeleteAction;
use Filament\\Actions\\ViewAction;
use Filament\\Resources\\Pages\\EditRecord;

class Edit{$name} extends EditRecord
{
    protected static string \$resource = {$name}Resource::class;

    protected static ?string \$navigationLabel = 'Edit';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
PHP;

        file_put_contents("{$baseDir}/Pages/Edit{$name}.php", $editContent);
        $files[] = "app/Filament/Resources/{$pluralName}/Pages/Edit{$name}.php";

        return $files;
    }

    protected function generateExporter(string $name): string
    {
        if ($this->smartMode) {
            $exportColumns = $this->buildSmartExportColumns($name);
        } else {
            $exportColumns = <<<'PHP'
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name'),
            ExportColumn::make('owner.name')->label('Owner'),
            ExportColumn::make('is_active')->label('Active'),
            ExportColumn::make('created_at'),
PHP;
        }

        $content = <<<PHP
<?php

namespace App\\Filament\\Exporters;

use App\\Models\\{$name};
use Filament\\Actions\\Exports\\ExportColumn;
use Filament\\Actions\\Exports\\Exporter;
use Filament\\Actions\\Exports\\Models\\Export;

class {$name}Exporter extends Exporter
{
    protected static ?string \$model = {$name}::class;

    public static function getColumns(): array
    {
        return [
{$exportColumns}
        ];
    }

    public static function getCompletedNotificationBody(Export \$export): string
    {
        return 'Your {$name} export with ' . number_format(\$export->successful_rows) . ' rows is ready.';
    }
}
PHP;

        $dir = app_path('Filament/Exporters');
        $this->ensureDirectoryExists($dir);
        file_put_contents("{$dir}/{$name}Exporter.php", $content);

        return "app/Filament/Exporters/{$name}Exporter.php";
    }

    /**
     * @return array<int, string>
     */
    protected function generateApiLayer(string $name, string $tableName = ''): array
    {
        $files = [];
        $snakeName = Str::snake($name);
        if ($tableName === '') {
            $tableName = Str::snake(Str::pluralStudly($name));
        }

        // Controller (uses Form Requests — not inline validation)
        $controllerContent = <<<PHP
<?php

namespace App\\Http\\Controllers\\Api;

use Aicl\\Traits\\PaginatesApiRequests;
use App\\Models\\{$name};
use App\\Http\\Controllers\\Controller;
use App\\Http\\Requests\\Store{$name}Request;
use App\\Http\\Requests\\Update{$name}Request;
use App\\Http\\Resources\\{$name}Resource;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection;
use Illuminate\\Support\\Facades\\Gate;

class {$name}Controller extends Controller
{
    use PaginatesApiRequests;

    public function index(Request \$request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', {$name}::class);

        \$query = {$name}::query()
            ->with('owner')
            ->when(\$request->query('search'), fn (\$q, \$term) => \$q->search(\$term))
            ->latest()
            ->paginate(\$this->getPerPage(\$request));

        return {$name}Resource::collection(\$query);
    }

    public function store(Store{$name}Request \$request): JsonResponse
    {
        \$record = {$name}::create([
            ...\$request->validated(),
            'owner_id' => \$request->user()->id,
        ]);

        return (new {$name}Resource(\$record->load('owner')))
            ->response()
            ->setStatusCode(201);
    }

    public function show({$name} \$record): {$name}Resource
    {
        Gate::authorize('view', \$record);

        return new {$name}Resource(\$record->load('owner'));
    }

    public function update(Update{$name}Request \$request, {$name} \$record): {$name}Resource
    {
        \$record->update(\$request->validated());

        return new {$name}Resource(\$record->fresh('owner'));
    }

    public function destroy({$name} \$record): JsonResponse
    {
        Gate::authorize('delete', \$record);

        \$record->delete();

        return response()->json(['message' => '{$name} deleted.'], 200);
    }
}
PHP;

        $this->ensureDirectoryExists(app_path('Http/Controllers/Api'));
        file_put_contents(app_path("Http/Controllers/Api/{$name}Controller.php"), $controllerContent);
        $files[] = "app/Http/Controllers/Api/{$name}Controller.php";

        // Build validation rules
        if ($this->smartMode) {
            $storeRules = $this->buildSmartStoreRules($name, $tableName);
            $updateRules = $this->buildSmartUpdateRules($name, $tableName);
            $storeRulesStr = $this->formatRulesArray($storeRules);
            $updateRulesStr = $this->formatRulesArray($updateRules);

            // Check if we need Rule import
            $needsRule = false;
            foreach ($this->fields as $field) {
                if ($field->isEnum()) {
                    $needsRule = true;

                    break;
                }
            }
            $ruleImport = $needsRule ? "\nuse Illuminate\\Validation\\Rule;" : '';
            $enumImports = '';
            foreach ($this->fields as $field) {
                if ($field->isEnum()) {
                    $enumImports .= "\nuse App\\Enums\\{$field->typeArgument};";
                }
            }
        } else {
            $storeRulesStr = <<<'PHP'
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
PHP;
            $updateRulesStr = <<<'PHP'
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
PHP;
            $ruleImport = '';
            $enumImports = '';
        }

        // Store Form Request
        $storeRequestContent = <<<PHP
<?php

namespace App\\Http\\Requests;

use App\\Models\\{$name};
use Illuminate\\Foundation\\Http\\FormRequest;{$ruleImport}{$enumImports}

class Store{$name}Request extends FormRequest
{
    public function authorize(): bool
    {
        return \$this->user()->can('create', {$name}::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
{$storeRulesStr}
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }
}
PHP;

        $this->ensureDirectoryExists(app_path('Http/Requests'));
        file_put_contents(app_path("Http/Requests/Store{$name}Request.php"), $storeRequestContent);
        $files[] = "app/Http/Requests/Store{$name}Request.php";

        // Update Form Request
        $updateRequestContent = <<<PHP
<?php

namespace App\\Http\\Requests;

use Illuminate\\Foundation\\Http\\FormRequest;{$ruleImport}{$enumImports}

class Update{$name}Request extends FormRequest
{
    public function authorize(): bool
    {
        return \$this->user()->can('update', \$this->route('record'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
{$updateRulesStr}
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }
}
PHP;

        file_put_contents(app_path("Http/Requests/Update{$name}Request.php"), $updateRequestContent);
        $files[] = "app/Http/Requests/Update{$name}Request.php";

        // API Resource
        if ($this->smartMode) {
            $resourceFields = $this->buildSmartResourceFields($name);
        } else {
            $resourceFields = <<<'PHP'
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
PHP;
        }

        $resourceContent = <<<PHP
<?php

namespace App\\Http\\Resources;

use Illuminate\\Http\\Request;
use Illuminate\\Http\\Resources\\Json\\JsonResource;

/**
 * @mixin \\App\\Models\\{$name}
 */
class {$name}Resource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request \$request): array
    {
        return [
{$resourceFields}
        ];
    }
}
PHP;

        $this->ensureDirectoryExists(app_path('Http/Resources'));
        file_put_contents(app_path("Http/Resources/{$name}Resource.php"), $resourceContent);
        $files[] = "app/Http/Resources/{$name}Resource.php";

        return $files;
    }

    /**
     * @param  array<int, string>  $traits
     */
    protected function generateTest(string $name, array $traits): string
    {
        if ($this->smartMode) {
            return $this->generateSmartTest($name, $traits);
        }

        return $this->generateLegacyTest($name, $traits);
    }

    /**
     * @param  array<int, string>  $traits
     */
    protected function generateLegacyTest(string $name, array $traits): string
    {
        $hasAuditTrail = in_array('HasAuditTrail', $traits);
        $hasEntityEvents = in_array('HasEntityEvents', $traits);
        $hasStandardScopes = in_array('HasStandardScopes', $traits);

        $auditTests = $hasAuditTrail ? <<<'PHP'

    public function test___SNAKE___creation_is_logged(): void
    {
        $record = __NAME__::factory()->create();

        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', __NAME__::class)
            ->where('subject_id', $record->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
    }
PHP : '';

        $eventTests = $hasEntityEvents ? <<<'PHP'

    public function test_entity_events_are_dispatched(): void
    {
        \Illuminate\Support\Facades\Event::fake([\Aicl\Events\EntityCreated::class]);

        __NAME__::factory()->create();

        \Illuminate\Support\Facades\Event::assertDispatched(\Aicl\Events\EntityCreated::class);
    }
PHP : '';

        $scopeTests = $hasStandardScopes ? <<<'PHP'

    public function test_active_scope_filters_correctly(): void
    {
        __NAME__::factory()->create(['is_active' => true]);
        __NAME__::factory()->create(['is_active' => false]);

        $this->assertCount(1, __NAME__::active()->get());
    }

    public function test_search_scope_finds_matching_records(): void
    {
        __NAME__::factory()->create(['name' => 'Alpha Test']);
        __NAME__::factory()->create(['name' => 'Beta Test']);

        $this->assertCount(1, __NAME__::search('Alpha')->get());
    }
PHP : '';

        $snakeName = Str::snake($name);
        $tableName = Str::snake(Str::pluralStudly($name));
        $content = <<<PHP
<?php

namespace Tests\\Feature\\Entities;

use App\\Models\\{$name};
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\DatabaseTransactions;
use Spatie\\Permission\\Models\\Permission;
use Spatie\\Permission\\Models\\Role;
use Spatie\\Permission\\PermissionRegistrar;
use Tests\\TestCase;

class {$name}Test extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        \$this->seedPermissions();
    }

    protected function seedPermissions(): void
    {
        \$permissions = [
            'ViewAny:{$name}', 'View:{$name}', 'Create:{$name}',
            'Update:{$name}', 'Delete:{$name}',
        ];

        foreach (\$permissions as \$permission) {
            Permission::firstOrCreate(['name' => \$permission, 'guard_name' => 'web']);
        }

        \$admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        \$admin->syncPermissions(Permission::where('guard_name', 'web')->get());
    }

    public function test_{$snakeName}_can_be_created(): void
    {
        \$owner = User::factory()->create();
        \$record = {$name}::factory()->create(['owner_id' => \$owner->id]);

        \$this->assertDatabaseHas('{$tableName}', ['id' => \$record->id]);
    }

    public function test_{$snakeName}_belongs_to_owner(): void
    {
        \$owner = User::factory()->create();
        \$record = {$name}::factory()->create(['owner_id' => \$owner->id]);

        \$this->assertTrue(\$record->owner->is(\$owner));
    }

    public function test_{$snakeName}_soft_deletes(): void
    {
        \$record = {$name}::factory()->create();
        \$record->delete();

        \$this->assertSoftDeleted('{$tableName}', ['id' => \$record->id]);
    }

    public function test_owner_can_view_own_{$snakeName}(): void
    {
        \$owner = User::factory()->create();
        \$record = {$name}::factory()->create(['owner_id' => \$owner->id]);

        \$this->assertTrue(\$owner->can('view', \$record));
    }

    public function test_admin_can_manage_any_{$snakeName}(): void
    {
        \$admin = User::factory()->create();
        \$admin->assignRole('admin');
        \$record = {$name}::factory()->create();

        \$this->assertTrue(\$admin->can('view', \$record));
        \$this->assertTrue(\$admin->can('update', \$record));
        \$this->assertTrue(\$admin->can('delete', \$record));
    }{$auditTests}{$eventTests}{$scopeTests}
}
PHP;

        $content = str_replace('__NAME__', $name, $content);
        $content = str_replace('__SNAKE__', $snakeName, $content);

        $path = base_path("tests/Feature/Entities/{$name}Test.php");
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        return "tests/Feature/Entities/{$name}Test.php";
    }

    /**
     * @param  array<int, string>  $traits
     */
    protected function generateSmartTest(string $name, array $traits): string
    {
        $hasAuditTrail = in_array('HasAuditTrail', $traits);
        $hasEntityEvents = in_array('HasEntityEvents', $traits);
        $hasStandardScopes = in_array('HasStandardScopes', $traits);

        $snakeName = Str::snake($name);
        $tableName = Str::snake(Str::pluralStudly($name));

        // Collect extra imports
        $extraImports = [];
        $extraTests = '';

        // ForeignId relationship tests
        foreach ($this->fields as $field) {
            if ($field->isForeignKey() && $field->name !== 'owner_id') {
                $relationMethod = $field->relationshipMethodName() ?? Str::camel(str_replace('_id', '', $field->name));
                $relatedModel = $field->relatedModelName() ?? 'User';
                $extraImports[] = "use App\\Models\\{$relatedModel};";

                $extraTests .= <<<PHP

    public function test_{$snakeName}_belongs_to_{$relationMethod}(): void
    {
        \$related = {$relatedModel}::factory()->create();
        \$record = {$name}::factory()->create(['{$field->name}' => \$related->id]);

        \$this->assertTrue(\$record->{$relationMethod}->is(\$related));
    }

PHP;
            }
        }

        // State machine tests
        if (! empty($this->states)) {
            $defaultState = $this->states[0];
            $stateClass = Str::studly($defaultState);
            $extraImports[] = "use App\\States\\{$name}State\\{$name}State;";
            $extraImports[] = "use App\\States\\{$name}State\\{$stateClass};";

            $extraTests .= <<<PHP

    public function test_{$snakeName}_has_default_state(): void
    {
        \$record = {$name}::factory()->create();

        \$this->assertInstanceOf({$stateClass}::class, \$record->status);
    }

PHP;

            // Transition test (first → second state if exists)
            if (count($this->states) >= 2) {
                $secondState = $this->states[1];
                $secondStateClass = Str::studly($secondState);
                $extraImports[] = "use App\\States\\{$name}State\\{$secondStateClass};";

                $extraTests .= <<<PHP

    public function test_{$snakeName}_can_transition_states(): void
    {
        \$record = {$name}::factory()->create();

        \$this->assertInstanceOf({$stateClass}::class, \$record->status);

        \$record->status->transitionTo({$secondStateClass}::class);
        \$record->refresh();

        \$this->assertInstanceOf({$secondStateClass}::class, \$record->status);
    }

PHP;
            }
        }

        // Enum value tests
        foreach ($this->fields as $field) {
            if ($field->isEnum()) {
                $enumClass = $field->typeArgument;
                $extraImports[] = "use App\\Enums\\{$enumClass};";

                $extraTests .= <<<PHP

    public function test_{$snakeName}_has_valid_{$field->name}_values(): void
    {
        \$cases = {$enumClass}::cases();
        \$this->assertNotEmpty(\$cases);

        foreach (\$cases as \$case) {
            \$record = {$name}::factory()->create(['{$field->name}' => \$case]);
            \$this->assertEquals(\$case, \$record->{$field->name});
        }
    }

PHP;
            }
        }

        // SearchableColumns test (uses actual string fields from --fields)
        if ($hasStandardScopes) {
            $stringFields = [];
            foreach ($this->fields as $field) {
                if ($field->type === 'string') {
                    $stringFields[] = $field->name;
                }
            }

            if (! empty($stringFields)) {
                $firstStringField = $stringFields[0];
                $expectedArray = '['.implode(', ', array_map(fn ($f) => "'{$f}'", $stringFields)).']';

                $extraTests .= <<<PHP

    public function test_{$snakeName}_searchable_columns(): void
    {
        \$expected = {$expectedArray};
        \$actual = (new {$name})->searchableColumns();

        \$this->assertEquals(\$expected, \$actual);
    }

    public function test_search_scope_finds_matching_records(): void
    {
        {$name}::factory()->create(['{$firstStringField}' => 'Alpha Unique Value']);
        {$name}::factory()->create(['{$firstStringField}' => 'Beta Unique Value']);

        \$this->assertCount(1, {$name}::search('Alpha')->get());
    }

PHP;
            } else {
                $extraTests .= <<<PHP

    public function test_{$snakeName}_searchable_columns_is_empty(): void
    {
        \$actual = (new {$name})->searchableColumns();

        \$this->assertEquals([], \$actual);
    }

PHP;
            }
        }

        // Trait-based tests (audit trail, entity events, active scope)
        if ($hasAuditTrail) {
            $extraTests .= <<<PHP

    public function test_{$snakeName}_creation_is_logged(): void
    {
        \$record = {$name}::factory()->create();

        \$activity = \\Spatie\\Activitylog\\Models\\Activity::where('subject_type', {$name}::class)
            ->where('subject_id', \$record->id)
            ->where('event', 'created')
            ->first();

        \$this->assertNotNull(\$activity);
    }

PHP;
        }

        if ($hasEntityEvents) {
            $extraTests .= <<<PHP

    public function test_entity_events_are_dispatched(): void
    {
        \\Illuminate\\Support\\Facades\\Event::fake([\\Aicl\\Events\\EntityCreated::class]);

        {$name}::factory()->create();

        \\Illuminate\\Support\\Facades\\Event::assertDispatched(\\Aicl\\Events\\EntityCreated::class);
    }

PHP;
        }

        if ($hasStandardScopes) {
            $extraTests .= <<<PHP

    public function test_active_scope_filters_correctly(): void
    {
        {$name}::factory()->create(['is_active' => true]);
        {$name}::factory()->create(['is_active' => false]);

        \$this->assertCount(1, {$name}::active()->get());
    }

PHP;
        }

        // Build imports (filter out those already in base template)
        $baseImports = [
            "use App\\Models\\{$name};",
            'use App\\Models\\User;',
            'use Illuminate\\Foundation\\Testing\\DatabaseTransactions;',
            'use Spatie\\Permission\\Models\\Permission;',
            'use Spatie\\Permission\\Models\\Role;',
            'use Spatie\\Permission\\PermissionRegistrar;',
            'use Tests\\TestCase;',
        ];
        $extraImports = array_unique($extraImports);
        $extraImports = array_filter($extraImports, fn ($import) => ! in_array($import, $baseImports));
        sort($extraImports);
        $importStr = ! empty($extraImports) ? "\n".implode("\n", $extraImports) : '';

        $content = <<<PHP
<?php

namespace Tests\\Feature\\Entities;

use App\\Models\\{$name};
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\DatabaseTransactions;
use Spatie\\Permission\\Models\\Permission;
use Spatie\\Permission\\Models\\Role;
use Spatie\\Permission\\PermissionRegistrar;
use Tests\\TestCase;{$importStr}

class {$name}Test extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        \$this->seedPermissions();
    }

    protected function seedPermissions(): void
    {
        \$permissions = [
            'ViewAny:{$name}', 'View:{$name}', 'Create:{$name}',
            'Update:{$name}', 'Delete:{$name}',
        ];

        foreach (\$permissions as \$permission) {
            Permission::firstOrCreate(['name' => \$permission, 'guard_name' => 'web']);
        }

        \$admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        \$admin->syncPermissions(Permission::where('guard_name', 'web')->get());
    }

    public function test_{$snakeName}_can_be_created(): void
    {
        \$owner = User::factory()->create();
        \$record = {$name}::factory()->create(['owner_id' => \$owner->id]);

        \$this->assertDatabaseHas('{$tableName}', ['id' => \$record->id]);
    }

    public function test_{$snakeName}_belongs_to_owner(): void
    {
        \$owner = User::factory()->create();
        \$record = {$name}::factory()->create(['owner_id' => \$owner->id]);

        \$this->assertTrue(\$record->owner->is(\$owner));
    }

    public function test_{$snakeName}_soft_deletes(): void
    {
        \$record = {$name}::factory()->create();
        \$record->delete();

        \$this->assertSoftDeleted('{$tableName}', ['id' => \$record->id]);
    }

    public function test_owner_can_view_own_{$snakeName}(): void
    {
        \$owner = User::factory()->create();
        \$record = {$name}::factory()->create(['owner_id' => \$owner->id]);

        \$this->assertTrue(\$owner->can('view', \$record));
    }

    public function test_admin_can_manage_any_{$snakeName}(): void
    {
        \$admin = User::factory()->create();
        \$admin->assignRole('admin');
        \$record = {$name}::factory()->create();

        \$this->assertTrue(\$admin->can('view', \$record));
        \$this->assertTrue(\$admin->can('update', \$record));
        \$this->assertTrue(\$admin->can('delete', \$record));
    }
{$extraTests}}
PHP;

        $path = base_path("tests/Feature/Entities/{$name}Test.php");
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        return "tests/Feature/Entities/{$name}Test.php";
    }

    /**
     * Resolve trait imports, use statements, and interface mappings for a set of traits.
     *
     * Handles HasAiContext injection, base class deduplication, and trait-to-contract mapping.
     *
     * @param  array<int, string>  $traits
     * @return array{traitImports: array<int, string>, traitUses: array<int, string>, interfaces: array<int, string>, interfaceImports: array<int, string>}
     */
    protected function resolveTraitsAndInterfaces(array $traits, bool $aiContext): array
    {
        $traitImports = [];
        $traitUses = [];
        $interfaces = [];
        $interfaceImports = [];

        if ($aiContext) {
            $traitImports[] = 'use Aicl\\Traits\\HasAiContext;';
            $traitUses[] = '    use HasAiContext;';
        }

        foreach ($traits as $trait) {
            // Skip traits already provided by the base class
            if ($this->baseInspector !== null && $this->baseInspector->hasTrait($trait)) {
                continue;
            }

            $traitImports[] = "use Aicl\\Traits\\{$trait};";
            $traitUses[] = "    use {$trait};";

            match ($trait) {
                'HasEntityEvents' => $this->addInterface($interfaces, $interfaceImports, 'HasEntityLifecycle'),
                'HasAuditTrail' => $this->addInterface($interfaces, $interfaceImports, 'Auditable'),
                'HasTagging' => $this->addInterface($interfaces, $interfaceImports, 'Taggable'),
                'HasSearchableFields' => $this->addInterface($interfaces, $interfaceImports, 'Searchable'),
                default => null,
            };
        }

        // Remove contracts already provided by base class
        if ($this->baseInspector !== null) {
            $baseContracts = $this->baseInspector->contracts();
            $interfaces = array_values(array_filter(
                $interfaces,
                fn (string $iface): bool => ! in_array($iface, $baseContracts, true)
            ));
        }

        return compact('traitImports', 'traitUses', 'interfaces', 'interfaceImports');
    }

    /**
     * @param  array<int, string>  $interfaces
     * @param  array<int, string>  $imports
     */
    protected function addInterface(array &$interfaces, array &$imports, string $interface): void
    {
        if (! in_array($interface, $interfaces)) {
            $interfaces[] = $interface;
            $imports[] = "use Aicl\\Contracts\\{$interface};";
        }
    }

    /**
     * @param  array<int, string>  $interfaces
     * @param  array<int, string>  $imports
     */
    protected function addExternalInterface(array &$interfaces, array &$imports, string $interface, string $fqcn): void
    {
        if (! in_array($interface, $interfaces)) {
            $interfaces[] = $interface;
            $imports[] = "use {$fqcn};";
        }
    }

    // ========================================================================
    // Smart Filament Form Generation
    // ========================================================================

    protected function buildSmartFormSchema(string $name): string
    {
        $sections = [];

        // Inherited Fields section (from base class)
        if ($this->baseInspector !== null) {
            $inheritedFields = [];
            foreach ($this->baseInspector->columns() as $baseField) {
                if ($baseField->type === 'boolean' || $baseField->isForeignKey()) {
                    continue; // These go in settings section
                }
                $inheritedFields[] = $this->getFormComponentForField($baseField, $name);
            }

            if (! empty($inheritedFields)) {
                $inheritedStr = implode(",\n", array_map(fn ($s) => rtrim($s, ','), $inheritedFields));
                $sections[] = <<<PHP
            Section::make('Inherited Fields')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
{$inheritedStr},
                ]),
PHP;
            }
        }

        // Child entity detail fields
        $detailFields = [];
        $settingsFields = [];

        foreach ($this->fields as $field) {
            if ($field->type === 'boolean' || $field->isForeignKey()) {
                $settingsFields[] = $this->getFormComponentForField($field, $name);
            } else {
                $detailFields[] = $this->getFormComponentForField($field, $name);
            }
        }

        // State machine select
        if (! empty($this->states)) {
            $stateLabels = [];
            foreach ($this->states as $state) {
                $label = Str::title(str_replace('_', ' ', $state));
                $stateLabels[] = "                        '{$state}' => '{$label}',";
            }
            $stateLabelsStr = implode("\n", $stateLabels);
            $detailFields[] = <<<PHP
                    Select::make('status')
                        ->options([
{$stateLabelsStr}
                        ])
                        ->required()
PHP;
        }

        // Check if base class provides is_active / owner_id
        $baseHasIsActive = $this->baseInspector !== null && $this->baseInspector->hasColumn('is_active');
        $baseHasOwnerId = $this->baseInspector !== null && $this->baseInspector->hasColumn('owner_id');

        // Always add is_active + owner_id to settings if not in explicit fields and not from base
        $hasExplicitIsActive = false;
        $hasExplicitOwnerId = false;
        foreach ($this->fields as $field) {
            if ($field->name === 'is_active') {
                $hasExplicitIsActive = true;
            }
            if ($field->name === 'owner_id') {
                $hasExplicitOwnerId = true;
            }
        }

        // Add base class boolean/foreignId fields to settings section
        if ($this->baseInspector !== null) {
            foreach ($this->baseInspector->columns() as $baseField) {
                if ($baseField->type === 'boolean' || $baseField->isForeignKey()) {
                    $settingsFields[] = $this->getFormComponentForField($baseField, $name);
                }
            }
        }

        if (! $hasExplicitOwnerId && ! $baseHasOwnerId) {
            $settingsFields[] = <<<'PHP'
                    Select::make('owner_id')
                        ->relationship('owner', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
PHP;
        }

        if (! $hasExplicitIsActive && ! $baseHasIsActive) {
            $settingsFields[] = <<<'PHP'
                    Toggle::make('is_active')
                        ->default(true)
PHP;
        }

        if (! empty($detailFields)) {
            $detailStr = implode(",\n", array_map(fn ($s) => rtrim($s, ','), $detailFields));
            $sections[] = <<<PHP
            Section::make('{$name} Details')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
{$detailStr},
                ]),
PHP;
        }

        $settingsStr = implode(",\n", array_map(fn ($s) => rtrim($s, ','), $settingsFields));
        $sections[] = <<<PHP
            Section::make('Settings')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
{$settingsStr},
                ]),
PHP;

        return implode("\n\n", $sections);
    }

    protected function getFormComponentForField(FieldDefinition $field, string $name): string
    {
        $nullable = $field->nullable ? '' : "\n                        ->required()";
        $nullableChain = $field->nullable ? '' : '->required()';

        return match ($field->type) {
            'string' => "                    TextInput::make('{$field->name}'){$nullable}\n                        ->maxLength(255)",
            'text' => "                    RichEditor::make('{$field->name}')\n                        ->columnSpanFull()",
            'integer' => "                    TextInput::make('{$field->name}')\n                        ->numeric(){$nullable}",
            'float' => "                    TextInput::make('{$field->name}')\n                        ->numeric()\n                        ->prefix('\$')",
            'boolean' => "                    Toggle::make('{$field->name}')\n                        ->default(".($field->default ?? 'true').')',
            'date' => "                    DatePicker::make('{$field->name}')",
            'datetime' => "                    DateTimePicker::make('{$field->name}')",
            'enum' => "                    Select::make('{$field->name}')\n                        ->options({$field->typeArgument}::class){$nullable}",
            'json' => "                    KeyValue::make('{$field->name}')",
            'foreignId' => "                    Select::make('{$field->name}')\n                        ->relationship('{$field->relationshipMethodName()}', 'name'){$nullable}\n                        ->searchable()\n                        ->preload()",
            default => "                    TextInput::make('{$field->name}')",
        };
    }

    // ========================================================================
    // Smart Filament Infolist Generation
    // ========================================================================

    protected function buildSmartInfolistSchema(string $name): string
    {
        $sections = [];

        // Inherited Fields section (from base class)
        if ($this->baseInspector !== null) {
            $inheritedEntries = [];
            foreach ($this->baseInspector->columns() as $baseField) {
                if ($baseField->type === 'boolean' || $baseField->isForeignKey()) {
                    continue; // These go in settings section
                }
                $inheritedEntries[] = $this->getInfolistEntryForField($baseField);
            }

            if (! empty($inheritedEntries)) {
                $inheritedStr = implode(",\n", array_map(fn ($s) => rtrim($s, ','), $inheritedEntries));
                $sections[] = <<<PHP
            Section::make('Inherited Fields')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
{$inheritedStr},
                ]),
PHP;
            }
        }

        // Child entity detail entries
        $detailEntries = [];
        $settingsEntries = [];

        foreach ($this->fields as $field) {
            if ($field->type === 'boolean' || $field->isForeignKey()) {
                $settingsEntries[] = $this->getInfolistEntryForField($field);
            } else {
                $detailEntries[] = $this->getInfolistEntryForField($field);
            }
        }

        // State machine entry
        if (! empty($this->states)) {
            $detailEntries[] = "                    TextEntry::make('status')\n                        ->badge()";
        }

        // Check if base class provides is_active / owner_id
        $baseHasIsActive = $this->baseInspector !== null && $this->baseInspector->hasColumn('is_active');
        $baseHasOwnerId = $this->baseInspector !== null && $this->baseInspector->hasColumn('owner_id');

        $hasExplicitIsActive = false;
        $hasExplicitOwnerId = false;
        foreach ($this->fields as $field) {
            if ($field->name === 'is_active') {
                $hasExplicitIsActive = true;
            }
            if ($field->name === 'owner_id') {
                $hasExplicitOwnerId = true;
            }
        }

        // Add base class boolean/foreignId entries to settings section
        if ($this->baseInspector !== null) {
            foreach ($this->baseInspector->columns() as $baseField) {
                if ($baseField->type === 'boolean' || $baseField->isForeignKey()) {
                    $settingsEntries[] = $this->getInfolistEntryForField($baseField);
                }
            }
        }

        if (! $hasExplicitOwnerId && ! $baseHasOwnerId) {
            $settingsEntries[] = "                    TextEntry::make('owner.name')\n                        ->label('Owner')";
        }

        if (! $hasExplicitIsActive && ! $baseHasIsActive) {
            $settingsEntries[] = "                    IconEntry::make('is_active')\n                        ->boolean()";
        }

        if (! empty($detailEntries)) {
            $detailStr = implode(",\n", array_map(fn ($s) => rtrim($s, ','), $detailEntries));
            $sections[] = <<<PHP
            Section::make('{$name} Details')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
{$detailStr},
                ]),
PHP;
        }

        $settingsStr = implode(",\n", array_map(fn ($s) => rtrim($s, ','), $settingsEntries));
        $sections[] = <<<PHP
            Section::make('Settings')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
{$settingsStr},
                ]),
PHP;

        return implode("\n\n", $sections);
    }

    protected function getInfolistEntryForField(FieldDefinition $field): string
    {
        return match ($field->type) {
            'string' => "                    TextEntry::make('{$field->name}')",
            'text' => "                    TextEntry::make('{$field->name}')\n                        ->html()\n                        ->columnSpanFull()",
            'integer' => "                    TextEntry::make('{$field->name}')\n                        ->numeric()",
            'float' => "                    TextEntry::make('{$field->name}')\n                        ->numeric()",
            'boolean' => "                    IconEntry::make('{$field->name}')\n                        ->boolean()",
            'date' => "                    TextEntry::make('{$field->name}')\n                        ->date()",
            'datetime' => "                    TextEntry::make('{$field->name}')\n                        ->dateTime()",
            'enum' => "                    TextEntry::make('{$field->name}')\n                        ->badge()",
            'json' => "                    KeyValueEntry::make('{$field->name}')\n                        ->columnSpanFull()",
            'foreignId' => "                    TextEntry::make('{$field->relationshipMethodName()}.name')\n                        ->label('".Str::title(str_replace('_', ' ', Str::beforeLast($field->name, '_id')))."')",
            default => "                    TextEntry::make('{$field->name}')",
        };
    }

    protected function getSmartInfolistImports(string $name): string
    {
        $imports = [
            'use Filament\\Infolists\\Components\\TextEntry;',
            'use Filament\\Schemas\\Components\\Section;',
            'use Filament\\Schemas\\Schema;',
        ];

        $allFields = $this->fields;
        if ($this->baseInspector !== null) {
            $allFields = array_merge($this->baseInspector->columns(), $allFields);
        }

        $hasBoolean = false;
        $hasJson = false;

        foreach ($allFields as $field) {
            if ($field->type === 'boolean') {
                $hasBoolean = true;
            }
            if ($field->type === 'json') {
                $hasJson = true;
            }
        }

        // Always include IconEntry for is_active
        $imports[] = 'use Filament\\Infolists\\Components\\IconEntry;';

        if ($hasJson) {
            $imports[] = 'use Filament\\Infolists\\Components\\KeyValueEntry;';
        }

        $imports = array_unique($imports);
        sort($imports);

        return implode("\n", $imports);
    }

    // ========================================================================
    // Smart Filament Table Generation
    // ========================================================================

    protected function buildSmartTableColumns(string $name): string
    {
        $columns = [];
        $filters = [];
        $isFirstString = true;

        foreach ($this->fields as $field) {
            $col = $this->getTableColumnForField($field, $name, $isFirstString);
            if ($col !== null) {
                $columns[] = $col;
            }

            $filter = $this->getTableFilterForField($field, $name);
            if ($filter !== null) {
                $filters[] = $filter;
            }

            if ($field->type === 'string' && $isFirstString) {
                $isFirstString = false;
            }
        }

        // State machine column
        if (! empty($this->states)) {
            $columns[] = "                TextColumn::make('status')\n                    ->badge()\n                    ->formatStateUsing(fn (\$state) => \$state->label())\n                    ->color(fn (\$state) => \$state->color())";
            $stateFilterOptions = [];
            foreach ($this->states as $state) {
                $label = Str::title(str_replace('_', ' ', $state));
                $stateFilterOptions[] = "                        '{$state}' => '{$label}',";
            }
            $stateOptionsStr = implode("\n", $stateFilterOptions);
            $filters[] = "                SelectFilter::make('status')\n                    ->options([\n{$stateOptionsStr}\n                    ])";
        }

        // Always add is_active if not in explicit fields
        $hasExplicitIsActive = false;
        foreach ($this->fields as $field) {
            if ($field->name === 'is_active') {
                $hasExplicitIsActive = true;
            }
        }
        if (! $hasExplicitIsActive) {
            $columns[] = "                IconColumn::make('is_active')\n                    ->boolean()";
            $filters[] = "                TernaryFilter::make('is_active')\n                    ->label('Active')";
        }

        // Owner column
        $columns[] = "                TextColumn::make('owner.name')\n                    ->label('Owner')\n                    ->sortable()";

        // Timestamps
        $columns[] = "                TextColumn::make('created_at')\n                    ->dateTime()\n                    ->sortable()\n                    ->toggleable(isToggledHiddenByDefault: true)";

        $columnsStr = implode(",\n", $columns);
        $filtersStr = implode(",\n", $filters);

        return "columns:{$columnsStr}\nfilters:{$filtersStr}";
    }

    protected function getTableColumnForField(FieldDefinition $field, string $name, bool $isFirstString): ?string
    {
        $weightBold = ($field->type === 'string' && $isFirstString) ? "\n                    ->weight('bold')" : '';

        return match ($field->type) {
            'string' => "                TextColumn::make('{$field->name}')\n                    ->searchable()\n                    ->sortable(){$weightBold}",
            'text' => "                TextColumn::make('{$field->name}')\n                    ->limit(50)\n                    ->toggleable(isToggledHiddenByDefault: true)",
            'integer' => "                TextColumn::make('{$field->name}')\n                    ->numeric()\n                    ->sortable()",
            'float' => "                TextColumn::make('{$field->name}')\n                    ->money('usd')\n                    ->sortable()",
            'boolean' => "                IconColumn::make('{$field->name}')\n                    ->boolean()",
            'date' => "                TextColumn::make('{$field->name}')\n                    ->date()\n                    ->sortable()",
            'datetime' => "                TextColumn::make('{$field->name}')\n                    ->dateTime()\n                    ->sortable()",
            'enum' => "                TextColumn::make('{$field->name}')\n                    ->badge()\n                    ->color(fn (\$state) => \$state?->color())",
            'json' => null,
            'foreignId' => "                TextColumn::make('{$field->relationshipMethodName()}.name')\n                    ->label('".Str::title(str_replace('_', ' ', $field->relationshipMethodName()))."')\n                    ->sortable()",
            default => "                TextColumn::make('{$field->name}')",
        };
    }

    protected function getTableFilterForField(FieldDefinition $field, string $name): ?string
    {
        return match ($field->type) {
            'boolean' => "                TernaryFilter::make('{$field->name}')",
            'enum' => "                SelectFilter::make('{$field->name}')\n                    ->options({$field->typeArgument}::class)",
            'foreignId' => "                SelectFilter::make('{$field->name}')\n                    ->relationship('{$field->relationshipMethodName()}', 'name')",
            default => null,
        };
    }

    // ========================================================================
    // Smart Form Request Generation
    // ========================================================================

    /**
     * @return array<string, string>
     */
    protected function buildSmartStoreRules(string $name, string $tableName): array
    {
        $rules = [];

        foreach ($this->fields as $field) {
            $rules[$field->name] = $this->getValidationRuleForField($field, $tableName, false);
        }

        if (! empty($this->states)) {
            $rules['status'] = "['nullable', 'string']";
        }

        $hasExplicitIsActive = false;
        foreach ($this->fields as $field) {
            if ($field->name === 'is_active') {
                $hasExplicitIsActive = true;
            }
        }
        if (! $hasExplicitIsActive) {
            $rules['is_active'] = "['boolean']";
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function buildSmartUpdateRules(string $name, string $tableName): array
    {
        $rules = [];

        foreach ($this->fields as $field) {
            $rule = $this->getValidationRuleForField($field, $tableName, true);
            $rules[$field->name] = $rule;
        }

        if (! empty($this->states)) {
            $rules['status'] = "['sometimes', 'nullable', 'string']";
        }

        $hasExplicitIsActive = false;
        foreach ($this->fields as $field) {
            if ($field->name === 'is_active') {
                $hasExplicitIsActive = true;
            }
        }
        if (! $hasExplicitIsActive) {
            $rules['is_active'] = "['boolean']";
        }

        return $rules;
    }

    protected function getValidationRuleForField(FieldDefinition $field, string $tableName, bool $isUpdate): string
    {
        $prefix = $isUpdate ? "'sometimes', " : '';

        $rule = match ($field->type) {
            'string' => $field->nullable
                ? "['nullable', 'string', 'max:255']"
                : "[{$prefix}'required', 'string', 'max:255']",
            'text' => "['nullable', 'string']",
            'integer' => $field->nullable
                ? "['nullable', 'integer']"
                : "[{$prefix}'required', 'integer']",
            'float' => "['nullable', 'numeric', 'min:0']",
            'boolean' => "['boolean']",
            'date' => "['nullable', 'date']",
            'datetime' => "['nullable', 'date']",
            'enum' => $field->nullable
                ? "['nullable', Rule::enum({$field->typeArgument}::class)]"
                : "[{$prefix}'required', Rule::enum({$field->typeArgument}::class)]",
            'json' => "['nullable', 'array']",
            'foreignId' => $field->nullable
                ? "['nullable', 'exists:{$field->typeArgument},id']"
                : "[{$prefix}'required', 'exists:{$field->typeArgument},id']",
            default => "['nullable', 'string']",
        };

        if ($field->unique && ! $isUpdate) {
            $rule = str_replace(']', ", 'unique:{$tableName},{$field->name}']", $rule);
        }

        return $rule;
    }

    // ========================================================================
    // Smart API Resource Generation
    // ========================================================================

    protected function buildSmartResourceFields(string $name): string
    {
        $lines = ["            'id' => \$this->id,"];

        foreach ($this->fields as $field) {
            $lines[] = '            '.$this->getResourceFieldForField($field);
        }

        if (! empty($this->states)) {
            $lines[] = "            'status' => \$this->status ? ['value' => (string) \$this->status, 'label' => \$this->status->label()] : null,";
        }

        // Always add is_active + owner
        $hasExplicitIsActive = false;
        $hasExplicitOwnerId = false;
        foreach ($this->fields as $field) {
            if ($field->name === 'is_active') {
                $hasExplicitIsActive = true;
            }
            if ($field->name === 'owner_id') {
                $hasExplicitOwnerId = true;
            }
        }
        if (! $hasExplicitIsActive) {
            $lines[] = "            'is_active' => \$this->is_active,";
        }
        if (! $hasExplicitOwnerId) {
            $lines[] = "            'owner' => \$this->whenLoaded('owner', fn () => [";
            $lines[] = "                'id' => \$this->owner->id,";
            $lines[] = "                'name' => \$this->owner->name,";
            $lines[] = '            ]),';
        }

        $lines[] = "            'created_at' => \$this->created_at?->toIso8601String(),";
        $lines[] = "            'updated_at' => \$this->updated_at?->toIso8601String(),";

        return implode("\n", $lines);
    }

    protected function getResourceFieldForField(FieldDefinition $field): string
    {
        return match ($field->type) {
            'date' => "'{$field->name}' => \$this->{$field->name}?->toDateString(),",
            'datetime' => "'{$field->name}' => \$this->{$field->name}?->toIso8601String(),",
            'enum' => "'{$field->name}' => \$this->{$field->name}?->value,",
            'foreignId' => "'{$field->relationshipMethodName()}' => \$this->whenLoaded('{$field->relationshipMethodName()}', fn () => [\n                'id' => \$this->{$field->relationshipMethodName()}->id,\n                'name' => \$this->{$field->relationshipMethodName()}->name,\n            ]),",
            default => "'{$field->name}' => \$this->{$field->name},",
        };
    }

    // ========================================================================
    // Smart Exporter Generation
    // ========================================================================

    protected function buildSmartExportColumns(string $name): string
    {
        $lines = ["            ExportColumn::make('id')->label('ID'),"];

        foreach ($this->fields as $field) {
            $col = $this->getExportColumnForField($field);
            if ($col !== null) {
                $lines[] = '            '.$col;
            }
        }

        if (! empty($this->states)) {
            $lines[] = "            ExportColumn::make('status')->formatStateUsing(fn (\$state) => \$state instanceof \\Stringable ? (string) \$state : \$state),";
        }

        $lines[] = "            ExportColumn::make('owner.name')->label('Owner'),";
        $lines[] = "            ExportColumn::make('created_at'),";

        return implode("\n", $lines);
    }

    protected function getExportColumnForField(FieldDefinition $field): ?string
    {
        return match ($field->type) {
            'json' => null,
            'enum' => "ExportColumn::make('{$field->name}')->formatStateUsing(fn (\$state) => \$state instanceof \\BackedEnum ? \$state->value : \$state),",
            'foreignId' => "ExportColumn::make('{$field->relationshipMethodName()}.name')->label('".Str::title(str_replace('_', ' ', $field->relationshipMethodName()))."'),",
            default => "ExportColumn::make('{$field->name}'),",
        };
    }

    // ========================================================================
    // Enum Generation
    // ========================================================================

    protected function generateEnum(string $entityName, FieldDefinition $field): string
    {
        $enumName = $field->typeArgument;

        // Check for rich enum data from spec file
        if (! empty($this->specEnums[$enumName])) {
            return $this->generateEnumFromSpec($enumName, $this->specEnums[$enumName]);
        }

        $content = <<<PHP
<?php

namespace App\\Enums;

enum {$enumName}: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match (\$this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
        };
    }

    public function color(): string
    {
        return match (\$this) {
            self::Low => 'gray',
            self::Medium => 'warning',
            self::High => 'danger',
        };
    }
}
PHP;

        $dir = app_path('Enums');
        $this->ensureDirectoryExists($dir);
        file_put_contents("{$dir}/{$enumName}.php", $content);

        return "app/Enums/{$enumName}.php";
    }

    /**
     * Generate an enum class from rich spec file data.
     *
     * @param  array<int, array{case: string, label: string, color?: string, icon?: string}>  $cases
     */
    protected function generateEnumFromSpec(string $enumName, array $cases): string
    {
        $caseLines = [];
        $labelLines = [];
        $colorLines = [];
        $hasColor = false;
        $iconLines = [];
        $hasIcon = false;

        foreach ($cases as $entry) {
            $caseName = Str::studly($entry['case']);
            $caseValue = Str::snake($entry['case']);
            $label = $entry['label'];

            $caseLines[] = "    case {$caseName} = '{$caseValue}';";
            $labelLines[] = "            self::{$caseName} => '{$label}',";

            if (isset($entry['color']) && $entry['color'] !== '') {
                $hasColor = true;
                $colorLines[] = "            self::{$caseName} => '{$entry['color']}',";
            }

            if (isset($entry['icon']) && $entry['icon'] !== '') {
                $hasIcon = true;
                $iconLines[] = "            self::{$caseName} => '{$entry['icon']}',";
            }
        }

        $casesStr = implode("\n", $caseLines);
        $labelMatchStr = implode("\n", $labelLines);

        $methods = "    public function label(): string\n";
        $methods .= "    {\n";
        $methods .= "        return match (\$this) {\n";
        $methods .= $labelMatchStr."\n";
        $methods .= "        };\n";
        $methods .= '    }';

        if ($hasColor) {
            $colorMatchStr = implode("\n", $colorLines);
            $methods .= "\n\n    public function color(): string\n";
            $methods .= "    {\n";
            $methods .= "        return match (\$this) {\n";
            $methods .= $colorMatchStr."\n";
            $methods .= "        };\n";
            $methods .= '    }';
        }

        if ($hasIcon) {
            $iconMatchStr = implode("\n", $iconLines);
            $methods .= "\n\n    public function icon(): string\n";
            $methods .= "    {\n";
            $methods .= "        return match (\$this) {\n";
            $methods .= $iconMatchStr."\n";
            $methods .= "        };\n";
            $methods .= '    }';
        }

        $content = "<?php\n\nnamespace App\\Enums;\n\nenum {$enumName}: string\n{\n{$casesStr}\n\n{$methods}\n}\n";

        $dir = app_path('Enums');
        $this->ensureDirectoryExists($dir);
        file_put_contents("{$dir}/{$enumName}.php", $content);

        return "app/Enums/{$enumName}.php";
    }

    // ========================================================================
    // State Machine Generation
    // ========================================================================

    /**
     * @return array<int, string>
     */
    protected function generateStateMachine(string $name): array
    {
        $files = [];
        $colors = ['gray', 'success', 'warning', 'info', 'danger'];
        $icons = ['pencil-square', 'play', 'pause', 'check-circle', 'archive-box'];

        // Abstract state class
        $transitionLines = [];
        for ($i = 0; $i < count($this->states) - 1; $i++) {
            $fromClass = Str::studly($this->states[$i]);
            $toClass = Str::studly($this->states[$i + 1]);
            $transitionLines[] = "                    {$fromClass}::class => [{$toClass}::class],";
        }
        $transitionsStr = implode("\n", $transitionLines);

        $stateImports = [];
        foreach ($this->states as $state) {
            $className = Str::studly($state);
            $stateImports[] = "use App\\States\\{$name}\\{$className};";
        }
        $stateImportsStr = implode("\n", $stateImports);

        $defaultState = Str::studly($this->states[0]);

        $abstractContent = <<<PHP
<?php

namespace App\\States;

{$stateImportsStr}
use Spatie\\ModelStates\\State;
use Spatie\\ModelStates\\StateConfig;

abstract class {$name}State extends State
{
    abstract public function label(): string;

    abstract public function color(): string;

    abstract public function icon(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default({$defaultState}::class)
            ->allowTransitions([
{$transitionsStr}
                ]);
    }
}
PHP;

        $dir = app_path('States');
        $this->ensureDirectoryExists($dir);
        file_put_contents("{$dir}/{$name}State.php", $abstractContent);
        $files[] = "app/States/{$name}State.php";

        // Concrete state classes
        $stateDir = app_path("States/{$name}");
        $this->ensureDirectoryExists($stateDir);

        foreach ($this->states as $index => $state) {
            $className = Str::studly($state);
            $label = Str::title(str_replace('_', ' ', $state));
            $color = $colors[$index % count($colors)];
            $icon = $icons[$index % count($icons)];

            $concreteContent = <<<PHP
<?php

namespace App\\States\\{$name};

use App\\States\\{$name}State;

class {$className} extends {$name}State
{
    public function label(): string
    {
        return '{$label}';
    }

    public function color(): string
    {
        return '{$color}';
    }

    public function icon(): string
    {
        return 'heroicon-o-{$icon}';
    }
}
PHP;

            file_put_contents("{$stateDir}/{$className}.php", $concreteContent);
            $files[] = "app/States/{$name}/{$className}.php";
        }

        return $files;
    }

    // ========================================================================
    // Widget Stubs
    // ========================================================================

    /**
     * @return array<int, string>
     */
    protected function generateWidgets(string $name): array
    {
        // Use structured widget specs if available
        if ($this->entitySpec !== null && $this->entitySpec->hasStructuredWidgets()) {
            return $this->generateStructuredWidgets($name, $this->entitySpec);
        }

        return $this->generateLegacyWidgets($name);
    }

    /**
     * Generate widgets from structured WidgetSpec definitions.
     *
     * @return array<int, string>
     */
    protected function generateStructuredWidgets(string $name, EntitySpec $spec): array
    {
        $files = [];
        $dir = app_path('Filament/Widgets');
        $this->ensureDirectoryExists($dir);

        $queryParser = new WidgetQueryParser(
            modelName: $name,
            states: $spec->states,
            enums: $spec->enums,
        );

        $sort = 1;

        foreach ($spec->widgetSpecs as $widget) {
            $files = match ($widget->type) {
                'stats' => array_merge($files, $this->generateStructuredStatsWidget($name, $widget, $queryParser, $dir, $sort++)),
                'chart' => array_merge($files, $this->generateStructuredChartWidget($name, $widget, $spec, $dir, $sort++)),
                default => array_merge($files, $this->generateStructuredTableWidget($name, $widget, $queryParser, $dir, $sort++)),
            };
        }

        return $files;
    }

    /**
     * @return array<int, string>
     */
    protected function generateStructuredStatsWidget(
        string $name,
        WidgetSpec $widget,
        WidgetQueryParser $queryParser,
        string $dir,
        int $sort,
    ): array {
        $statLines = [];

        foreach ($widget->metrics as $metric) {
            $queryCode = $queryParser->parseAggregate($metric->query);
            $statLine = "            Stat::make('{$metric->label}', {$queryCode})";

            // Add static color
            if ($metric->color !== '' && $metric->color !== 'primary') {
                $statLine .= "\n                ->color('{$metric->color}')";
            }

            // Add conditional color
            $conditionExpr = WidgetQueryParser::parseConditionColor($metric->conditionColor ?? '');
            if ($conditionExpr !== null) {
                // Wrap in a closure that evaluates the stat value
                $statLine .= "\n                ->color(fn (): string => ({$queryCode}) > 0 ? '{$this->extractTrueColor($metric->conditionColor)}' : '{$this->extractFalseColor($metric->conditionColor)}')";
            }

            $statLines[] = $statLine.',';
        }

        $statsBody = implode("\n", $statLines);

        $content = <<<PHP
<?php

namespace App\\Filament\\Widgets;

use App\\Models\\{$name};
use Filament\\Widgets\\StatsOverviewWidget;
use Filament\\Widgets\\StatsOverviewWidget\\Stat;
use Livewire\\Attributes\\On;

class {$name}StatsOverview extends StatsOverviewWidget
{
    protected static ?int \$sort = {$sort};

    protected ?string \$pollingInterval = '60s';

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        // Stats will refresh on next poll
    }

    protected function getStats(): array
    {
        return [
{$statsBody}
        ];
    }
}
PHP;

        file_put_contents("{$dir}/{$name}StatsOverview.php", $content);

        return ["app/Filament/Widgets/{$name}StatsOverview.php"];
    }

    /**
     * @return array<int, string>
     */
    protected function generateStructuredChartWidget(
        string $name,
        WidgetSpec $widget,
        EntitySpec $spec,
        string $dir,
        int $sort,
    ): array {
        $pluralName = Str::pluralStudly($name);
        $chartType = $widget->chartType ?? 'doughnut';
        $groupBy = $widget->groupBy ?? 'status';

        // Build data arrays from colors mapping
        $dataLines = [];
        $labelLines = [];
        $colorLines = [];

        if (! empty($widget->colors)) {
            foreach ($widget->colors as $stateValue => $colorName) {
                $resolvedColor = $this->filamentColorToHex($colorName);

                // Determine the where condition based on states vs enums
                if (! empty($spec->states) && in_array($stateValue, $spec->states, true)) {
                    $stateClass = Str::studly($stateValue);
                    $dataLines[] = "                {$name}::query()->where('{$groupBy}', {$stateClass}::getMorphClass())->count()";
                } else {
                    $dataLines[] = "                {$name}::query()->where('{$groupBy}', '{$stateValue}')->count()";
                }

                $labelLines[] = "'".Str::headline($stateValue)."'";
                $colorLines[] = "'{$resolvedColor}'";
            }
        } else {
            $dataLines[] = "                {$name}::query()->count()";
            $labelLines[] = "'All'";
            $colorLines[] = "'#3b82f6'";
        }

        $dataBody = implode(",\n", $dataLines);
        $labelsBody = implode(', ', $labelLines);
        $colorsBody = implode(', ', $colorLines);

        // Build state imports
        $stateImports = '';
        if (! empty($spec->states) && ! empty($widget->colors)) {
            $imports = [];

            foreach (array_keys($widget->colors) as $stateValue) {
                if (in_array($stateValue, $spec->states, true)) {
                    $stateClass = Str::studly($stateValue);
                    $imports[] = "use App\\States\\{$name}\\{$stateClass};";
                }
            }

            if (! empty($imports)) {
                $stateImports = "\n".implode("\n", $imports);
            }
        }

        $content = <<<PHP
<?php

namespace App\\Filament\\Widgets;

use App\\Models\\{$name};
use Filament\\Widgets\\ChartWidget;
use Livewire\\Attributes\\On;{$stateImports}

class {$name}ByStatusChart extends ChartWidget
{
    protected ?string \$heading = '{$pluralName} by Status';

    protected static ?int \$sort = {$sort};

    protected ?string \$pollingInterval = '60s';

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        \$this->updateChartData();
    }

    protected function getType(): string
    {
        return '{$chartType}';
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'data' => [
{$dataBody},
                    ],
                    'backgroundColor' => [{$colorsBody}],
                ],
            ],
            'labels' => [{$labelsBody}],
        ];
    }
}
PHP;

        file_put_contents("{$dir}/{$name}ByStatusChart.php", $content);

        return ["app/Filament/Widgets/{$name}ByStatusChart.php"];
    }

    /**
     * @return array<int, string>
     */
    protected function generateStructuredTableWidget(
        string $name,
        WidgetSpec $widget,
        WidgetQueryParser $queryParser,
        string $dir,
        int $sort,
    ): array {
        $widgetClassName = Str::studly(str_replace(' ', '', $widget->name)).'Widget';
        $queryCode = $widget->query !== null
            ? $queryParser->parseTableQuery($widget->query)
            : "{$name}::query()->latest()->limit(5)";

        // Build columns
        $columnLines = [];

        foreach ($widget->columns as $column) {
            $formatChain = WidgetQueryParser::columnFormatToFilament($column->format);
            $label = Str::headline(str_replace('.', ' ', $column->name));
            $columnLines[] = "                TextColumn::make('{$column->name}')->label('{$label}'){$formatChain},";
        }

        if (empty($columnLines)) {
            $columnLines[] = "                TextColumn::make('id')->label('ID'),";
            $columnLines[] = "                TextColumn::make('owner.name')->label('Owner'),";
            $columnLines[] = "                TextColumn::make('created_at')->dateTime(),";
        }

        $columnsBody = implode("\n", $columnLines);

        $content = <<<PHP
<?php

namespace App\\Filament\\Widgets;

use App\\Models\\{$name};
use Filament\\Tables\\Columns\\TextColumn;
use Filament\\Tables\\Table;
use Filament\\Widgets\\TableWidget;
use Livewire\\Attributes\\On;

class {$widgetClassName} extends TableWidget
{
    protected static ?int \$sort = {$sort};

    protected int|string|array \$columnSpan = 'full';

    protected ?string \$pollingInterval = '60s';

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        // Table will refresh on next poll
    }

    public function table(Table \$table): Table
    {
        return \$table
            ->heading('{$widget->name}')
            ->query({$queryCode})
            ->columns([
{$columnsBody}
            ])
            ->paginated(false);
    }
}
PHP;

        file_put_contents("{$dir}/{$widgetClassName}.php", $content);

        return ["app/Filament/Widgets/{$widgetClassName}.php"];
    }

    /**
     * Extract the "true" color from a condition color expression.
     */
    protected function extractTrueColor(?string $expression): string
    {
        if ($expression !== null && preg_match('/:\s*(\w+),/', $expression, $m)) {
            return $m[1];
        }

        return 'primary';
    }

    /**
     * Extract the "false" color from a condition color expression.
     */
    protected function extractFalseColor(?string $expression): string
    {
        if ($expression !== null && preg_match('/else:\s*(\w+)/', $expression, $m)) {
            return $m[1];
        }

        return 'gray';
    }

    /**
     * Convert a Filament color name to a hex value for chart backgrounds.
     */
    protected function filamentColorToHex(string $color): string
    {
        return match ($color) {
            'primary' => '#6366f1',
            'success' => '#10b981',
            'warning' => '#f59e0b',
            'danger' => '#ef4444',
            'info' => '#3b82f6',
            'gray' => '#6b7280',
            'secondary' => '#64748b',
            default => '#'.ltrim($color, '#'),
        };
    }

    /**
     * Generate legacy stub widgets (backward-compatible behavior).
     *
     * @return array<int, string>
     */
    protected function generateLegacyWidgets(string $name): array
    {
        $files = [];
        $pluralName = Str::pluralStudly($name);
        $dir = app_path('Filament/Widgets');
        $this->ensureDirectoryExists($dir);

        // Stats Overview Widget
        $statsContent = <<<PHP
<?php

namespace App\\Filament\\Widgets;

use App\\Models\\{$name};
use Filament\\Widgets\\StatsOverviewWidget;
use Filament\\Widgets\\StatsOverviewWidget\\Stat;
use Livewire\\Attributes\\On;

class {$name}StatsOverview extends StatsOverviewWidget
{
    protected static ?int \$sort = 1;

    protected ?string \$pollingInterval = '60s';

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        // Stats will refresh on next poll
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Total {$pluralName}', {$name}::query()->count()),
            Stat::make('Active', {$name}::query()->where('is_active', true)->count()),
        ];
    }
}
PHP;

        file_put_contents("{$dir}/{$name}StatsOverview.php", $statsContent);
        $files[] = "app/Filament/Widgets/{$name}StatsOverview.php";

        // Chart Widget (only if enum or states)
        $hasEnumOrStates = ! empty($this->states);
        if (! $hasEnumOrStates) {
            foreach ($this->fields as $field) {
                if ($field->isEnum()) {
                    $hasEnumOrStates = true;

                    break;
                }
            }
        }

        if ($hasEnumOrStates) {
            $chartContent = <<<PHP
<?php

namespace App\\Filament\\Widgets;

use App\\Models\\{$name};
use Filament\\Widgets\\ChartWidget;
use Livewire\\Attributes\\On;

class {$name}ByStatusChart extends ChartWidget
{
    protected ?string \$heading = '{$pluralName} by Status';

    protected static ?int \$sort = 2;

    protected ?string \$pollingInterval = '60s';

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        \$this->updateChartData();
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        // TODO: Customize with actual status/enum queries
        return [
            'datasets' => [
                [
                    'data' => [{$name}::query()->count()],
                    'backgroundColor' => ['#3b82f6'],
                ],
            ],
            'labels' => ['All'],
        ];
    }
}
PHP;

            file_put_contents("{$dir}/{$name}ByStatusChart.php", $chartContent);
            $files[] = "app/Filament/Widgets/{$name}ByStatusChart.php";
        }

        // Table Widget
        $hasDateField = false;
        foreach ($this->fields as $field) {
            if ($field->type === 'date' || $field->type === 'datetime') {
                $hasDateField = true;

                break;
            }
        }

        $widgetName = $hasDateField ? "{$name}DeadlinesWidget" : "Recent{$pluralName}Widget";
        $widgetHeading = $hasDateField ? "Upcoming {$pluralName}" : "Recent {$pluralName}";
        $widgetQuery = $hasDateField
            ? "{$name}::query()->where('is_active', true)->orderBy('created_at', 'desc')->limit(5)"
            : "{$name}::query()->latest()->limit(5)";

        $tableWidgetContent = <<<PHP
<?php

namespace App\\Filament\\Widgets;

use App\\Models\\{$name};
use Filament\\Tables\\Columns\\TextColumn;
use Filament\\Tables\\Table;
use Filament\\Widgets\\TableWidget;
use Livewire\\Attributes\\On;

class {$widgetName} extends TableWidget
{
    protected static ?int \$sort = 3;

    protected int|string|array \$columnSpan = 'full';

    protected ?string \$pollingInterval = '60s';

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        // Table will refresh on next poll
    }

    public function table(Table \$table): Table
    {
        return \$table
            ->heading('{$widgetHeading}')
            ->query({$widgetQuery})
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('owner.name')->label('Owner'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->paginated(false);
    }
}
PHP;

        file_put_contents("{$dir}/{$widgetName}.php", $tableWidgetContent);
        $files[] = "app/Filament/Widgets/{$widgetName}.php";

        return $files;
    }

    // ========================================================================
    // Notification Stubs
    // ========================================================================

    /**
     * @return array<int, string>
     */
    protected function generateNotifications(string $name): array
    {
        // Use structured notification specs if available
        if ($this->entitySpec !== null && $this->entitySpec->hasStructuredNotifications()) {
            return $this->generateStructuredNotifications($name, $this->entitySpec);
        }

        return $this->generateLegacyNotifications($name);
    }

    /**
     * Generate notifications from structured NotificationSpec definitions.
     *
     * @return array<int, string>
     */
    protected function generateStructuredNotifications(string $name, EntitySpec $spec): array
    {
        $files = [];
        $snakeName = Str::snake($name);
        $dir = app_path('Notifications');
        $this->ensureDirectoryExists($dir);

        $resolver = new NotificationTemplateResolver($name);

        foreach ($spec->notificationSpecs as $notifSpec) {
            $className = $name.$notifSpec->name.'Notification';
            $files = array_merge(
                $files,
                $this->generateSingleStructuredNotification($name, $notifSpec, $className, $resolver, $dir)
            );
        }

        return $files;
    }

    /**
     * @return array<int, string>
     */
    protected function generateSingleStructuredNotification(
        string $name,
        NotificationSpec $notifSpec,
        string $className,
        NotificationTemplateResolver $resolver,
        string $dir,
    ): array {
        $snakeName = Str::snake($name);
        $resolvedBody = $resolver->resolveBody($notifSpec->body);
        $resolvedColor = $resolver->resolveColor($notifSpec->color);
        $isStatusChange = $notifSpec->watchedField() === 'status' && ! empty($this->states);

        // Build constructor params and imports
        $imports = [
            'use Aicl\\Notifications\\BaseNotification;',
            "use App\\Models\\{$name};",
            'use App\\Models\\User;',
        ];
        $constructorParams = [
            "        public {$name} \${$snakeName},",
        ];

        if ($isStatusChange) {
            $imports[] = "use App\\States\\{$name}State;";
            $constructorParams[] = "        public {$name}State \$previousStatus,";
            $constructorParams[] = "        public {$name}State \$newStatus,";
            $constructorParams[] = '        public ?User $changedBy = null,';
        } else {
            $constructorParams[] = '        public User $changedBy,';
        }

        sort($imports);
        $importsStr = implode("\n", $imports);
        $constructorStr = implode("\n", $constructorParams);

        // Color method body
        $colorBody = $notifSpec->hasDynamicColor()
            ? "return {$resolvedColor};"
            : "return {$resolvedColor};";

        $content = <<<PHP
<?php

namespace App\\Notifications;

{$importsStr}

class {$className} extends BaseNotification
{
    public function __construct(
{$constructorStr}
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object \$notifiable): array
    {
        return [
            'title' => '{$notifSpec->title}',
            'body' => "{$resolvedBody}",
            'icon' => \$this->getIcon(),
            'color' => \$this->getColor(),
            'action_url' => route('filament.admin.resources.{$snakeName}s.view', ['record' => \$this->{$snakeName}]),
            'action_text' => 'View {$name}',
        ];
    }

    public function getIcon(): string
    {
        return '{$notifSpec->icon}';
    }

    public function getColor(): string
    {
        {$colorBody}
    }
}
PHP;

        file_put_contents("{$dir}/{$className}.php", $content);

        return ["app/Notifications/{$className}.php"];
    }

    /**
     * Generate legacy stub notifications (backward-compatible behavior).
     *
     * @return array<int, string>
     */
    protected function generateLegacyNotifications(string $name): array
    {
        $files = [];
        $snakeName = Str::snake($name);
        $dir = app_path('Notifications');
        $this->ensureDirectoryExists($dir);

        // Assignment notification
        $assignedContent = <<<PHP
<?php

namespace App\\Notifications;

use Aicl\\Notifications\\BaseNotification;
use App\\Models\\{$name};
use App\\Models\\User;

class {$name}AssignedNotification extends BaseNotification
{
    public function __construct(
        public {$name} \${$snakeName},
        public User \$assignedBy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object \$notifiable): array
    {
        return [
            'title' => '{$name} assigned to you',
            'body' => "\\\"{\$this->{$snakeName}->name}\\\" was assigned to you by {\$this->assignedBy->name}.",
            'icon' => \$this->getIcon(),
            'color' => \$this->getColor(),
            'action_url' => route('filament.admin.resources.{$snakeName}s.view', ['record' => \$this->{$snakeName}]),
            'action_text' => 'View {$name}',
        ];
    }

    public function getIcon(): string
    {
        return 'heroicon-o-user-plus';
    }

    public function getColor(): string
    {
        return 'primary';
    }
}
PHP;

        file_put_contents("{$dir}/{$name}AssignedNotification.php", $assignedContent);
        $files[] = "app/Notifications/{$name}AssignedNotification.php";

        // Status changed notification (only if --states)
        if (! empty($this->states)) {
            $statusContent = <<<PHP
<?php

namespace App\\Notifications;

use Aicl\\Notifications\\BaseNotification;
use App\\Models\\{$name};
use App\\Models\\User;
use App\\States\\{$name}State;

class {$name}StatusChangedNotification extends BaseNotification
{
    public function __construct(
        public {$name} \${$snakeName},
        public {$name}State \$previousStatus,
        public {$name}State \$newStatus,
        public ?User \$changedBy = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object \$notifiable): array
    {
        \$changedByText = \$this->changedBy
            ? " by {\$this->changedBy->name}"
            : '';

        return [
            'title' => '{$name} status changed',
            'body' => "The status of \\\"{\$this->{$snakeName}->name}\\\" was changed from {\$this->previousStatus->label()} to {\$this->newStatus->label()}{\$changedByText}.",
            'icon' => 'heroicon-o-arrow-path',
            'color' => \$this->newStatus->color(),
            'action_url' => route('filament.admin.resources.{$snakeName}s.view', ['record' => \$this->{$snakeName}]),
            'action_text' => 'View {$name}',
        ];
    }

    public function getIcon(): string
    {
        return 'heroicon-o-arrow-path';
    }

    public function getColor(): string
    {
        return \$this->newStatus->color();
    }
}
PHP;

            file_put_contents("{$dir}/{$name}StatusChangedNotification.php", $statusContent);
            $files[] = "app/Notifications/{$name}StatusChangedNotification.php";
        }

        return $files;
    }

    // ========================================================================
    // PDF Template Stubs
    // ========================================================================

    /**
     * @return array<int, string>
     */
    protected function generatePdfTemplates(string $name): array
    {
        // Structured report layout takes priority over field-based generation
        if ($this->entitySpec !== null && $this->entitySpec->hasReportLayout()) {
            return $this->generateStructuredPdfTemplates($name, $this->entitySpec);
        }

        $files = [];
        $snakeName = Str::snake($name);
        $pluralSnake = Str::snake(Str::pluralStudly($name));
        $pluralName = Str::pluralStudly($name);
        $dir = resource_path('views/pdf');
        $this->ensureDirectoryExists($dir);

        // Build info grid rows from fields
        $infoRows = '';
        $fieldPairs = array_chunk($this->fields ?? [], 2);
        foreach ($fieldPairs as $pair) {
            $cells = '';
            foreach ($pair as $field) {
                $label = Str::title(str_replace('_', ' ', $field->name));
                $value = match ($field->type) {
                    'date', 'datetime' => "{{ \${$snakeName}->{$field->name}?->format('F j, Y') ?? 'Not set' }}",
                    'float' => "{{ \${$snakeName}->{$field->name} ? '\$' . number_format(\${$snakeName}->{$field->name}, 2) : 'Not set' }}",
                    'boolean' => "{{ \${$snakeName}->{$field->name} ? 'Yes' : 'No' }}",
                    'enum' => "{{ \${$snakeName}->{$field->name}?->label() ?? '—' }}",
                    'foreignId' => "{{ \${$snakeName}->{$field->relationshipMethodName()}?->name ?? 'Unassigned' }}",
                    default => "{{ \${$snakeName}->{$field->name} ?? '—' }}",
                };
                $cells .= <<<HTML

            <td>
                <div class="label">{$label}</div>
                <div class="value">{$value}</div>
            </td>
HTML;
            }
            $infoRows .= <<<HTML

        <tr>{$cells}
        </tr>
HTML;
        }

        // Single report
        $firstStringField = null;
        foreach ($this->fields ?? [] as $field) {
            if ($field->type === 'string') {
                $firstStringField = $field->name;

                break;
            }
        }
        $titleField = $firstStringField ?? 'name';

        $singleContent = <<<BLADE
@extends('aicl::pdf.layout')

@section('content')
    <h1>{{ \${$snakeName}->{$titleField} }}</h1>

    <h2>{$name} Details</h2>
    <table class="info-grid">{$infoRows}
    </table>

    <div class="text-small text-muted mt-10">
        <p>Last updated: {{ \${$snakeName}->updated_at->format('F j, Y \\a\\t g:i A') }}</p>
    </div>
@endsection
BLADE;

        file_put_contents("{$dir}/{$snakeName}-report.blade.php", $singleContent);
        $files[] = "resources/views/pdf/{$snakeName}-report.blade.php";

        // List report - build table headers and columns
        $tableHeaders = '';
        $tableCells = '';
        $colIndex = 0;
        foreach ($this->fields ?? [] as $field) {
            if ($field->type === 'json' || $field->type === 'text') {
                continue;
            }
            $label = Str::title(str_replace('_', ' ', $field->name));
            $tableHeaders .= "\n                <th>{$label}</th>";
            $cell = match ($field->type) {
                'enum' => "{{ \${$snakeName}->{$field->name}?->label() ?? '—' }}",
                'date', 'datetime' => "{{ \${$snakeName}->{$field->name}?->format('M j, Y') ?? '—' }}",
                'float' => "{{ \${$snakeName}->{$field->name} ? '\$' . number_format(\${$snakeName}->{$field->name}, 2) : '—' }}",
                'boolean' => "{{ \${$snakeName}->{$field->name} ? 'Yes' : 'No' }}",
                'foreignId' => "{{ \${$snakeName}->{$field->relationshipMethodName()}?->name ?? '—' }}",
                default => "{{ \${$snakeName}->{$field->name} }}",
            };
            $tableCells .= "\n                    <td>{$cell}</td>";
            $colIndex++;
        }

        $totalCols = $colIndex + 1; // +1 for # column

        $listContent = <<<BLADE
@extends('aicl::pdf.layout')

@section('content')
    <h1>{$pluralName} Report</h1>

    <table>
        <thead>
            <tr>
                <th>#</th>{$tableHeaders}
            </tr>
        </thead>
        <tbody>
            @forelse(\${$pluralSnake} as \${$snakeName})
                <tr>
                    <td>{{ \${$snakeName}->id }}</td>{$tableCells}
                </tr>
            @empty
                <tr>
                    <td colspan="{$totalCols}" class="text-center text-muted">No {$pluralSnake} found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="text-small text-muted text-right">
        Total: {{ \${$pluralSnake}->count() }} {$pluralSnake}
    </p>
@endsection
BLADE;

        file_put_contents("{$dir}/{$pluralSnake}-report.blade.php", $listContent);
        $files[] = "resources/views/pdf/{$pluralSnake}-report.blade.php";

        return $files;
    }

    /**
     * Generate PDF templates from structured ## Report Layout spec.
     *
     * @return array<int, string>
     */
    protected function generateStructuredPdfTemplates(string $name, EntitySpec $spec): array
    {
        $files = [];
        $snakeName = Str::snake($name);
        $pluralSnake = Str::snake(Str::pluralStudly($name));
        $pluralName = Str::pluralStudly($name);
        $dir = resource_path('views/pdf');
        $this->ensureDirectoryExists($dir);

        $layout = $spec->reportLayout;

        // Generate single report
        if ($layout->hasSingleReport()) {
            $sectionsHtml = '';

            foreach ($layout->singleReport as $section) {
                $sectionsHtml .= $this->renderReportSection($section, $snakeName, $name);
            }

            $singleContent = <<<BLADE
@extends('aicl::pdf.layout')

@section('content'){$sectionsHtml}

    <div class="text-small text-muted mt-10">
        <p>Last updated: {{ \${$snakeName}->updated_at->format('F j, Y \\a\\t g:i A') }}</p>
    </div>
@endsection
BLADE;

            file_put_contents("{$dir}/{$snakeName}-report.blade.php", $singleContent);
            $files[] = "resources/views/pdf/{$snakeName}-report.blade.php";
        }

        // Generate list report
        if ($layout->hasListReport()) {
            $tableHeaders = '';
            $tableCells = '';

            foreach ($layout->listReport as $col) {
                $label = Str::title(str_replace('_', ' ', explode('.', $col->column)[0]));
                $widthAttr = $col->width !== '' ? " style=\"width: {$col->width}\"" : '';
                $tableHeaders .= "\n                <th{$widthAttr}>{$label}</th>";
                $tableCells .= "\n                    <td>{$this->renderListReportCell($col, $snakeName)}</td>";
            }

            $totalCols = count($layout->listReport) + 1;

            $listContent = <<<BLADE
@extends('aicl::pdf.layout')

@section('content')
    <h1>{$pluralName} Report</h1>

    <table>
        <thead>
            <tr>
                <th>#</th>{$tableHeaders}
            </tr>
        </thead>
        <tbody>
            @forelse(\${$pluralSnake} as \${$snakeName})
                <tr>
                    <td>{{ \${$snakeName}->id }}</td>{$tableCells}
                </tr>
            @empty
                <tr>
                    <td colspan="{$totalCols}" class="text-center text-muted">No {$pluralSnake} found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="text-small text-muted text-right">
        Total: {{ \${$pluralSnake}->count() }} {$pluralSnake}
    </p>
@endsection
BLADE;

            file_put_contents("{$dir}/{$pluralSnake}-report.blade.php", $listContent);
            $files[] = "resources/views/pdf/{$pluralSnake}-report.blade.php";
        }

        return $files;
    }

    /**
     * Render a single report section to Blade HTML.
     */
    protected function renderReportSection(ReportSectionSpec $section, string $snakeName, string $entityName): string
    {
        return match ($section->type) {
            'title' => $this->renderTitleSection($section, $snakeName),
            'badges' => $this->renderBadgesSection($section, $snakeName),
            'info-grid' => $this->renderInfoGridSection($section, $snakeName),
            'card' => $this->renderCardSection($section, $snakeName),
            'timeline' => $this->renderTimelineSection($section, $snakeName),
            default => "\n    <!-- TODO: Unknown section type '{$section->type}' for {$section->section} -->",
        };
    }

    /**
     * Render a title section (h1 with model field).
     */
    protected function renderTitleSection(ReportSectionSpec $section, string $snakeName): string
    {
        $field = $section->parsedFields[0] ?? null;

        if ($field === null) {
            return '';
        }

        $value = $this->resolveReportFieldValue($field, $snakeName);

        return "\n    <h1>{$value}</h1>";
    }

    /**
     * Render a badges section (inline colored spans).
     */
    protected function renderBadgesSection(ReportSectionSpec $section, string $snakeName): string
    {
        $badges = '';

        foreach ($section->parsedFields as $field) {
            $value = $this->resolveReportFieldValue($field, $snakeName);
            $badges .= "\n            <span class=\"badge\">{$value}</span>";
        }

        return "\n\n    <div class=\"badges\">{$badges}\n    </div>";
    }

    /**
     * Render an info-grid section (two-column key-value table).
     */
    protected function renderInfoGridSection(ReportSectionSpec $section, string $snakeName): string
    {
        $rows = '';
        $fieldPairs = array_chunk($section->parsedFields, 2);

        foreach ($fieldPairs as $pair) {
            $cells = '';

            foreach ($pair as $field) {
                $label = Str::title(str_replace('_', ' ', explode('.', $field->field)[0]));
                $value = $this->resolveReportFieldValue($field, $snakeName);
                $cells .= "\n            <td>\n                <div class=\"label\">{$label}</div>\n                <div class=\"value\">{$value}</div>\n            </td>";
            }

            $rows .= "\n        <tr>{$cells}\n        </tr>";
        }

        return "\n\n    <h2>{$section->section}</h2>\n    <table class=\"info-grid\">{$rows}\n    </table>";
    }

    /**
     * Render a card section (text content block).
     */
    protected function renderCardSection(ReportSectionSpec $section, string $snakeName): string
    {
        $field = $section->parsedFields[0] ?? null;

        if ($field === null) {
            return '';
        }

        return "\n\n    <h2>{$section->section}</h2>\n    <div class=\"card\">{!! nl2br(e(\${$snakeName}->{$field->field})) !!}</div>";
    }

    /**
     * Render a timeline section (activity log loop).
     */
    protected function renderTimelineSection(ReportSectionSpec $section, string $snakeName): string
    {
        $field = $section->parsedFields[0] ?? null;
        $source = $field !== null ? $field->field : 'activities';

        // Parse limit from field like "activities (limit 10)"
        $limit = 10;
        if ($field !== null && preg_match('/\(limit\s+(\d+)\)/', $field->field, $m)) {
            $limit = (int) $m[1];
            $source = trim(preg_replace('/\s*\(limit\s+\d+\)/', '', $field->field));
        }

        return <<<BLADE


    <h2>{$section->section}</h2>
    <div class="timeline">
        @foreach(\${$snakeName}->{$source}()->latest()->limit({$limit})->get() as \$activity)
            <div class="timeline-entry">
                <div class="text-small text-muted">{{ \$activity->created_at->format('M j, Y g:i A') }}</div>
                <div>{{ \$activity->description }}</div>
                @if(\$activity->causer)
                    <div class="text-small">by {{ \$activity->causer->name }}</div>
                @endif
            </div>
        @endforeach
    </div>
BLADE;
    }

    /**
     * Resolve a ReportFieldSpec into a Blade expression.
     */
    protected function resolveReportFieldValue(ReportFieldSpec $field, string $snakeName): string
    {
        // Template variable like {model.name}
        if ($field->isTemplateVariable()) {
            $inner = trim($field->field, '{}');

            // {model.field} → $model->field
            if (str_starts_with($inner, 'model.')) {
                $attr = substr($inner, 6);

                return "{{ \${$snakeName}->{$attr} }}";
            }

            return "{{ \${$snakeName}->{$inner} }}";
        }

        // Relationship like owner.name
        if ($field->isRelationship()) {
            $rel = $field->relationshipName();
            $attr = $field->relationshipAttribute();

            return "{{ \${$snakeName}->{$rel}?->{$attr} ?? '—' }}";
        }

        // Apply format
        $fieldName = $field->field;

        return match ($field->format) {
            'date' => "{{ \${$snakeName}->{$fieldName}?->format('F j, Y') ?? '—' }}",
            'currency' => "{{ \${$snakeName}->{$fieldName} ? '\$' . number_format(\${$snakeName}->{$fieldName}, 2) : '—' }}",
            'percent' => "{{ \${$snakeName}->{$fieldName} }}%",
            'badge' => "<span class=\"badge\">{{ \${$snakeName}->{$fieldName}?->label() ?? \${$snakeName}->{$fieldName} }}</span>",
            'bold', 'text:bold' => "<strong>{{ \${$snakeName}->{$fieldName} }}</strong>",
            default => "{{ \${$snakeName}->{$fieldName} ?? '—' }}",
        };
    }

    /**
     * Render a list report cell from a ReportColumnSpec.
     */
    protected function renderListReportCell(ReportColumnSpec $col, string $snakeName): string
    {
        // Relationship column
        if ($col->isRelationship()) {
            $rel = $col->relationshipName();
            $attr = $col->relationshipAttribute();

            return "{{ \${$snakeName}->{$rel}?->{$attr} ?? '—' }}";
        }

        $fieldName = $col->column;

        return match ($col->format) {
            'date' => "{{ \${$snakeName}->{$fieldName}?->format('M j, Y') ?? '—' }}",
            'currency' => "{{ \${$snakeName}->{$fieldName} ? '\$' . number_format(\${$snakeName}->{$fieldName}, 2) : '—' }}",
            'percent' => "{{ \${$snakeName}->{$fieldName} }}%",
            'badge' => "<span class=\"badge\">{{ \${$snakeName}->{$fieldName}?->label() ?? \${$snakeName}->{$fieldName} }}</span>",
            'text:bold' => "<strong>{{ \${$snakeName}->{$fieldName} }}</strong>",
            'text' => "{{ \${$snakeName}->{$fieldName} }}",
            default => "{{ \${$snakeName}->{$fieldName} ?? '—' }}",
        };
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    protected function getSmartFormImports(string $name): string
    {
        $imports = [
            'use Filament\\Forms\\Components\\Select;',
            'use Filament\\Forms\\Components\\Toggle;',
            'use Filament\\Schemas\\Components\\Section;',
            'use Filament\\Schemas\\Schema;',
        ];

        // Collect all fields (child + base) for import resolution
        $allFormFields = $this->fields;
        if ($this->baseInspector !== null) {
            $allFormFields = array_merge($this->baseInspector->columns(), $allFormFields);
        }

        foreach ($allFormFields as $field) {
            match ($field->type) {
                'string', 'integer', 'float' => $imports[] = 'use Filament\\Forms\\Components\\TextInput;',
                'text' => $imports[] = 'use Filament\\Forms\\Components\\RichEditor;',
                'date' => $imports[] = 'use Filament\\Forms\\Components\\DatePicker;',
                'datetime' => $imports[] = 'use Filament\\Forms\\Components\\DateTimePicker;',
                'json' => $imports[] = 'use Filament\\Forms\\Components\\KeyValue;',
                default => null,
            };

            if ($field->isEnum()) {
                $imports[] = "use App\\Enums\\{$field->typeArgument};";
            }
        }

        $imports = array_unique($imports);
        sort($imports);

        return implode("\n", $imports);
    }

    protected function getSmartTableImports(string $name): string
    {
        $imports = [
            'use Filament\\Tables\\Columns\\TextColumn;',
        ];

        $hasBooleanColumn = false;
        $hasSelectFilter = false;
        $hasTernaryFilter = false;

        foreach ($this->fields as $field) {
            if ($field->type === 'boolean') {
                $hasBooleanColumn = true;
                $hasTernaryFilter = true;
            }
            if ($field->isEnum() || $field->isForeignKey()) {
                $hasSelectFilter = true;
            }
            if ($field->isEnum()) {
                $imports[] = "use App\\Enums\\{$field->typeArgument};";
            }
        }

        // Always have is_active
        $hasBooleanColumn = true;
        $hasTernaryFilter = true;

        if ($hasBooleanColumn) {
            $imports[] = 'use Filament\\Tables\\Columns\\IconColumn;';
        }
        if ($hasTernaryFilter) {
            $imports[] = 'use Filament\\Tables\\Filters\\TernaryFilter;';
        }
        if ($hasSelectFilter || ! empty($this->states)) {
            $imports[] = 'use Filament\\Tables\\Filters\\SelectFilter;';
        }

        $imports = array_unique($imports);
        sort($imports);

        return implode("\n", $imports);
    }

    /**
     * @param  array<string, string>  $rules
     */
    protected function formatRulesArray(array $rules): string
    {
        $lines = [];
        foreach ($rules as $field => $rule) {
            $lines[] = "            '{$field}' => {$rule},";
        }

        return implode("\n", $lines);
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
