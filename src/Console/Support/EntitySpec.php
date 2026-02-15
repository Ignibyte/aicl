<?php

namespace Aicl\Console\Support;

/**
 * Value object representing a parsed .entity.md specification file.
 *
 * Contains all the structured data needed to generate a complete entity stack.
 */
class EntitySpec
{
    /**
     * @param  array<int, FieldDefinition>  $fields
     * @param  array<int, string>  $states
     * @param  array<string, array<int, string>>  $stateTransitions  Map of from-state => [to-states]
     * @param  array<int, RelationshipDefinition>  $relationships
     * @param  array<string, array<int, array{case: string, label: string, color?: string, icon?: string}>>  $enums
     * @param  array<int, string>  $traits
     * @param  array<string, mixed>  $options
     * @param  array<int, string>  $businessRules
     * @param  array<int, string>  $widgetHints
     * @param  array<int, string>  $notificationHints
     * @param  array<int, WidgetSpec>|null  $widgetSpecs  Structured widget definitions (null = use legacy widgetHints)
     * @param  array<int, NotificationSpec>|null  $notificationSpecs  Structured notification definitions (null = use legacy notificationHints)
     * @param  array<int, ObserverRuleSpec>|null  $observerRules  Structured observer rules (null = use default observer generation)
     * @param  ReportLayoutSpec|null  $reportLayout  Structured report layout (null = use default PDF generation)
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $fields = [],
        public array $states = [],
        public string $defaultState = '',
        public array $stateTransitions = [],
        public array $relationships = [],
        public array $enums = [],
        public array $traits = [],
        public array $options = [],
        public array $businessRules = [],
        public array $widgetHints = [],
        public array $notificationHints = [],
        public ?string $baseClass = null,
        public ?array $widgetSpecs = null,
        public ?array $notificationSpecs = null,
        public ?array $observerRules = null,
        public ?ReportLayoutSpec $reportLayout = null,
    ) {}

    /**
     * Whether the spec defines a state machine.
     */
    public function hasStateMachine(): bool
    {
        return ! empty($this->states);
    }

    /**
     * Whether the spec requests widget generation.
     */
    public function wantsWidgets(): bool
    {
        return ($this->options['widgets'] ?? false) === true;
    }

    /**
     * Whether the spec has structured widget definitions (vs legacy hints).
     */
    public function hasStructuredWidgets(): bool
    {
        return $this->widgetSpecs !== null && ! empty($this->widgetSpecs);
    }

    /**
     * Whether the spec requests notification generation.
     */
    public function wantsNotifications(): bool
    {
        return ($this->options['notifications'] ?? false) === true;
    }

    /**
     * Whether the spec has structured notification definitions (vs legacy hints).
     */
    public function hasStructuredNotifications(): bool
    {
        return $this->notificationSpecs !== null && ! empty($this->notificationSpecs);
    }

    /**
     * Whether the spec has structured observer rules.
     */
    public function hasObserverRules(): bool
    {
        return $this->observerRules !== null && ! empty($this->observerRules);
    }

    /**
     * Whether the spec has a structured report layout definition.
     */
    public function hasReportLayout(): bool
    {
        return $this->reportLayout !== null;
    }

    /**
     * Whether the spec requests PDF generation.
     */
    public function wantsPdf(): bool
    {
        return ($this->options['pdf'] ?? false) === true;
    }

    /**
     * Whether the spec requests AI context trait.
     */
    public function wantsAiContext(): bool
    {
        return ($this->options['ai-context'] ?? false) === true
            || in_array('HasAiContext', $this->traits, true);
    }

    /**
     * Whether the spec wants a Filament resource (default true).
     */
    public function wantsFilament(): bool
    {
        return ($this->options['filament'] ?? true) === true;
    }

    /**
     * Whether the spec wants an API layer (default true).
     */
    public function wantsApi(): bool
    {
        return ($this->options['api'] ?? true) === true;
    }

    /**
     * Convert fields to CLI --fields string format.
     * Format: name:type[:argument][:modifier1][:modifier2]
     */
    public function toFieldsString(): string
    {
        if (empty($this->fields)) {
            return '';
        }

        $segments = [];

        foreach ($this->fields as $field) {
            $parts = [$field->name, $field->type];

            if ($field->typeArgument !== null) {
                $parts[] = $field->typeArgument;
            }

            if ($field->nullable && ! in_array($field->type, ['text', 'date', 'datetime', 'json'], true)) {
                $parts[] = 'nullable';
            }

            if ($field->unique) {
                $parts[] = 'unique';
            }

            if ($field->indexed) {
                $parts[] = 'index';
            }

            if ($field->default !== null && ! ($field->type === 'boolean' && $field->default === 'true')) {
                $parts[] = "default({$field->default})";
            }

            $segments[] = implode(':', $parts);
        }

        return implode(',', $segments);
    }

    /**
     * Convert states to CLI --states string format.
     */
    public function toStatesString(): string
    {
        return implode(',', $this->states);
    }

    /**
     * Convert relationships to CLI --relationships string format.
     * Format: name:type:Model[:foreign_key]
     */
    public function toRelationshipsString(): string
    {
        if (empty($this->relationships)) {
            return '';
        }

        $segments = [];

        foreach ($this->relationships as $rel) {
            $parts = [$rel->name, $rel->type, $rel->relatedModel];

            if ($rel->foreignKey !== null) {
                $parts[] = $rel->foreignKey;
            }

            $segments[] = implode(':', $parts);
        }

        return implode(',', $segments);
    }
}
