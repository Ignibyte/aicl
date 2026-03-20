<?php

declare(strict_types=1);

namespace Aicl\Filament\Resources\Users\Pages;

use Aicl\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/** Edit page for the User resource with 2FA reset action. */
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
                ->visible(function (): bool {
                    $record = $this->getRecord();

                    return $record instanceof User && $record->hasConfirmedTwoFactor();
                })
                ->action(function (): void {
                    $record = $this->getRecord();

                    if (! $record instanceof User) {
                        return;
                    }

                    $record->disableTwoFactorAuthentication();

                    Notification::make()
                        ->success()
                        ->title('Two-Factor Authentication Reset')
                        ->body("2FA has been reset for {$record->name}. They will need to set it up again.")
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
