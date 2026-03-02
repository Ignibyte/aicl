<?php

namespace Aicl\Filament\Resources\Users\Pages;

use Aicl\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset_two_factor')
                ->label('Reset 2FA')
                ->icon('heroicon-o-shield-exclamation')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Reset Two-Factor Authentication')
                ->modalDescription('This will remove the user\'s 2FA setup. They will need to set up 2FA again on their next login if required.')
                ->modalSubmitActionLabel('Reset 2FA')
                ->visible(fn (): bool => $this->record->hasConfirmedTwoFactor())
                ->action(function (): void {
                    $this->record->disableTwoFactorAuthentication();

                    Notification::make()
                        ->success()
                        ->title('Two-Factor Authentication Reset')
                        ->body("2FA has been reset for {$this->record->name}. They will need to set it up again.")
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
