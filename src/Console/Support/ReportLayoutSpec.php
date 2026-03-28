<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

/**
 * Container for the parsed ## Report Layout spec section.
 *
 * Holds both the Single Report sections and List Report columns.
 */
class ReportLayoutSpec
{
    /**
     * @param  array<int, ReportSectionSpec>  $singleReport  Sections for the single-record PDF report
     * @param  array<int, ReportColumnSpec>  $listReport  Columns for the list/table PDF report
     */
    public function __construct(
        public array $singleReport = [],
        public array $listReport = [],
    ) {}

    /**
     * Whether the spec has a single report definition.
     */
    public function hasSingleReport(): bool
    {
        return ! empty($this->singleReport);
    }

    /**
     * Whether the spec has a list report definition.
     */
    public function hasListReport(): bool
    {
        return ! empty($this->listReport);
    }
}
