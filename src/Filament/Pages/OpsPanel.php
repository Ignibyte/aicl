<?php

namespace Aicl\Filament\Pages;

use Aicl\Health\HealthCheckRegistry;
use Aicl\Health\ServiceCheckResult;
use Aicl\Services\PresenceRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class OpsPanel extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Ops Panel';

    protected static ?string $title = 'Ops Panel';

    protected static ?string $slug = 'ops-panel';

    protected string $view = 'aicl::filament.pages.ops-panel';

    /**
     * Get all service check results from cache — called by the Blade template.
     *
     * @return array<ServiceCheckResult>
     */
    public function getServiceChecks(): array
    {
        return app(HealthCheckRegistry::class)->runAllCached();
    }

    /**
     * Get all active admin sessions from the PresenceRegistry.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getActiveSessions(): Collection
    {
        return app(PresenceRegistry::class)->allSessions();
    }

    /**
     * Terminate a session by its ID (super_admin only).
     */
    public function terminateSession(string $sessionId): void
    {
        $user = auth()->user();

        if (! $user || ! $user->hasRole('super_admin')) {
            Notification::make()
                ->title('Unauthorized')
                ->body('Only super admins can terminate sessions.')
                ->danger()
                ->send();

            return;
        }

        $currentSessionId = request()->hasSession() ? request()->session()->getId() : null;

        if ($currentSessionId !== null && $sessionId === $currentSessionId) {
            Notification::make()
                ->title('Cannot Terminate')
                ->body('You cannot terminate your own current session.')
                ->warning()
                ->send();

            return;
        }

        $result = app(PresenceRegistry::class)->terminateSession($sessionId);

        if ($result) {
            Notification::make()
                ->title('Session Terminated')
                ->body('The session has been forcefully terminated.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Session Not Found')
                ->body('The session may have already expired.')
                ->warning()
                ->send();
        }
    }

    public function killSessionAction(): Action
    {
        return Action::make('killSession')
            ->label('Kill Session')
            ->color('danger')
            ->icon('heroicon-o-x-mark')
            ->requiresConfirmation()
            ->modalHeading('Terminate Session')
            ->modalDescription(fn (array $arguments): string => 'Are you sure you want to terminate the session for '.($arguments['userName'] ?? 'this user').'? They will be logged out immediately.')
            ->modalSubmitActionLabel('Yes, terminate')
            ->action(fn (array $arguments) => $this->terminateSession($arguments['sessionId'] ?? ''));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('forceRefresh')
                ->label('Force Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    app(HealthCheckRegistry::class)->forceRefresh();
                }),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }
}
