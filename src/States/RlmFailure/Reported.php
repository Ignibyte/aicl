<?php

namespace Aicl\States\RlmFailure;

use Aicl\States\RlmFailureState;

class Reported extends RlmFailureState
{
    public function label(): string
    {
        return 'Reported';
    }

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): string
    {
        return 'heroicon-o-pencil-square';
    }
}
