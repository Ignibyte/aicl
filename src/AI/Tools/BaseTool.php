<?php

namespace Aicl\AI\Tools;

use Aicl\AI\Contracts\AiTool;
use Aicl\AI\Enums\ToolRenderType;
use NeuronAI\Tools\Tool;

abstract class BaseTool extends Tool implements AiTool
{
    protected ?int $authenticatedUserId = null;

    public function category(): string
    {
        return 'general';
    }

    public function requiresAuth(): bool
    {
        return false;
    }

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

    public function setAuthenticatedUser(int $userId): static
    {
        $this->authenticatedUserId = $userId;

        return $this;
    }

    public function getAuthenticatedUserId(): ?int
    {
        return $this->authenticatedUserId;
    }
}
