<?php

namespace Aicl\Policies;

class AiAgentPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'AiAgent';
    }
}
