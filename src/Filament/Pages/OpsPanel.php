<?php

namespace Aicl\Filament\Pages;

use Aicl\Health\HealthCheckRegistry;
use Aicl\Health\ServiceCheckResult;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Operations panel page showing service health check statuses.
 *
 * Displays the cached results of all registered health checks (Swoole,
 * PostgreSQL, Redis, Reverb, Elasticsearch, Queue, Scheduler, Application)
 * with a force-refresh action. Restricted to super_admin and admin roles.
 *
 * @see HealthCheckRegistry  Registry that runs and caches health checks
 */
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
     * Get the page header actions (force refresh button).
     *
     * @return array<Action>
     */
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

    /**
     * Restrict access to admin and super_admin roles.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }
}
