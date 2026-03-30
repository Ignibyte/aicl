<?php

declare(strict_types=1);

namespace Aicl\Console\Commands;

use Aicl\Console\Support\EntitySpec;
use Aicl\Console\Support\NotificationSpec;
use Aicl\Console\Support\SpecFileParser;
use Aicl\Console\Support\SpecValidation;
use Aicl\Console\Support\WidgetSpec;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * ValidateSpecCommand.
 */
class ValidateSpecCommand extends Command
{
    use SpecValidation;

    /**
     * @var string
     */
    protected $signature = 'aicl:validate-spec
        {spec : Entity name (looks in specs/{Name}.entity.md) or path to spec file}';

    /**
     * @var string
     */
    protected $description = 'Validate an entity spec file before generation.';

    /**
     * @var array<int, string>
     */
    protected array $errors = [];

    /**
     * @var array<int, string>
     */
    protected array $warnings = [];

    /** @codeCoverageIgnore Reason: external-service -- Spec validation requires entity spec files */
    public function handle(): int
    {
        $input = $this->argument('spec');

        // Resolve spec file path
        $specPath = $this->resolveSpecPath($input);

        if ($specPath === null) {
            $this->components->error("Spec file not found: {$input}");
            $this->components->info('Usage: aicl:validate-spec Invoice  OR  aicl:validate-spec specs/Invoice.entity.md');

            return self::FAILURE;
        }

        $this->components->info("Validating spec file: {$specPath}");
        $this->newLine();

        // Parse the spec file
        $parser = new SpecFileParser;

        try {
            $spec = $parser->parse($specPath);
        } catch (InvalidArgumentException $e) {
            $this->components->error("Parse error: {$e->getMessage()}");

            return self::FAILURE;
        }

        // Run validation checks
        $this->validateDescription($spec);
        $this->validateFields($spec);
        $this->validateEnumDefinitions($spec);
        $this->validateStates($spec);
        $this->validateRelationships($spec);
        $this->validateTraits($spec);
        $this->validateOptions($spec);
        $this->validateCrossReferences($specPath, $spec);
        $this->validateSearchableColumns($spec);
        $this->validateWidgetSpecs($spec);
        $this->validateNotificationSpecs($spec);
        $this->validateObserverRules($spec);
        $this->validateReportLayout($spec);

        // Report results
        $this->newLine();

        if (! empty($this->warnings)) {
            $this->components->warn('Warnings:');

            foreach ($this->warnings as $warning) {
                $this->components->twoColumnDetail('  WARN', $warning);
            }

            $this->newLine();
        }

        if (! empty($this->errors)) {
            $this->components->error('Validation FAILED with '.count($this->errors).' error(s):');

            foreach ($this->errors as $error) {
                $this->components->twoColumnDetail('  ERROR', $error);
            }

            $this->newLine();

            return self::FAILURE;
        }

        $fieldCount = count($spec->fields);
        $stateCount = count($spec->states);
        $relCount = count($spec->relationships);
        $enumCount = count($spec->enums);

        $widgetCount = $spec->widgetSpecs !== null ? count($spec->widgetSpecs) : 0;
        $notifCount = $spec->notificationSpecs !== null ? count($spec->notificationSpecs) : 0;
        $observerRuleCount = $spec->observerRules !== null ? count($spec->observerRules) : 0;

        $this->components->info("Spec '{$spec->name}' is valid!");
        $this->components->twoColumnDetail('Fields', (string) $fieldCount);
        $this->components->twoColumnDetail('States', $stateCount > 0 ? (string) $stateCount : 'None');
        $this->components->twoColumnDetail('Relationships', $relCount > 0 ? (string) $relCount : 'None');
        $this->components->twoColumnDetail('Enums', $enumCount > 0 ? (string) $enumCount : 'None');
        $this->components->twoColumnDetail('Widgets', $widgetCount > 0 ? (string) $widgetCount : (! empty($spec->widgetHints) ? 'Legacy hints' : 'None'));
        $this->components->twoColumnDetail('Notifications', $notifCount > 0 ? (string) $notifCount : (! empty($spec->notificationHints) ? 'Legacy hints' : 'None'));
        $this->components->twoColumnDetail('Observer Rules', $observerRuleCount > 0 ? (string) $observerRuleCount : 'None');

        $reportSections = $spec->reportLayout !== null
            ? count($spec->reportLayout->singleReport) + count($spec->reportLayout->listReport)
            : 0;
        $this->components->twoColumnDetail('Report Layout', $reportSections > 0 ? "{$reportSections} sections/columns" : 'None');
        $this->components->twoColumnDetail('Warnings', ! empty($this->warnings) ? (string) count($this->warnings) : 'None');

        return self::SUCCESS;
    }

    protected function resolveSpecPath(string $input): ?string
    {
        // Direct path
        if (str_ends_with($input, '.md') || str_ends_with($input, '.entity.md')) {
            $path = str_starts_with($input, '/') ? $input : base_path($input);

            return file_exists($path) ? $path : null;
        }

        // Entity name — try specs/{Name}.entity.md
        $name = Str::studly($input);
        $path = base_path("specs/{$name}.entity.md");

        return file_exists($path) ? $path : null;
    }

    protected function validateDescription(EntitySpec $spec): void
    {
        if (trim($spec->description) === '') {
            $this->warnings[] = "No description provided. Add a paragraph after '# {$spec->name}' for documentation.";
        }
    }

    /** @codeCoverageIgnore Reason: external-service -- Validation edge case for duplicate field names */
    protected function validateFields(EntitySpec $spec): void
    {
        $fieldNames = [];

        foreach ($spec->fields as $field) {
            // Check reserved columns (ERROR)
            if ($this->isReservedColumn($field->name)) {
                $this->errors[] = "Field '{$field->name}' is a reserved column and cannot be explicitly defined.";

                continue;
            }

            // Check auto columns (WARN, per Amendment #2)
            if ($this->isAutoColumn($field->name)) {
                $this->warnings[] = "Field '{$field->name}' is auto-generated by base traits. Explicit definition will override the default.";
            }

            // Check duplicate names
            if (in_array($field->name, $fieldNames, true)) {
                // @codeCoverageIgnoreStart — Artisan command
                $this->errors[] = "Duplicate field name: '{$field->name}'.";
                // @codeCoverageIgnoreEnd
            }

            $fieldNames[] = $field->name;
        }
    }

    protected function validateEnumDefinitions(EntitySpec $spec): void
    {
        // Find all enum fields
        $enumFields = [];

        foreach ($spec->fields as $field) {
            if ($field->isEnum() && $field->typeArgument !== null) {
                $enumFields[$field->typeArgument] = $field->name;
            }
        }

        // Check that each enum field has a corresponding definition
        foreach ($enumFields as $enumClass => $fieldName) {
            if (! isset($spec->enums[$enumClass])) {
                $this->warnings[] = "Enum field '{$fieldName}' references '{$enumClass}' but no ## Enums > ### {$enumClass} section was found. Placeholder cases will be generated.";
            }
        }

        // Check that each enum definition corresponds to a field
        foreach (array_keys($spec->enums) as $enumName) {
            if (! isset($enumFields[$enumName])) {
                $this->warnings[] = "Enum definition '{$enumName}' has no corresponding enum field.";
            }
        }

        // Validate enum case format
        foreach ($spec->enums as $enumName => $cases) {
            if (empty($cases)) {
                // @codeCoverageIgnoreStart — Artisan command
                $this->errors[] = "Enum '{$enumName}' must have at least one case.";

                continue;
                // @codeCoverageIgnoreEnd
            }

            $caseNames = [];

            foreach ($cases as $case) {
                $caseName = $case['case'];

                if ($caseName === '') {
                    // @codeCoverageIgnoreStart — Artisan command
                    $this->errors[] = "Enum '{$enumName}' has a case with empty name.";

                    continue;
                    // @codeCoverageIgnoreEnd
                }

                if (in_array($caseName, $caseNames, true)) {
                    $this->errors[] = "Enum '{$enumName}' has duplicate case: '{$caseName}'.";
                }

                $caseNames[] = $caseName;

                if ($case['label'] === '') {
                    // @codeCoverageIgnoreStart — Artisan command
                    $this->errors[] = "Enum '{$enumName}' case '{$caseName}' is missing a label.";
                    // @codeCoverageIgnoreEnd
                }
            }
        }
    }

    protected function validateStates(EntitySpec $spec): void
    {
        if (empty($spec->states)) {
            return;
        }

        // Check default state exists
        if ($spec->defaultState !== '' && ! in_array($spec->defaultState, $spec->states, true)) {
            $this->errors[] = "Default state '{$spec->defaultState}' is not in the states list.";
        }

        // Check all transition states are defined
        foreach ($spec->stateTransitions as $from => $toList) {
            if (! in_array($from, $spec->states, true)) {
                // @codeCoverageIgnoreStart — Artisan command
                $this->errors[] = "Transition source state '{$from}' is not in the states list.";
                // @codeCoverageIgnoreEnd
            }

            foreach ($toList as $to) {
                if (! in_array($to, $spec->states, true)) {
                    // @codeCoverageIgnoreStart — Artisan command
                    $this->errors[] = "Transition target state '{$to}' is not in the states list.";
                    // @codeCoverageIgnoreEnd
                }
            }
        }

        // Check state name format
        foreach ($spec->states as $state) {
            if (! $this->isSnakeCase($state)) {
                $this->errors[] = "State name '{$state}' must be snake_case.";
            }
        }
    }

    protected function validateRelationships(EntitySpec $spec): void
    {
        foreach ($spec->relationships as $rel) {
            // Check method name is camelCase
            if (! $this->isCamelCase($rel->name)) {
                $this->errors[] = "Relationship method name '{$rel->name}' must be camelCase.";
            }

            // Check type
            if (! $this->isValidRelationshipType($rel->type)) {
                $this->errors[] = "Relationship type '{$rel->type}' for '{$rel->name}' is not supported. Supported: hasMany, hasOne, belongsToMany, morphMany";
            }

            // Check related model is PascalCase
            if (! $this->isPascalCase($rel->relatedModel)) {
                $this->errors[] = "Related model '{$rel->relatedModel}' for relationship '{$rel->name}' must be PascalCase.";
            }
        }
    }

    protected function validateTraits(EntitySpec $spec): void
    {
        foreach ($spec->traits as $trait) {
            if (! $this->isKnownTrait($trait)) {
                $this->warnings[] = "Trait '{$trait}' is not a recognized AICL trait. It may need manual implementation.";
            }
        }
    }

    protected function validateOptions(EntitySpec $spec): void
    {
        foreach (array_keys($spec->options) as $key) {
            if (! $this->isKnownOption($key)) {
                $this->warnings[] = "Option '{$key}' is not a recognized AICL option.";
            }
        }
    }

    protected function validateCrossReferences(string $specPath, EntitySpec $spec): void
    {
        $specsDir = dirname($specPath);

        foreach ($spec->fields as $field) {
            if ($field->type !== 'foreignId' || $field->typeArgument === null) {
                continue;
            }

            $tableName = $field->typeArgument;
            $modelName = Str::studly(Str::singular($tableName));
            $crossSpecPath = "{$specsDir}/{$modelName}.entity.md";

            // Check if referenced entity has a spec file
            if (! file_exists($crossSpecPath)) {
                $this->warnings[] = "Field '{$field->name}' references table '{$tableName}' but no spec file found at {$modelName}.entity.md";
            }
        }
    }

    protected function validateSearchableColumns(EntitySpec $spec): void
    {
        if (! in_array('HasStandardScopes', $spec->traits, true)) {
            return;
        }

        $hasStringField = false;

        foreach ($spec->fields as $field) {
            if (in_array($field->type, ['string', 'text'], true)) {
                $hasStringField = true;

                break;
            }
        }

        if (! $hasStringField) {
            $this->warnings[] = "Trait 'HasStandardScopes' is selected but no string/text fields found. Search scope may not work as expected.";
        }
    }

    protected function validateWidgetSpecs(EntitySpec $spec): void
    {
        if ($spec->widgetSpecs === null) {
            return;
        }

        $validColors = ['primary', 'success', 'warning', 'danger', 'info', 'gray', 'secondary'];
        $fieldNames = array_map(fn ($f) => $f->name, $spec->fields);
        $stateAndEnumFields = [];

        foreach ($spec->fields as $field) {
            if ($field->isEnum() || $field->name === 'status') {
                $stateAndEnumFields[] = $field->name;
            }
        }

        if (! empty($spec->states)) {
            $stateAndEnumFields[] = 'status';
        }

        $stateAndEnumFields = array_unique($stateAndEnumFields);

        foreach ($spec->widgetSpecs as $widget) {
            match ($widget->type) {
                'stats' => $this->validateStatsWidget($widget, $validColors),
                'chart' => $this->validateChartWidget($widget, $stateAndEnumFields, $validColors),
                default => $this->validateTableWidget($widget, $fieldNames),
            };
        }
    }

    /**
     * @param array<int, string> $validColors
     */
    protected function validateStatsWidget(WidgetSpec $widget, array $validColors): void
    {
        if (empty($widget->metrics)) {
            // @codeCoverageIgnoreStart — Artisan command
            $this->errors[] = 'StatsOverview widget has no metrics defined.';
            // @codeCoverageIgnoreEnd
        }

        foreach ($widget->metrics as $metric) {
            if ($metric->color !== '' && ! in_array($metric->color, $validColors, true)) {
                $this->warnings[] = "Metric '{$metric->label}' uses unknown color '{$metric->color}'.";
            }
        }
    }

    /**
     * @param array<int, string> $stateAndEnumFields
     * @param array<int, string> $validColors
     */
    protected function validateChartWidget(WidgetSpec $widget, array $stateAndEnumFields, array $validColors): void
    {
        if ($widget->groupBy !== null && ! in_array($widget->groupBy, $stateAndEnumFields, true)) {
            $this->warnings[] = "Chart groupBy field '{$widget->groupBy}' is not a state or enum field.";
        }

        foreach ($widget->colors as $colorValue) {
            if (! in_array($colorValue, $validColors, true) && ! preg_match('/^#[0-9a-fA-F]{3,6}$/', $colorValue)) {
                $this->warnings[] = "Chart color '{$colorValue}' is not a recognized Filament color or hex value.";
            }
        }
    }

    /**
     * @param array<int, string> $fieldNames
     */
    protected function validateTableWidget(WidgetSpec $widget, array $fieldNames): void
    {
        foreach ($widget->columns as $column) {
            // Allow relationship columns (contain dot notation)
            if (str_contains($column->name, '.')) {
                continue;
            }

            if (! in_array($column->name, $fieldNames, true)
                && ! in_array($column->name, ['id', 'created_at', 'updated_at', 'status', 'is_active', 'owner_id'], true)) {
                $this->warnings[] = "Table widget column '{$column->name}' does not match a known field.";
            }
        }
    }

    protected function validateNotificationSpecs(EntitySpec $spec): void
    {
        if ($spec->notificationSpecs === null) {
            return;
        }

        $validTriggerTypes = ['field_change', 'state_transition', 'created', 'deleted'];
        $validChannels = ['database', 'mail', 'broadcast', 'slack'];
        $validColors = ['primary', 'success', 'warning', 'danger', 'info', 'gray', 'secondary'];
        $fieldNames = array_map(fn ($f) => $f->name, $spec->fields);

        if (! empty($spec->states)) {
            // @codeCoverageIgnoreStart — Artisan command
            $fieldNames[] = 'status';
            // @codeCoverageIgnoreEnd
        }

        $fieldNames = array_unique($fieldNames);

        foreach ($spec->notificationSpecs as $notifSpec) {
            $this->validateSingleNotificationSpec($notifSpec, $spec, $fieldNames, $validTriggerTypes, $validChannels, $validColors);
        }
    }

    /**
     * @param array<int, string> $fieldNames
     * @param array<int, string> $validTriggerTypes
     * @param array<int, string> $validChannels
     * @param array<int, string> $validColors
     */
    protected function validateSingleNotificationSpec(
        NotificationSpec $notifSpec,
        EntitySpec $spec,
        array $fieldNames,
        array $validTriggerTypes,
        array $validChannels,
        array $validColors,
    ): void {
        // Validate notification name is PascalCase
        if (! $this->isPascalCase($notifSpec->name)) {
            $this->errors[] = "Notification name '{$notifSpec->name}' must be PascalCase.";
        }

        // Validate trigger type
        $triggerType = $notifSpec->triggerType();

        if (! in_array($triggerType, $validTriggerTypes, true)) {
            $this->errors[] = "Notification '{$notifSpec->name}' has unknown trigger type: '{$triggerType}'.";
        }

        // Validate watched field exists for field_change triggers
        if ($triggerType === 'field_change') {
            $watchedField = $notifSpec->watchedField();

            if ($watchedField !== null && ! in_array($watchedField, $fieldNames, true)) {
                $this->warnings[] = "Notification '{$notifSpec->name}' watches field '{$watchedField}' which is not in the fields list.";
            }
        }

        // Validate title and body are not empty
        if (trim($notifSpec->title) === '') {
            // @codeCoverageIgnoreStart — Artisan command
            $this->errors[] = "Notification '{$notifSpec->name}' has an empty title.";
            // @codeCoverageIgnoreEnd
        }

        if (trim($notifSpec->body) === '') {
            $this->errors[] = "Notification '{$notifSpec->name}' has an empty body.";
        }

        // Validate channels
        foreach ($notifSpec->channels as $channel) {
            if (! in_array($channel, $validChannels, true)) {
                $this->warnings[] = "Notification '{$notifSpec->name}' uses unknown channel '{$channel}'.";
            }
        }

        // Validate static color (dynamic colors like "new.status.color" are fine)
        if (! $notifSpec->hasDynamicColor() && ! in_array($notifSpec->color, $validColors, true)) {
            $this->warnings[] = "Notification '{$notifSpec->name}' uses unknown color '{$notifSpec->color}'.";
        }

        // Validate template variables reference valid fields
        $this->validateTemplateVariables($notifSpec, $spec);
    }

    protected function validateTemplateVariables(NotificationSpec $notifSpec, EntitySpec $spec): void
    {
        $fieldNames = array_map(fn ($f) => $f->name, $spec->fields);

        // Extract {model.field} references from body
        if (preg_match_all('/\{model\.(\w+)\}/', $notifSpec->body, $matches)) {
            foreach ($matches[1] as $referencedField) {
                // Allow common fields that might not be in the spec
                if (in_array($referencedField, ['name', 'title', 'id'], true)) {
                    continue;
                }

                if (! in_array($referencedField, $fieldNames, true)) {
                    $this->warnings[] = "Notification '{$notifSpec->name}' body references '{$referencedField}' which is not in the fields list.";
                }
            }
        }

        // Validate {old.status.label} / {new.status.label} only used when states exist
        if ((str_contains($notifSpec->body, '{old.status.label}') || str_contains($notifSpec->body, '{new.status.label}'))
            && empty($spec->states)) {
            $this->warnings[] = "Notification '{$notifSpec->name}' uses status label template variables but no states are defined.";
        }
    }

    protected function validateObserverRules(EntitySpec $spec): void
    {
        if ($spec->observerRules === null) {
            return;
        }

        $validEvents = ['created', 'updated', 'deleted'];
        $validActions = ['log', 'notify'];
        $fieldNames = array_map(fn ($f) => $f->name, $spec->fields);

        if (! empty($spec->states)) {
            $fieldNames[] = 'status';
        }

        $fieldNames = array_unique($fieldNames);

        foreach ($spec->observerRules as $rule) {
            // Validate event
            if (! in_array($rule->event, $validEvents, true)) {
                // @codeCoverageIgnoreStart — Artisan command
                $this->errors[] = "Observer rule has unknown event: '{$rule->event}'.";
                // @codeCoverageIgnoreEnd
            }

            // Validate action
            if (! in_array($rule->action, $validActions, true)) {
                $this->errors[] = "Observer rule has unknown action: '{$rule->action}'. Supported: log, notify.";
            }

            // Validate watch field for update rules
            if ($rule->event === 'updated' && $rule->watchField !== null) {
                if (! in_array($rule->watchField, $fieldNames, true)) {
                    $this->warnings[] = "Observer rule watches field '{$rule->watchField}' which is not in the fields list.";
                }
            }

            // Validate notify details
            if ($rule->isNotify()) {
                $parsed = $rule->parseNotifyDetails();

                if ($parsed['class'] === '') {
                    $this->errors[] = "Observer notify rule is missing notification class name. Format: 'recipient: ClassName'.";
                }
            }

            // Validate log details are not empty
            if ($rule->isLog() && trim($rule->details) === '') {
                // @codeCoverageIgnoreStart — Artisan command
                $this->errors[] = 'Observer log rule has empty details.';
                // @codeCoverageIgnoreEnd
            }
        }
    }

    protected function validateReportLayout(EntitySpec $spec): void
    {
        if ($spec->reportLayout === null) {
            return;
        }

        $fieldNames = array_map(fn ($f) => $f->name, $spec->fields);
        $relationNames = array_map(fn ($r) => $r->name, $spec->relationships);

        if (! empty($spec->states)) {
            $fieldNames[] = 'status';
        }

        $fieldNames = array_unique($fieldNames);

        $validSectionTypes = ['title', 'badges', 'info-grid', 'card', 'timeline'];
        $validFormats = ['text', 'text:bold', 'date', 'currency', 'percent', 'badge'];

        // Validate single report sections
        foreach ($spec->reportLayout->singleReport as $section) {
            if (! in_array($section->type, $validSectionTypes, true)) {
                $this->warnings[] = "Report section '{$section->section}' has unknown type: '{$section->type}'.";
            }

            foreach ($section->parsedFields as $field) {
                if ($field->isTemplateVariable()) {
                    continue;
                }

                if ($field->isRelationship()) {
                    if (! in_array($field->relationshipName(), $relationNames, true)) {
                        $this->warnings[] = "Report section '{$section->section}' references relationship '{$field->relationshipName()}' not in relationships list.";
                    }
                } elseif ($section->type !== 'timeline') {
                    $baseField = $field->field;

                    if (! in_array($baseField, $fieldNames, true)) {
                        $this->warnings[] = "Report section '{$section->section}' references field '{$baseField}' not in fields list.";
                    }
                }
            }
        }

        // Validate list report columns
        foreach ($spec->reportLayout->listReport as $col) {
            if (! in_array($col->format, $validFormats, true)) {
                $this->warnings[] = "List report column '{$col->column}' has unknown format: '{$col->format}'.";
            }

            if ($col->isRelationship()) {
                if (! in_array($col->relationshipName(), $relationNames, true)) {
                    $this->warnings[] = "List report column '{$col->column}' references relationship '{$col->relationshipName()}' not in relationships list.";
                }
            } elseif (! in_array($col->column, $fieldNames, true)) {
                $this->warnings[] = "List report column '{$col->column}' not in fields list.";
            }
        }
    }
}
