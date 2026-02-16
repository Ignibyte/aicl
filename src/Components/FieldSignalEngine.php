<?php

namespace Aicl\Components;

/**
 * Pattern matching engine that maps entity field definitions
 * to component recommendations.
 *
 * Uses field name heuristics and type-based matching to suggest
 * which AICL components best represent the data.
 */
class FieldSignalEngine
{
    /**
     * Signal pattern rules. Each rule defines a detection function
     * and the resulting recommendation.
     *
     * @var array<string, array{detect: callable, tag: string, reason: string, confidence: float}>
     */
    private array $rules = [];

    public function __construct()
    {
        $this->registerDefaultRules();
    }

    /**
     * Match a single field definition to a component recommendation.
     *
     * @param  string  $fieldName  Field name (e.g., 'starts_at')
     * @param  string  $fieldType  Field type (e.g., 'datetime')
     * @param  string  $context  Rendering context (e.g., 'blade')
     * @param  array  $allFields  All entity fields for multi-field pattern detection
     */
    public function match(string $fieldName, string $fieldType, string $context = 'blade', array $allFields = []): ?ComponentRecommendation
    {
        // Multi-field patterns (checked first, higher specificity)
        $multiFieldMatch = $this->matchMultiField($fieldName, $fieldType, $allFields, $context);
        if ($multiFieldMatch !== null) {
            return $multiFieldMatch;
        }

        // Single-field patterns
        foreach ($this->rules as $rule) {
            if (($rule['detect'])($fieldName, $fieldType, $context, $allFields)) {
                return new ComponentRecommendation(
                    tag: $rule['tag'],
                    reason: $rule['reason'],
                    suggestedProps: $rule['suggestedProps'] ?? [],
                    confidence: $rule['confidence'],
                    alternative: $rule['alternative'] ?? null,
                );
            }
        }

        return null;
    }

    /**
     * Match fields that require multi-field context.
     */
    private function matchMultiField(string $fieldName, string $fieldType, array $allFields, string $context): ?ComponentRecommendation
    {
        // datetime range: starts_at + ends_at pattern
        if ($this->isTemporalField($fieldName) && $fieldType === 'datetime') {
            $pairField = $this->findTemporalPair($fieldName, $allFields);
            if ($pairField !== null) {
                return new ComponentRecommendation(
                    tag: 'x-aicl-data-table',
                    reason: "Entity has datetime range fields ({$fieldName} + {$pairField}) — suitable for date-sorted table or calendar view",
                    suggestedProps: ['sortable' => true],
                    confidence: 0.95,
                    alternative: $context !== 'blade' ? 'Filament\Widgets\ChartWidget' : null,
                );
            }
        }

        // target + actual pair → KPI card
        if ($this->isTargetField($fieldName) && in_array($fieldType, ['float', 'integer', 'decimal'])) {
            $actualField = $this->findActualPair($fieldName, $allFields);
            if ($actualField !== null) {
                return new ComponentRecommendation(
                    tag: 'x-aicl-kpi-card',
                    reason: "Entity has target/actual pair ({$fieldName} + {$actualField}) — ideal for KPI display",
                    suggestedProps: ['label' => ucfirst(str_replace('_', ' ', $fieldName))],
                    confidence: 0.9,
                    alternative: $context !== 'blade' ? 'Filament\Widgets\StatsOverviewWidget' : null,
                );
            }
        }

        return null;
    }

    /**
     * Register the default field signal matching rules.
     */
    private function registerDefaultRules(): void
    {
        // Status enum/state → status badge
        $this->rules['status_enum'] = [
            'detect' => fn (string $name, string $type): bool => ($name === 'status' || str_ends_with($name, '_status'))
                && in_array($type, ['enum', 'state']),
            'tag' => 'x-aicl-status-badge',
            'reason' => 'Status/state field detected — use status badge for visual indicator',
            'confidence' => 0.95,
            'suggestedProps' => [],
            'alternative' => 'TextColumn::make()->badge()',
        ];

        // Progress field → progress card
        $this->rules['progress'] = [
            'detect' => fn (string $name, string $type): bool => $name === 'progress'
                && in_array($type, ['integer', 'float']),
            'tag' => 'x-aicl-progress-card',
            'reason' => 'Progress percentage field detected — use progress card',
            'confidence' => 0.95,
            'suggestedProps' => ['label' => 'Progress'],
            'alternative' => 'Filament\Widgets\StatsOverviewWidget',
        ];

        // Count/aggregate → stat card
        $this->rules['count_aggregate'] = [
            'detect' => fn (string $name, string $type): bool => (str_ends_with($name, '_count') || str_starts_with($name, 'total_') || str_starts_with($name, 'num_'))
                && in_array($type, ['integer', 'float']),
            'tag' => 'x-aicl-stat-card',
            'reason' => 'Count/aggregate field detected — use stat card for metric display',
            'confidence' => 0.9,
            'suggestedProps' => ['label' => ucfirst(str_replace('_', ' ', 'field'))],
            'alternative' => 'Filament\Widgets\StatsOverviewWidget',
        ];

        // Monetary field → stat card
        $this->rules['monetary'] = [
            'detect' => fn (string $name, string $type): bool => in_array($name, ['budget', 'amount', 'price', 'cost', 'total', 'revenue', 'salary'])
                && in_array($type, ['float', 'decimal', 'integer']),
            'tag' => 'x-aicl-stat-card',
            'reason' => 'Monetary field detected — use stat card with currency formatting',
            'confidence' => 0.8,
            'suggestedProps' => ['icon' => 'heroicon-o-currency-dollar'],
            'alternative' => 'Filament\Widgets\StatsOverviewWidget',
        ];

        // Boolean field → status badge
        $this->rules['boolean'] = [
            'detect' => fn (string $name, string $type): bool => str_starts_with($name, 'is_')
                && $type === 'boolean',
            'tag' => 'x-aicl-status-badge',
            'reason' => 'Boolean flag detected — use badge for on/off display',
            'confidence' => 0.7,
            'suggestedProps' => [],
            'alternative' => 'IconColumn::make()',
        ];

        // Single datetime → trend card context
        $this->rules['single_datetime'] = [
            'detect' => fn (string $name, string $type): bool => (str_ends_with($name, '_at') || str_ends_with($name, '_date'))
                && in_array($type, ['datetime', 'date']),
            'tag' => 'x-aicl-trend-card',
            'reason' => 'Temporal field detected — suitable for time-series trend display',
            'confidence' => 0.6,
            'suggestedProps' => [],
            'alternative' => 'Filament\Widgets\ChartWidget',
        ];
    }

    /**
     * Recommend components for an entity's full field set.
     *
     * @param  array  $fields  Array of ['name' => 'type'] pairs
     * @param  string  $context  Rendering context
     * @param  string  $viewType  View type: index, show, card
     * @return array<ComponentRecommendation>
     */
    public function recommendForEntity(array $fields, string $context = 'blade', string $viewType = 'index'): array
    {
        $recommendations = [];
        $processed = [];

        foreach ($fields as $name => $type) {
            if (isset($processed[$name])) {
                continue;
            }

            $match = $this->match($name, $type, $context, $fields);
            if ($match !== null) {
                $recommendations[] = $match;
                $processed[$name] = true;
            }
        }

        return $recommendations;
    }

    private function isTemporalField(string $name): bool
    {
        return (bool) preg_match('/^(starts?|begins?|from)_?(at|date|time)?$/', $name)
            || (bool) preg_match('/^(ends?|finishe?s?|to|until)_?(at|date|time)?$/', $name)
            || str_ends_with($name, '_at')
            || str_ends_with($name, '_date');
    }

    private function findTemporalPair(string $name, array $allFields): ?string
    {
        $startPatterns = ['start', 'starts', 'begin', 'begins', 'from'];
        $endPatterns = ['end', 'ends', 'finish', 'finishes', 'to', 'until'];

        foreach ($startPatterns as $pattern) {
            if (str_starts_with($name, $pattern)) {
                $suffix = substr($name, strlen($pattern));
                foreach ($endPatterns as $endPattern) {
                    $candidate = $endPattern.$suffix;
                    if (isset($allFields[$candidate])) {
                        return $candidate;
                    }
                }
            }
        }

        foreach ($endPatterns as $pattern) {
            if (str_starts_with($name, $pattern)) {
                $suffix = substr($name, strlen($pattern));
                foreach ($startPatterns as $startPattern) {
                    $candidate = $startPattern.$suffix;
                    if (isset($allFields[$candidate])) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    private function isTargetField(string $name): bool
    {
        return in_array($name, ['target', 'goal', 'budget', 'quota', 'planned']);
    }

    private function findActualPair(string $name, array $allFields): ?string
    {
        $pairMappings = [
            'target' => ['actual', 'current', 'achieved'],
            'goal' => ['actual', 'current', 'achieved', 'progress'],
            'budget' => ['spent', 'actual', 'used'],
            'quota' => ['actual', 'achieved', 'current'],
            'planned' => ['actual', 'completed'],
        ];

        $candidates = $pairMappings[$name] ?? [];
        foreach ($candidates as $candidate) {
            if (isset($allFields[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }
}
