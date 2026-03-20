<?php

namespace Aicl\States\AiAgent;

use Aicl\Models\AiAgent;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @extends State<AiAgent>
 */
abstract class AiAgentState extends State
{
    abstract public function label(): string;

    abstract public function color(): string;

    abstract public function icon(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Active::class)
            ->allowTransition(Active::class, Archived::class)
            ->allowTransition(Archived::class, Active::class);
    }
}
