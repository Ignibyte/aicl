<?php

namespace Aicl\AI\Tools;

use Aicl\AI\Contracts\AiTool;
use Aicl\AI\Enums\ToolRenderType;
use NeuronAI\Tools\Tool;

/**
 * Abstract base class for AICL AI tools.
 *
 * Provides sensible defaults for the AiTool contract: general category,
 * no auth required, text rendering, pass-through result formatting.
 * Subclasses implement the NeuronAI __invoke() method and override
 * metadata methods as needed.
 */
abstract class BaseTool extends Tool implements AiTool
{
    /** @var int|null The authenticated user's ID, injected by AiToolRegistry */
    protected ?int $authenticatedUserId = null;

    /**
     * Get the tool category for UI grouping.
     */
    public function category(): string
    {
        return 'general';
    }

    /**
     * Whether this tool requires an authenticated user context.
     */
    public function requiresAuth(): bool
    {
        return false;
    }

    /**
     * Get the frontend rendering type for this tool's output.
     */
    public function renderAs(): ToolRenderType
    {
        return ToolRenderType::Text;
    }

    /**
     * @return array{type: string, data: mixed}
     */
    public function formatResultForDisplay(mixed $result): array
    {
        return [
            'type' => $this->renderAs()->value,
            'data' => $result,
        ];
    }

    /**
     * Inject the authenticated user ID for auth-required tools.
     *
     * @param  int  $userId  The user's primary key
     */
    public function setAuthenticatedUser(int $userId): static
    {
        $this->authenticatedUserId = $userId;

        return $this;
    }

    /**
     * Get the injected authenticated user ID.
     */
    public function getAuthenticatedUserId(): ?int
    {
        return $this->authenticatedUserId;
    }
}
