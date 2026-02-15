<?php

namespace Aicl\AI\Contracts;

use NeuronAI\Tools\ToolInterface;

interface AiTool extends ToolInterface
{
    /**
     * Category for grouping in UI (e.g., "queries", "system", "entities").
     */
    public function category(): string;

    /**
     * Whether this tool requires authentication context.
     * If true, the current user is injected before execution.
     */
    public function requiresAuth(): bool;
}
