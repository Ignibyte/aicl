<?php

namespace Aicl;

use Aicl\Filament\Pages\ApiTokens;
use Aicl\Filament\Pages\AuditLog;
use Aicl\Filament\Pages\Errors\Forbidden;
use Aicl\Filament\Pages\Errors\NotFound;
use Aicl\Filament\Pages\Errors\ServerError;
use Aicl\Filament\Pages\Errors\ServiceUnavailable;
use Aicl\Filament\Pages\LogViewer;
use Aicl\Filament\Pages\ManageSettings;
use Aicl\Filament\Pages\NotificationCenter;
use Aicl\Filament\Pages\NotificationLogPage;
use Aicl\Filament\Pages\QueueDashboard;
use Aicl\Filament\Pages\Search;
use Aicl\Filament\Pages\Styleguide\ActionComponents;
use Aicl\Filament\Pages\Styleguide\DataDisplayComponents;
use Aicl\Filament\Pages\Styleguide\LayoutComponents;
use Aicl\Filament\Pages\Styleguide\MetricComponents;
use Aicl\Filament\Pages\Styleguide\StyleguideOverview;
use Aicl\Filament\Resources\FailedJobs\FailedJobResource;
use Aicl\Filament\Resources\Users\UserResource;
use Aicl\Filament\Widgets\GlobalSearchWidget;
use Aicl\Filament\Widgets\QueueStatsWidget;
use Aicl\Filament\Widgets\RecentFailedJobsWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

class AiclPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'aicl';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources($this->getResources())
            ->pages($this->getPages())
            ->widgets($this->getWidgets());
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * @return array<class-string>
     */
    protected function getResources(): array
    {
        return [
            FailedJobResource::class,
            UserResource::class,
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getPages(): array
    {
        return [
            QueueDashboard::class,
            LogViewer::class,
            ManageSettings::class,
            AuditLog::class,
            NotificationCenter::class,
            NotificationLogPage::class,
            Search::class,
            ApiTokens::class,
            StyleguideOverview::class,
            LayoutComponents::class,
            MetricComponents::class,
            DataDisplayComponents::class,
            ActionComponents::class,
            NotFound::class,
            Forbidden::class,
            ServerError::class,
            ServiceUnavailable::class,
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getWidgets(): array
    {
        return [
            QueueStatsWidget::class,
            RecentFailedJobsWidget::class,
            GlobalSearchWidget::class,
        ];
    }
}
