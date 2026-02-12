<?php

namespace Aicl\States;

use Aicl\States\RlmFailure\Confirmed;
use Aicl\States\RlmFailure\Deprecated;
use Aicl\States\RlmFailure\Investigating;
use Aicl\States\RlmFailure\Reported;
use Aicl\States\RlmFailure\Resolved;
use Aicl\States\RlmFailure\WontFix;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class RlmFailureState extends State
{
    abstract public function label(): string;

    abstract public function color(): string;

    abstract public function icon(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Reported::class)
            ->registerState([
                Reported::class,
                Confirmed::class,
                Investigating::class,
                Resolved::class,
                WontFix::class,
                Deprecated::class,
            ])
            ->allowTransition(Reported::class, Confirmed::class)
            ->allowTransition(Reported::class, Deprecated::class)
            ->allowTransition(Confirmed::class, Investigating::class)
            ->allowTransition(Confirmed::class, WontFix::class)
            ->allowTransition(Confirmed::class, Deprecated::class)
            ->allowTransition(Investigating::class, Resolved::class)
            ->allowTransition(Investigating::class, WontFix::class);
    }
}
