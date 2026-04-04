<?php

declare(strict_types=1);

namespace Aicl\Filament\Pages;

use Aicl\AiclServiceProvider;
use Aicl\Health\HealthCheckRegistry;
use Aicl\Health\ServiceCheckResult;
use BackedEnum;
use Composer\InstalledVersions;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Redis;
use Throwable;
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
     * Get runtime stack version information for display.
     *
     * @return array<string, string>
     */
    public function getStackVersions(): array
    {
        return [
            'AICL' => AiclServiceProvider::VERSION,
            'PHP' => PHP_VERSION,
            'Laravel' => app()->version(),
            'Filament' => $this->getComposerVersion('filament/filament'),
            'Swoole' => defined('SWOOLE_VERSION') ? SWOOLE_VERSION : 'N/A',
        ];
    }

    /**
     * Get a package version from Composer's installed versions registry.
     */
    private function getComposerVersion(string $package): string
    {
        try {
            return InstalledVersions::getPrettyVersion($package) ?? 'Unknown';
        } catch (Throwable) {
            return 'Unknown';
        }
    }

    /**
     * Get all service check results from cache — called by the Blade template.
     *
     * @return array<ServiceCheckResult>
     */
    public function getServiceChecks(): array
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        return app(HealthCheckRegistry::class)->runAllCached();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the page header actions (force refresh button).
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        return [
            Action::make('forceRefresh')
                ->label('Force Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    app(HealthCheckRegistry::class)->forceRefresh();
                }),
        ];
        // @codeCoverageIgnoreEnd
    }

    /**
     * Restrict access to admin and super_admin roles.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            // @codeCoverageIgnoreStart — Filament Livewire rendering
            return false;
            // @codeCoverageIgnoreEnd
        }

        return $user->hasRole(['super_admin', 'admin']);
    }
}
