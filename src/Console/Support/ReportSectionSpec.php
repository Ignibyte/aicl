<?php

namespace Aicl\Console\Support;

/**
 * Represents a single section in a ## Report Layout → ### Single Report spec.
 *
 * Each row in the Single Report table maps to one section:
 * | Section | Type | Fields |
 */
class ReportSectionSpec
{
    /**
     * @param  string  $section  Section label (e.g., 'Header', 'Details', 'Description', 'Tags', 'Activity')
     * @param  string  $type  Display type (e.g., 'title', 'badges', 'info-grid', 'card', 'timeline')
     * @param  string  $fields  Raw fields string (e.g., 'status, priority' or 'invoice_number, owner.name, due_date:date')
     * @param  array<int, ReportFieldSpec>  $parsedFields  Parsed individual field references
     */
    public function __construct(
        public string $section,
        public string $type,
        public string $fields,
        public array $parsedFields = [],
    ) {}

    /**
     * Whether this is a title section (single field, displayed as h1).
     */
    public function isTitle(): bool
    {
        return $this->type === 'title';
    }

    /**
     * Whether this is an info-grid section (two-column key-value layout).
     */
    public function isInfoGrid(): bool
    {
        return $this->type === 'info-grid';
    }

    /**
     * Whether this is a badges section (inline colored spans).
     */
    public function isBadges(): bool
    {
        return $this->type === 'badges';
    }

    /**
     * Whether this is a card section (text content block).
     */
    public function isCard(): bool
    {
        return $this->type === 'card';
    }

    /**
     * Whether this is a timeline section (activity log).
     */
    public function isTimeline(): bool
    {
        return $this->type === 'timeline';
    }
}
