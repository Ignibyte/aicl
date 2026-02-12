<?php

namespace Aicl\States\RlmFailure;

use Aicl\States\RlmFailureState;

class Deprecated extends RlmFailureState
{
    public function label(): string
    {
        return 'Deprecated';
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
