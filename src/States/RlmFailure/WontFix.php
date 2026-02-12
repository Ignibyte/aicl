<?php

namespace Aicl\States\RlmFailure;

use Aicl\States\RlmFailureState;

class WontFix extends RlmFailureState
{
    public function label(): string
    {
        return 'Wont Fix';
    }

    public function color(): string
    {
        return 'danger';
    }

    public function icon(): string
    {
        return 'heroicon-o-archive-box';
    }
}
