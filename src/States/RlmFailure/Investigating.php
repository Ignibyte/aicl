<?php

namespace Aicl\States\RlmFailure;

use Aicl\States\RlmFailureState;

class Investigating extends RlmFailureState
{
    public function label(): string
    {
        return 'Investigating';
    }

    public function color(): string
    {
        return 'warning';
    }

    public function icon(): string
    {
        return 'heroicon-o-pause';
    }
}
