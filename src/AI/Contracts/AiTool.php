<?php

namespace Aicl\AI\Contracts;

use Aicl\AI\Enums\ToolRenderType;
use NeuronAI\Tools\ToolInterface;

/**
 * Contract for AICL AI tools that extend NeuronAI's ToolInterface.
 *
 * Adds AICL-specific metadata: category grouping, authentication requirements,
 * frontend rendering hints, and structured result formatting.
 */
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

    /**
     * How the tool's result should be rendered in the frontend.
     */
    public function renderAs(): ToolRenderType;

    /**
     * Transform the raw __invoke() result into a structured shape
     * the frontend can render as a card/table/badge.
     *
     * @return array{type: string, data: mixed}
     */
    public function formatResultForDisplay(mixed $result): array;
}
