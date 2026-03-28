<?php

declare(strict_types=1);

namespace Aicl\Filament\Pages\Auth;

use Aicl\AiclPlugin;
use Filament\Auth\Pages\Register as BaseRegister;

/**
 * Register.
 */
class Register extends BaseRegister
{
    /** @codeCoverageIgnore Reason: filament-closure -- Registration mount requires Filament panel boot */
    public function mount(): void
    {
        if (! AiclPlugin::isRegistrationEnabled()) {
            $this->redirect(filament()->getLoginUrl());

            return;
        }

        // @codeCoverageIgnoreStart — Filament Livewire rendering
        parent::mount();
        // @codeCoverageIgnoreEnd
    }
}
