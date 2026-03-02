<?php

namespace Aicl\Filament\Pages\Auth;

use Aicl\AiclPlugin;
use Filament\Auth\Pages\Register as BaseRegister;

class Register extends BaseRegister
{
    public function mount(): void
    {
        if (! AiclPlugin::isRegistrationEnabled()) {
            $this->redirect(filament()->getLoginUrl());

            return;
        }

        parent::mount();
    }
}
