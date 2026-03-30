<?php

declare(strict_types=1);

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
        // @codeCoverageIgnoreStart — AI provider dependency
        return 'general';
        // @codeCoverageIgnoreEnd
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
        // @codeCoverageIgnoreStart — AI provider dependency
        return ToolRenderType::Text;
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return array{type: string, data: mixed}
     */
    public function formatResultForDisplay(mixed $result): array
    {
        // @codeCoverageIgnoreStart — AI provider dependency
        return [
            'type' => $this->renderAs()->value,
            'data' => $result,
        ];
        // @codeCoverageIgnoreEnd
    }

    /**
     * Inject the authenticated user ID for auth-required tools.
     *
     * @param int $userId The user's primary key
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
