<?php

declare(strict_types=1);

namespace Aicl\Policies;

/**
 * AiAgentPolicy.
 */
class AiAgentPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'AiAgent';
    }
}
