<?php

namespace Aicl\Console\Support;

use InvalidArgumentException;

/**
 * Parse a .entity.md specification file into an EntitySpec value object.
 *
 * The spec file uses structured Markdown with typed sections:
 * - # Name + description paragraph (required)
 * - ## Fields table (required)
 * - ## Enums subsections (required if enum fields exist)
 * - ## States fenced block (optional)
 * - ## Relationships table (optional)
 * - ## Traits bullet list (optional)
 * - ## Options key-value list (optional)
 * - ## Business Rules bullet list (optional)
 * - ## Widget Hints bullet list (optional)
 * - ## Notification Hints bullet list (optional)
 * - ## Observer Rules structured subsections (optional)
 * - ## Report Layout structured subsections (optional)
 */
class SpecFileParser
{
    /**
     * @var array<int, string>
     */
    protected const SUPPORTED_TYPES = [
        'string', 'text', 'integer', 'float', 'boolean',
        'date', 'datetime', 'enum', 'json', 'foreignId',
    ];

    /**
     * @var array<int, string>
     */
    protected const RESERVED_COLUMNS = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * @var array<int, string>
     */
    protected const AUTO_COLUMNS = ['is_active', 'owner_id'];

    /**
     * Parse a .entity.md file into an EntitySpec.
     *
     * @throws InvalidArgumentException
     */
    public function parse(string $filePath): EntitySpec
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("Spec file not found: {$filePath}");
        }

        $content = file_get_contents($filePath);

        if ($content === false || trim($content) === '') {
            throw new InvalidArgumentException("Spec file is empty: {$filePath}");
        }

        return $this->parseContent($content);
    }

    /**
     * Parse raw Markdown content into an EntitySpec.
     *
     * @throws InvalidArgumentException
     */
    public function parseContent(string $content): EntitySpec
    {
        $sections = $this->splitSections($content);

        $name = $this->parseName($sections);
        $description = $this->parseDescription($sections);
        $fields = $this->parseFields($sections);
        $enums = $this->parseEnums($sections);
        $states = $this->parseStates($sections);
        $relationships = $this->parseRelationships($sections);
        $traits = $this->parseTraits($sections);
        $options = $this->parseOptions($sections);
        $businessRules = $this->parseBulletList($sections, 'Business Rules');
        $widgetSpecs = $this->parseWidgets($sections);
        $notificationSpecs = $this->parseNotifications($sections);
        $observerRules = $this->parseObserverRules($sections);
        $reportLayout = $this->parseReportLayout($sections);
        $widgetHints = $this->parseBulletList($sections, 'Widget Hints');
        $notificationHints = $this->parseBulletList($sections, 'Notification Hints');

        $baseClass = $options['base'] ?? null;
        unset($options['base']);

        return new EntitySpec(
            name: $name,
            description: $description,
            fields: $fields,
            states: $states['states'],
            defaultState: $states['default'],
            stateTransitions: $states['transitions'],
            relationships: $relationships,
            enums: $enums,
            traits: $traits,
            options: $options,
            businessRules: $businessRules,
            widgetHints: $widgetHints,
            notificationHints: $notificationHints,
            baseClass: $baseClass,
            widgetSpecs: $widgetSpecs,
            notificationSpecs: $notificationSpecs,
            observerRules: $observerRules,
            reportLayout: $reportLayout,
        );
    }

    /**
     * Split Markdown content into sections keyed by header name.
     *
     * @return array<string, string>
     */
    protected function splitSections(string $content): array
    {
        return MarkdownTableParser::splitSections($content);
    }

    /**
     * @param  array<string, string>  $sections
     *
     * @throws InvalidArgumentException
     */
    protected function parseName(array $sections): string
    {
        if (! isset($sections['_name']) || trim($sections['_name']) === '') {
            throw new InvalidArgumentException('Spec file must start with a # Name header.');
        }

        $name = trim($sections['_name']);

        if (! preg_match('/^[A-Z][a-zA-Z0-9]+$/', $name)) {
            throw new InvalidArgumentException("Entity name '{$name}' must be PascalCase.");
        }

        return $name;
    }

    /**
     * @param  array<string, string>  $sections
     */
    protected function parseDescription(array $sections): string
    {
        $headerContent = $sections['_header'] ?? '';
        $lines = array_filter(
            explode("\n", $headerContent),
            fn (string $line): bool => trim($line) !== '' && trim($line) !== '---'
        );

        return trim(implode("\n", $lines));
    }

    /**
     * @param  array<string, string>  $sections
     * @return array<int, FieldDefinition>
     *
     * @throws InvalidArgumentException
     */
    protected function parseFields(array $sections): array
    {
        if (! isset($sections['Fields'])) {
            throw new InvalidArgumentException('Spec file must have a ## Fields section.');
        }

        $rows = $this->parseMarkdownTable($sections['Fields']);

        if (empty($rows)) {
            throw new InvalidArgumentException('## Fields section must contain at least one field.');
        }

        $definitions = [];
        $seenNames = [];

        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');
            $typeStr = trim($row['type'] ?? '');
            $modifiersStr = trim($row['modifiers'] ?? '');

            if ($name === '' || $typeStr === '') {
                continue;
            }

            $this->validateFieldName($name, $seenNames);
            $seenNames[] = $name;

            $definition = $this->parseFieldTypeAndModifiers($name, $typeStr, $modifiersStr);
            $definitions[] = $definition;
        }

        return $definitions;
    }

    /**
     * @param  array<int, string>  $seenNames
     *
     * @throws InvalidArgumentException
     */
    protected function validateFieldName(string $name, array $seenNames): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Field name '{$name}' must be snake_case.");
        }

        if (in_array($name, $seenNames, true)) {
            throw new InvalidArgumentException("Duplicate field name: '{$name}'.");
        }
    }

    /**
     * Parse a field's type column (which may contain type:argument) and modifiers column.
     */
    protected function parseFieldTypeAndModifiers(string $name, string $typeStr, string $modifiersStr): FieldDefinition
    {
        // Type column may be "enum:ClassName" or "foreignId:tableName"
        $typeParts = explode(':', $typeStr, 2);
        $type = $typeParts[0];
        $typeArgument = $typeParts[1] ?? null;

        if (! in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new InvalidArgumentException(
                "Unknown field type: '{$type}' for field '{$name}'. Supported: ".implode(', ', self::SUPPORTED_TYPES)
            );
        }

        if (in_array($type, ['enum', 'foreignId'], true) && $typeArgument === null) {
            $hint = $type === 'enum' ? 'enum:ClassName' : 'foreignId:tableName';

            throw new InvalidArgumentException(
                ucfirst($type)." field '{$name}' requires an argument. Format: {$hint}"
            );
        }

        // Validate type argument
        if ($type === 'enum' && $typeArgument !== null && ! preg_match('/^[A-Z][a-zA-Z0-9]+$/', $typeArgument)) {
            throw new InvalidArgumentException(
                "Enum class name '{$typeArgument}' for field '{$name}' must be PascalCase."
            );
        }

        if ($type === 'foreignId' && $typeArgument !== null && ! preg_match('/^[a-z][a-z0-9_]*$/', $typeArgument)) {
            throw new InvalidArgumentException(
                "Table name '{$typeArgument}' for field '{$name}' must be snake_case."
            );
        }

        // Parse modifiers — pipe-separated in spec files (per Amendment #3)
        $modifiers = $this->parseModifiers($modifiersStr);

        $nullable = in_array('nullable', $modifiers, true);
        $unique = in_array('unique', $modifiers, true);
        $indexed = in_array('index', $modifiers, true);
        $default = null;

        foreach ($modifiers as $modifier) {
            if (preg_match('/^default\((.+)\)$/', $modifier, $matches)) {
                $default = $matches[1];
            }
        }

        // Apply type-specific defaults (same as FieldParser)
        if (in_array($type, ['text', 'date', 'datetime', 'json'], true) && ! $nullable) {
            $nullable = true;
        }

        if ($type === 'boolean' && $default === null) {
            $default = 'true';
        }

        return new FieldDefinition(
            name: $name,
            type: $type,
            typeArgument: $typeArgument,
            nullable: $nullable,
            unique: $unique,
            default: $default,
            indexed: $indexed,
        );
    }

    /**
     * Parse pipe-separated modifiers from the Modifiers column.
     *
     * @return array<int, string>
     */
    protected function parseModifiers(string $modifiersStr): array
    {
        if (trim($modifiersStr) === '') {
            return [];
        }

        return array_filter(
            array_map('trim', explode('|', $modifiersStr)),
            fn (string $m): bool => $m !== ''
        );
    }

    /**
     * Parse the ## Enums section into rich enum definitions.
     *
     * @param  array<string, string>  $sections
     * @return array<string, array<int, array{case: string, label: string, color?: string, icon?: string}>>
     */
    protected function parseEnums(array $sections): array
    {
        if (! isset($sections['Enums'])) {
            return [];
        }

        $enums = [];
        $content = $sections['Enums'];

        // Split by ### subsections
        $subsections = preg_split('/^### /m', $content, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($subsections as $subsection) {
            $lines = explode("\n", $subsection, 2);
            $enumName = trim($lines[0]);

            if ($enumName === '' || ! preg_match('/^[A-Z][a-zA-Z0-9]+$/', $enumName)) {
                continue;
            }

            $tableContent = $lines[1] ?? '';
            $rows = $this->parseMarkdownTable($tableContent);

            $cases = [];

            foreach ($rows as $row) {
                $case = trim($row['case'] ?? '');
                $label = trim($row['label'] ?? '');

                if ($case === '' || $label === '') {
                    continue;
                }

                $entry = ['case' => $case, 'label' => $label];

                $color = trim($row['color'] ?? '');
                if ($color !== '') {
                    $entry['color'] = $color;
                }

                $icon = trim($row['icon'] ?? '');
                if ($icon !== '') {
                    $entry['icon'] = $icon;
                }

                $cases[] = $entry;
            }

            if (! empty($cases)) {
                $enums[$enumName] = $cases;
            }
        }

        return $enums;
    }

    /**
     * Parse the ## States section.
     *
     * @param  array<string, string>  $sections
     * @return array{states: array<int, string>, default: string, transitions: array<string, array<int, string>>}
     */
    protected function parseStates(array $sections): array
    {
        $result = ['states' => [], 'default' => '', 'transitions' => []];

        if (! isset($sections['States'])) {
            return $result;
        }

        $content = $sections['States'];

        // Extract fenced code block content
        if (preg_match('/```(?:states)?\s*\n(.*?)```/s', $content, $matches)) {
            $transitionText = trim($matches[1]);
        } else {
            // Try without fenced block (plain lines with arrows)
            $transitionText = trim($content);
        }

        if ($transitionText === '') {
            return $result;
        }

        $allStates = [];
        $transitions = [];

        foreach (explode("\n", $transitionText) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Support both → (Unicode) and -> (ASCII)
            $arrow = str_contains($line, '→') ? '→' : '->';
            $parts = array_map('trim', explode($arrow, $line));

            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }

            $from = $parts[0];
            $to = $parts[1];

            $allStates[$from] = true;
            $allStates[$to] = true;

            if (! isset($transitions[$from])) {
                $transitions[$from] = [];
            }

            if (! in_array($to, $transitions[$from], true)) {
                $transitions[$from][] = $to;
            }
        }

        $states = array_keys($allStates);

        // Check for explicit default
        $default = $states[0] ?? '';
        if (preg_match('/Default:\s*`?([a-z_]+)`?/i', $content, $defaultMatch)) {
            $default = trim($defaultMatch[1]);
        }

        $result['states'] = $states;
        $result['default'] = $default;
        $result['transitions'] = $transitions;

        return $result;
    }

    /**
     * Parse the ## Relationships section.
     *
     * @param  array<string, string>  $sections
     * @return array<int, RelationshipDefinition>
     */
    protected function parseRelationships(array $sections): array
    {
        if (! isset($sections['Relationships'])) {
            return [];
        }

        $rows = $this->parseMarkdownTable($sections['Relationships']);
        $definitions = [];

        foreach ($rows as $row) {
            $method = trim($row['method'] ?? '');
            $type = trim($row['type'] ?? '');
            $relatedModel = trim($row['related model'] ?? $row['related_model'] ?? '');
            $foreignKey = trim($row['foreign key'] ?? $row['foreign_key'] ?? '');

            if ($method === '' || $type === '' || $relatedModel === '') {
                continue;
            }

            $definitions[] = new RelationshipDefinition(
                name: $method,
                type: $type,
                relatedModel: $relatedModel,
                foreignKey: $foreignKey !== '' ? $foreignKey : null,
            );
        }

        return $definitions;
    }

    /**
     * Parse the ## Traits section.
     *
     * @param  array<string, string>  $sections
     * @return array<int, string>
     */
    protected function parseTraits(array $sections): array
    {
        if (! isset($sections['Traits'])) {
            return ['HasEntityEvents', 'HasAuditTrail', 'HasStandardScopes'];
        }

        return $this->parseBulletList($sections, 'Traits');
    }

    /**
     * Parse the ## Options section.
     *
     * @param  array<string, string>  $sections
     * @return array<string, mixed>
     */
    protected function parseOptions(array $sections): array
    {
        if (! isset($sections['Options'])) {
            return [];
        }

        $options = [];
        $content = $sections['Options'];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            // Match "- key: value" or "key: value"
            if (preg_match('/^-?\s*([a-z][a-z0-9_-]*)\s*:\s*(.+)$/i', $line, $matches)) {
                $key = trim($matches[1]);
                $value = trim($matches[2]);

                // Convert string values to appropriate types
                $options[$key] = $this->castOptionValue($value);
            }
        }

        return $options;
    }

    /**
     * Cast an option value string to its appropriate PHP type.
     */
    protected function castOptionValue(string $value): mixed
    {
        $lower = strtolower($value);

        if ($lower === 'true') {
            return true;
        }

        if ($lower === 'false') {
            return false;
        }

        if ($lower === 'null') {
            return null;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * Parse a structured ## Widgets section into WidgetSpec value objects.
     *
     * Returns null if no structured Widgets section exists (legacy Widget Hints fallback).
     *
     * @param  array<string, string>  $sections
     * @return array<int, WidgetSpec>|null
     */
    protected function parseWidgets(array $sections): ?array
    {
        if (! isset($sections['Widgets'])) {
            return null;
        }

        $subsections = MarkdownTableParser::splitSections($sections['Widgets'], 3);
        $widgets = [];

        // StatsOverview
        if (isset($subsections['StatsOverview'])) {
            $rows = MarkdownTableParser::parseMarkdownTable($subsections['StatsOverview']);
            $metrics = [];

            foreach ($rows as $row) {
                $label = trim($row['metric'] ?? '');
                $query = trim($row['query'] ?? '');
                $color = trim($row['color'] ?? 'primary');
                $conditionColor = trim($row['condition color'] ?? '');

                if ($label === '' || $query === '') {
                    continue;
                }

                $metrics[] = new MetricDefinition(
                    label: $label,
                    query: $query,
                    color: $color,
                    conditionColor: $conditionColor !== '' ? $conditionColor : null,
                );
            }

            if (! empty($metrics)) {
                $widgets[] = new WidgetSpec(
                    type: 'stats',
                    name: 'StatsOverview',
                    metrics: $metrics,
                );
            }
        }

        // Chart
        if (isset($subsections['Chart'])) {
            $rows = MarkdownTableParser::parseMarkdownTable($subsections['Chart']);

            foreach ($rows as $row) {
                $chartType = trim($row['type'] ?? 'doughnut');
                $groupBy = trim($row['group by'] ?? $row['groupby'] ?? '');
                $colorsStr = trim($row['colors'] ?? '');

                $colors = [];
                if ($colorsStr !== '') {
                    foreach (explode(',', $colorsStr) as $pair) {
                        $pair = trim($pair);
                        if (str_contains($pair, ':')) {
                            [$key, $value] = array_map('trim', explode(':', $pair, 2));
                            $colors[$key] = $value;
                        }
                    }
                }

                $widgets[] = new WidgetSpec(
                    type: 'chart',
                    name: 'Chart',
                    chartType: $chartType,
                    groupBy: $groupBy !== '' ? $groupBy : null,
                    colors: $colors,
                );
            }
        }

        // Table
        if (isset($subsections['Table'])) {
            $rows = MarkdownTableParser::parseMarkdownTable($subsections['Table']);

            foreach ($rows as $row) {
                $widgetName = trim($row['name'] ?? '');
                $query = trim($row['query'] ?? '');
                $columnsStr = trim($row['columns'] ?? '');

                if ($widgetName === '') {
                    continue;
                }

                $columns = [];
                if ($columnsStr !== '') {
                    foreach (explode(',', $columnsStr) as $colDef) {
                        $colDef = trim($colDef);
                        if ($colDef === '') {
                            continue;
                        }

                        if (str_contains($colDef, ':')) {
                            [$colName, $colFormat] = array_map('trim', explode(':', $colDef, 2));
                        } else {
                            $colName = $colDef;
                            $colFormat = '';
                        }

                        $columns[] = new ColumnDefinition(
                            name: $colName,
                            format: $colFormat,
                        );
                    }
                }

                $widgets[] = new WidgetSpec(
                    type: 'table',
                    name: $widgetName,
                    query: $query !== '' ? $query : null,
                    columns: $columns,
                );
            }
        }

        return ! empty($widgets) ? $widgets : null;
    }

    /**
     * Parse a structured ## Notifications section into NotificationSpec value objects.
     *
     * Returns null if no structured Notifications section exists (legacy Notification Hints fallback).
     *
     * @param  array<string, string>  $sections
     * @return array<int, NotificationSpec>|null
     */
    protected function parseNotifications(array $sections): ?array
    {
        if (! isset($sections['Notifications'])) {
            return null;
        }

        $subsections = MarkdownTableParser::splitSections($sections['Notifications'], 3);
        $notifications = [];

        foreach ($subsections as $sectionName => $content) {
            if ($sectionName === '_header' || $sectionName === '_name') {
                continue;
            }

            $rows = MarkdownTableParser::parseMarkdownTable($content);

            if (empty($rows)) {
                continue;
            }

            // Convert key-value rows into an associative map
            $data = [];

            foreach ($rows as $row) {
                $field = trim($row['field'] ?? '');
                $value = trim($row['value'] ?? '');

                if ($field !== '' && $value !== '') {
                    $data[strtolower($field)] = $value;
                }
            }

            $trigger = $data['trigger'] ?? '';
            $title = $data['title'] ?? '';

            if ($trigger === '' || $title === '') {
                continue;
            }

            $channels = ['database'];
            if (isset($data['channels'])) {
                $channels = array_map('trim', explode(',', $data['channels']));
            }

            $notifications[] = new NotificationSpec(
                name: $sectionName,
                trigger: $trigger,
                title: $title,
                body: $data['body'] ?? '',
                icon: $data['icon'] ?? 'heroicon-o-bell',
                color: $data['color'] ?? 'primary',
                recipient: $data['recipient'] ?? 'owner',
                channels: $channels,
            );
        }

        return ! empty($notifications) ? $notifications : null;
    }

    /**
     * Parse ## Observer Rules section into ObserverRuleSpec[].
     *
     * Subsection formats:
     *   ### On Create / ### On Delete: | Action | Details |
     *   ### On Update: | Watch Field | Action | Details |
     *
     * @param  array<string, string>  $sections
     * @return array<int, ObserverRuleSpec>|null
     */
    protected function parseObserverRules(array $sections): ?array
    {
        if (! isset($sections['Observer Rules'])) {
            return null;
        }

        $subsections = MarkdownTableParser::splitSections($sections['Observer Rules'], 3);
        $rules = [];

        foreach ($subsections as $sectionName => $content) {
            if ($sectionName === '_header' || $sectionName === '_name') {
                continue;
            }

            $rows = MarkdownTableParser::parseMarkdownTable($content);

            if (empty($rows)) {
                continue;
            }

            $event = $this->resolveObserverEvent($sectionName);

            if ($event === null) {
                continue;
            }

            foreach ($rows as $row) {
                if ($event === 'updated') {
                    // On Update table: | Watch Field | Action | Details |
                    $watchField = trim($row['watch field'] ?? $row['watch_field'] ?? '');
                    $action = strtolower(trim($row['action'] ?? ''));
                    $details = trim($row['details'] ?? '');

                    if ($action === '' || $details === '') {
                        continue;
                    }

                    $rules[] = new ObserverRuleSpec(
                        event: $event,
                        action: $action,
                        details: $details,
                        watchField: $watchField !== '' ? $watchField : null,
                    );
                } else {
                    // On Create / On Delete table: | Action | Details |
                    $action = strtolower(trim($row['action'] ?? ''));
                    $details = trim($row['details'] ?? '');

                    if ($action === '' || $details === '') {
                        continue;
                    }

                    $rules[] = new ObserverRuleSpec(
                        event: $event,
                        action: $action,
                        details: $details,
                    );
                }
            }
        }

        return ! empty($rules) ? $rules : null;
    }

    /**
     * Parse ## Report Layout with ### Single Report and ### List Report subsections.
     *
     * @param  array<string, string>  $sections
     */
    protected function parseReportLayout(array $sections): ?ReportLayoutSpec
    {
        if (! isset($sections['Report Layout'])) {
            return null;
        }

        $subsections = MarkdownTableParser::splitSections($sections['Report Layout'], 3);

        $singleReport = [];
        $listReport = [];

        // Parse ### Single Report
        if (isset($subsections['Single Report'])) {
            $rows = MarkdownTableParser::parseMarkdownTable($subsections['Single Report']);

            foreach ($rows as $row) {
                $section = trim($row['section'] ?? '');
                $type = strtolower(trim($row['type'] ?? ''));
                $fields = trim($row['fields'] ?? '');

                if ($section === '' || $type === '' || $fields === '') {
                    continue;
                }

                $parsedFields = array_map(
                    fn (string $f) => ReportFieldSpec::parse($f),
                    array_filter(array_map('trim', explode(',', $fields)))
                );

                $singleReport[] = new ReportSectionSpec(
                    section: $section,
                    type: $type,
                    fields: $fields,
                    parsedFields: $parsedFields,
                );
            }
        }

        // Parse ### List Report
        if (isset($subsections['List Report'])) {
            $rows = MarkdownTableParser::parseMarkdownTable($subsections['List Report']);

            foreach ($rows as $row) {
                $column = trim($row['column'] ?? '');
                $format = trim($row['format'] ?? '');

                if ($column === '' || $format === '') {
                    continue;
                }

                $width = trim($row['width'] ?? '');

                $listReport[] = new ReportColumnSpec(
                    column: $column,
                    format: $format,
                    width: $width,
                );
            }
        }

        if (empty($singleReport) && empty($listReport)) {
            return null;
        }

        return new ReportLayoutSpec(
            singleReport: $singleReport,
            listReport: $listReport,
        );
    }

    /**
     * Map subsection name to observer event name.
     */
    protected function resolveObserverEvent(string $sectionName): ?string
    {
        return match (strtolower(trim($sectionName))) {
            'on create' => 'created',
            'on update' => 'updated',
            'on delete' => 'deleted',
            default => null,
        };
    }

    /**
     * Parse a bullet list section into an array of strings.
     *
     * @param  array<string, string>  $sections
     * @return array<int, string>
     */
    protected function parseBulletList(array $sections, string $sectionName): array
    {
        if (! isset($sections[$sectionName])) {
            return [];
        }

        return MarkdownTableParser::parseBulletList($sections[$sectionName]);
    }

    /**
     * Parse a Markdown pipe-delimited table into an array of associative rows.
     *
     * @return array<int, array<string, string>>
     */
    protected function parseMarkdownTable(string $content): array
    {
        return MarkdownTableParser::parseMarkdownTable($content);
    }
}
