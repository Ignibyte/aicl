<?php

namespace Aicl\States\RlmFailure;

use Aicl\States\RlmFailureState;

class Resolved extends RlmFailureState
{
    public function label(): string
    {
        return 'Resolved';
    }

    public function color(): string
    {
        return 'info';
    }

    public function icon(): string
    {
        return 'heroicon-o-check-circle';
    }
}
