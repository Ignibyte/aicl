<?php

namespace Aicl\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitted when RLM GC maintenance discovers notable conditions.
 */
class RlmMaintenanceComplete
{
    use Dispatchable;

    public function __construct(
        public int $stalePatternCount = 0,
        public int $cleanedRecordCount = 0,
        public int $brokenLinkCount = 0,
    ) {}
}
