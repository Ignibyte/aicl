<?php

declare(strict_types=1);

namespace Aicl\States\AiConversation;

use Aicl\Models\AiConversation;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @extends State<AiConversation>
 */
abstract class AiConversationState extends State
{
    abstract public function label(): string;

    abstract public function color(): string;

    abstract public function icon(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Active::class)
            ->allowTransition(Active::class, Summarized::class)
            ->allowTransition(Active::class, Archived::class)
            ->allowTransition(Summarized::class, Archived::class)
            ->allowTransition(Archived::class, Active::class);
    }
}
