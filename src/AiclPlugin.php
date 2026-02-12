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
use Aicl\Filament\Pages\RlmDashboard;
use Aicl\Filament\Pages\Search;
use Aicl\Filament\Pages\Styleguide\ActionComponents;
use Aicl\Filament\Pages\Styleguide\DataDisplayComponents;
use Aicl\Filament\Pages\Styleguide\LayoutComponents;
use Aicl\Filament\Pages\Styleguide\MetricComponents;
use Aicl\Filament\Pages\Styleguide\StyleguideOverview;
use Aicl\Filament\Resources\FailedJobs\FailedJobResource;
use Aicl\Filament\Resources\FailureReports\FailureReportResource;
use Aicl\Filament\Resources\GenerationTraces\GenerationTraceResource;
use Aicl\Filament\Resources\PreventionRules\PreventionRuleResource;
use Aicl\Filament\Resources\RlmFailures\RlmFailureResource;
use Aicl\Filament\Resources\RlmLessons\RlmLessonResource;
use Aicl\Filament\Resources\RlmPatterns\RlmPatternResource;
use Aicl\Filament\Resources\Users\UserResource;
use Aicl\Filament\Widgets\CategoryBreakdownChart;
use Aicl\Filament\Widgets\FailureReportDeadlinesWidget;
use Aicl\Filament\Widgets\FailureReportStatsOverview;
use Aicl\Filament\Widgets\FailureTrendChart;
use Aicl\Filament\Widgets\GenerationTraceStatsOverview;
use Aicl\Filament\Widgets\GlobalSearchWidget;
use Aicl\Filament\Widgets\PreventionRuleDeadlinesWidget;
use Aicl\Filament\Widgets\PreventionRuleStatsOverview;
use Aicl\Filament\Widgets\ProjectHealthWidget;
use Aicl\Filament\Widgets\PromotionQueueWidget;
use Aicl\Filament\Widgets\QueueStatsWidget;
use Aicl\Filament\Widgets\RecentFailedJobsWidget;
use Aicl\Filament\Widgets\RecentGenerationTracesWidget;
use Aicl\Filament\Widgets\RecentRlmLessonsWidget;
use Aicl\Filament\Widgets\RlmFailureByStatusChart;
use Aicl\Filament\Widgets\RlmFailureDeadlinesWidget;
use Aicl\Filament\Widgets\RlmFailureStatsOverview;
use Aicl\Filament\Widgets\RlmLessonStatsOverview;
use Aicl\Filament\Widgets\RlmPatternDeadlinesWidget;
use Aicl\Filament\Widgets\RlmPatternStatsOverview;
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
            FailureReportResource::class,
            GenerationTraceResource::class,
            PreventionRuleResource::class,
            RlmFailureResource::class,
            RlmLessonResource::class,
            RlmPatternResource::class,
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
            RlmDashboard::class,
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
            CategoryBreakdownChart::class,
            FailureReportDeadlinesWidget::class,
            FailureReportStatsOverview::class,
            FailureTrendChart::class,
            GenerationTraceStatsOverview::class,
            GlobalSearchWidget::class,
            PreventionRuleDeadlinesWidget::class,
            PreventionRuleStatsOverview::class,
            ProjectHealthWidget::class,
            PromotionQueueWidget::class,
            QueueStatsWidget::class,
            RecentFailedJobsWidget::class,
            RecentGenerationTracesWidget::class,
            RecentRlmLessonsWidget::class,
            RlmFailureByStatusChart::class,
            RlmFailureDeadlinesWidget::class,
            RlmFailureStatsOverview::class,
            RlmLessonStatsOverview::class,
            RlmPatternDeadlinesWidget::class,
            RlmPatternStatsOverview::class,
        ];
    }
}
