<?php

namespace Aicl\Console\Support;

use Illuminate\Support\Str;

/**
 * Converts the widget query mini-DSL into Eloquent code strings.
 *
 * Supported DSL syntax:
 *   count(*)                           → Model::query()->count()
 *   count(*) where status = active     → Model::query()->where('status', ...)->count()
 *   sum(amount)                        → Model::query()->sum('amount')
 *   sum(amount) where status != paid   → Model::query()->where('status', '!=', 'paid')->sum('amount')
 *   where status = active, due_date >= now, order by due_date, limit 5
 *                                      → Model::query()->where(...)->where(...)->orderBy(...)->limit(5)
 */
class WidgetQueryParser
{
    /**
     * @param  array<int, string>  $states  State names for the entity
     * @param  array<string, array<int, array{case: string, label: string}>>  $enums  Enum definitions
     */
    public function __construct(
        protected string $modelName,
        protected array $states = [],
        protected array $enums = [],
    ) {}

    /**
     * Parse an aggregate query DSL string (for stats widgets).
     *
     * Handles: count(*), count(*) where ..., sum(field), sum(field) where ...
     */
    public function parseAggregate(string $dsl): string
    {
        $dsl = trim($dsl);

        if ($dsl === '') {
            return "// TODO: empty query DSL\n{$this->modelName}::query()->count()";
        }

        // Match aggregate function: count(*) or sum(field)
        if (preg_match('/^(count|sum|avg|min|max)\(([^)]*)\)\s*(?:where\s+(.+))?$/i', $dsl, $matches)) {
            $function = strtolower($matches[1]);
            $field = trim($matches[2]);
            $whereClause = trim($matches[3] ?? '');

            $query = "{$this->modelName}::query()";

            if ($whereClause !== '') {
                $query .= $this->parseWhereConditions($whereClause);
            }

            if ($function === 'count') {
                $query .= '->count()';
            } else {
                $fieldName = $field !== '' && $field !== '*' ? "'{$field}'" : "'id'";
                $query .= "->{$function}({$fieldName})";
            }

            return $query;
        }

        return "// TODO: Could not parse query DSL: {$dsl}\n{$this->modelName}::query()->count()";
    }

    /**
     * Parse a table query DSL string (for table widgets).
     *
     * Handles: where field = value, field2 >= now, order by field3, limit N
     */
    public function parseTableQuery(string $dsl): string
    {
        $dsl = trim($dsl);

        if ($dsl === '') {
            return "{$this->modelName}::query()->latest()->limit(5)";
        }

        $query = "{$this->modelName}::query()";
        $clauses = array_map('trim', explode(',', $dsl));

        foreach ($clauses as $clause) {
            if ($clause === '') {
                continue;
            }

            // "order by field [asc|desc]"
            if (preg_match('/^order\s+by\s+(\w+)(?:\s+(asc|desc))?$/i', $clause, $m)) {
                $direction = isset($m[2]) ? "'".strtolower($m[2])."'" : '';
                $query .= $direction !== ''
                    ? "->orderBy('{$m[1]}', {$direction})"
                    : "->orderBy('{$m[1]}')";

                continue;
            }

            // "limit N"
            if (preg_match('/^limit\s+(\d+)$/i', $clause, $m)) {
                $query .= "->limit({$m[1]})";

                continue;
            }

            // "where field op value" or just "field op value"
            $condition = $clause;
            if (preg_match('/^where\s+/i', $condition)) {
                $condition = preg_replace('/^where\s+/i', '', $condition);
            }

            $query .= $this->parseSingleCondition($condition);
        }

        return $query;
    }

    /**
     * Parse where conditions (potentially multiple joined by "and").
     */
    protected function parseWhereConditions(string $whereClause): string
    {
        $result = '';
        $conditions = array_map('trim', preg_split('/\s+and\s+/i', $whereClause));

        foreach ($conditions as $condition) {
            if ($condition !== '') {
                $result .= $this->parseSingleCondition($condition);
            }
        }

        return $result;
    }

    /**
     * Parse a single where condition: "field op value".
     */
    protected function parseSingleCondition(string $condition): string
    {
        // Match: field operator value
        if (! preg_match('/^(\w+(?:\.\w+)?)\s*(=|!=|<>|>=|<=|>|<)\s*(.+)$/', $condition, $m)) {
            return "// TODO: Could not parse condition: {$condition}\n";
        }

        $field = $m[1];
        $operator = $m[2];
        $value = trim($m[3]);

        $resolvedValue = $this->resolveValue($field, $value);

        if ($operator === '=') {
            return "->where('{$field}', {$resolvedValue})";
        }

        return "->where('{$field}', '{$operator}', {$resolvedValue})";
    }

    /**
     * Resolve a value reference — state class, enum, special keyword, or string literal.
     */
    protected function resolveValue(string $field, string $value): string
    {
        // Special keyword: "now"
        if (strtolower($value) === 'now') {
            return 'now()';
        }

        // Special keyword: "true" / "false"
        if (strtolower($value) === 'true') {
            return 'true';
        }

        if (strtolower($value) === 'false') {
            return 'false';
        }

        // Numeric
        if (is_numeric($value)) {
            return $value;
        }

        // Check if it matches a known state name
        if ($field === 'status' && ! empty($this->states) && in_array($value, $this->states, true)) {
            $stateClass = Str::studly($value);

            return "{$stateClass}::getMorphClass()";
        }

        // Check if it matches a known enum value
        foreach ($this->enums as $enumName => $cases) {
            foreach ($cases as $case) {
                if (strtolower($case['case']) === strtolower($value)) {
                    return "\\App\\Enums\\{$enumName}::{$case['case']}->value";
                }
            }
        }

        // Default: string literal
        return "'{$value}'";
    }

    /**
     * Parse a condition color expression.
     *
     * Input: "> 0: danger, else: success"
     * Output: "$value > 0 ? 'danger' : 'success'"
     */
    public static function parseConditionColor(string $expression): ?string
    {
        $expression = trim($expression);

        if ($expression === '') {
            return null;
        }

        // Match: "{op} {value}: {color}, else: {color}"
        if (preg_match('/^([><=!]+)\s*(\d+)\s*:\s*(\w+),\s*else:\s*(\w+)$/', $expression, $m)) {
            $op = $m[1];
            $threshold = $m[2];
            $trueColor = $m[3];
            $falseColor = $m[4];

            return "\$value {$op} {$threshold} ? '{$trueColor}' : '{$falseColor}'";
        }

        return null;
    }

    /**
     * Convert a column format spec to a Filament column method chain.
     *
     * Supported formats: bold, date, datetime, badge, money, numeric, boolean
     */
    public static function columnFormatToFilament(string $format): string
    {
        return match ($format) {
            'bold' => "->weight('bold')",
            'date' => '->date()',
            'datetime' => '->dateTime()',
            'badge' => '->badge()',
            'money' => "->money('usd')",
            'numeric' => '->numeric()',
            'boolean' => '->boolean()',
            default => $format !== '' ? "// TODO: unknown format '{$format}'" : '',
        };
    }
}
