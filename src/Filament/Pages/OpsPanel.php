<?php

namespace Aicl\Filament\Pages;

use Aicl\Health\HealthCheckRegistry;
use Aicl\Health\ServiceCheckResult;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
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
