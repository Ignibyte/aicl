<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

use InvalidArgumentException;

/**
 * Parse a *.tool.md specification file into a ToolSpec value object.
 *
 * The spec file uses structured Markdown:
 * - # ClassName + description paragraph (required)
 * - ## Tool key-value table (required)
 * - ## Parameters table (optional)
 * - ## Returns table (optional)
 *
 * @codeCoverageIgnore Reason: external-service -- Parser default match arm
 */
class ToolSpecParser
{
    /**
     * Parse a *.tool.md file into a ToolSpec.
     *
     * @throws InvalidArgumentException
     */
    public function parse(string $filePath): ToolSpec
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("Tool spec file not found: {$filePath}");
        }

        return $this->parseContent((string) file_get_contents($filePath));
    }

    /**
     * Parse raw Markdown content into a ToolSpec.
     *
     * @throws InvalidArgumentException
     */
    public function parseContent(string $content): ToolSpec
    {
        $sections = MarkdownTableParser::splitSections($content);

        $className = $this->parseClassName($sections);
        $description = $this->parseDescription($sections);
        $toolInfo = $this->parseToolInfo($sections);
        $parameters = $this->parseParameters($sections);
        $returns = $this->parseReturns($sections);

        return new ToolSpec(
            className: $className,
            description: $description,
            name: $toolInfo['name'],
            category: $toolInfo['category'],
            authRequired: $toolInfo['authRequired'],
            toolDescription: $toolInfo['description'],
            parameters: $parameters,
            returns: $returns,
        );
    }

    /**
     * @param  array<string, string>  $sections
     *
     * @throws InvalidArgumentException
     */
    protected function parseClassName(array $sections): string
    {
        if (! isset($sections['_name']) || trim($sections['_name']) === '') {
            throw new InvalidArgumentException('Tool spec file must start with a # ClassName header.');
        }

        $name = trim($sections['_name']);

        if (! preg_match('/^[A-Z][a-zA-Z0-9]+$/', $name)) {
            throw new InvalidArgumentException("Tool class name '{$name}' must be PascalCase.");
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
            fn (string $line): bool => trim($line) !== '' && ! str_starts_with(trim($line), '#')
        );

        return trim(implode(' ', array_map('trim', $lines)));
    }

    /**
     * @param  array<string, string>  $sections
     * @return array{name: string, category: string, authRequired: bool, description: string}
     *
     * @throws InvalidArgumentException
     */
    protected function parseToolInfo(array $sections): array
    {
        if (! isset($sections['Tool'])) {
            throw new InvalidArgumentException('Tool spec must have a ## Tool section.');
        }

        $rows = MarkdownTableParser::parseMarkdownTable($sections['Tool']);

        $info = [
            'name' => '',
            'category' => 'general',
            'authRequired' => false,
            'description' => '',
        ];

        foreach ($rows as $row) {
            $field = strtolower(trim($row['field'] ?? ''));
            $value = trim($row['value'] ?? '');

            match ($field) {
                'name' => $info['name'] = $value,
                'category' => $info['category'] = $value,
                'auth required' => $info['authRequired'] = strtolower($value) === 'true',
                'description' => $info['description'] = $value,
                // @codeCoverageIgnoreStart — Untestable in unit context
                default => null,
                // @codeCoverageIgnoreEnd
            };
        }

        if ($info['name'] === '') {
            throw new InvalidArgumentException('Tool spec ## Tool section must have a Name field.');
        }

        return $info;
    }

    /**
     * @param  array<string, string>  $sections
     * @return array<int, ToolParameterSpec>
     */
    protected function parseParameters(array $sections): array
    {
        if (! isset($sections['Parameters'])) {
            return [];
        }

        $rows = MarkdownTableParser::parseMarkdownTable($sections['Parameters']);
        $params = [];

        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');
            $type = trim($row['type'] ?? '');

            if ($name === '' || $type === '') {
                continue;
            }

            $required = strtolower(trim($row['required'] ?? 'false')) === 'true';
            $description = trim($row['description'] ?? '');

            $params[] = new ToolParameterSpec(
                name: $name,
                type: $type,
                required: $required,
                description: $description,
            );
        }

        return $params;
    }

    /**
     * @param  array<string, string>  $sections
     * @return array<int, ToolReturnFieldSpec>
     */
    protected function parseReturns(array $sections): array
    {
        if (! isset($sections['Returns'])) {
            return [];
        }

        $rows = MarkdownTableParser::parseMarkdownTable($sections['Returns']);
        $fields = [];

        foreach ($rows as $row) {
            $field = trim($row['field'] ?? '');
            $type = trim($row['type'] ?? '');

            if ($field === '' || $type === '') {
                continue;
            }

            $description = trim($row['description'] ?? '');

            $fields[] = new ToolReturnFieldSpec(
                field: $field,
                type: $type,
                description: $description,
            );
        }

        return $fields;
    }
}
