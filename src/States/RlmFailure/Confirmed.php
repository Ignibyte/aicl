<?php

namespace Aicl\States\RlmFailure;

use Aicl\States\RlmFailureState;

class Confirmed extends RlmFailureState
{
    public function label(): string
    {
        return 'Confirmed';
    }

    public function color(): string
    {
        return 'success';
    }

    public function icon(): string
    {
        return 'heroicon-o-play';
    }
}
