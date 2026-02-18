<?php

namespace Aicl\Filament\Pages\Styleguide;

use Aicl\Components\ComponentRegistry;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class StyleguideOverview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'Styleguide';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Component Styleguide';

    protected string $view = 'aicl::filament.pages.styleguide.overview';

    protected function getViewData(): array
    {
        $registry = app(ComponentRegistry::class);
        $all = $registry->all();

        $categoryPageMap = [
            'layout' => ['label' => 'Layout', 'icon' => 'heroicon-o-rectangle-group', 'color' => 'blue', 'slug' => 'layout-components'],
            'metric' => ['label' => 'Metrics', 'icon' => 'heroicon-o-chart-bar', 'color' => 'green', 'slug' => 'metric-components'],
            'data' => ['label' => 'Data Display', 'icon' => 'heroicon-o-table-cells', 'color' => 'purple', 'slug' => 'data-display-components'],
            'status' => ['label' => 'Data Display', 'icon' => 'heroicon-o-table-cells', 'color' => 'purple', 'slug' => 'data-display-components'],
            'timeline' => ['label' => 'Data Display', 'icon' => 'heroicon-o-table-cells', 'color' => 'purple', 'slug' => 'data-display-components'],
            'action' => ['label' => 'Actions & Utility', 'icon' => 'heroicon-o-cursor-arrow-rays', 'color' => 'amber', 'slug' => 'action-components'],
            'utility' => ['label' => 'Actions & Utility', 'icon' => 'heroicon-o-cursor-arrow-rays', 'color' => 'amber', 'slug' => 'action-components'],
            'feedback' => ['label' => 'Feedback', 'icon' => 'heroicon-o-chat-bubble-left-right', 'color' => 'rose', 'slug' => 'feedback-components'],
            'collection' => ['label' => 'Interactive', 'icon' => 'heroicon-o-cursor-arrow-ripple', 'color' => 'cyan', 'slug' => 'interactive-components'],
        ];

        $grouped = [];
        foreach ($all as $component) {
            $mapping = $categoryPageMap[$component->category] ?? [
                'label' => ucfirst($component->category),
                'icon' => 'heroicon-o-squares-2x2',
                'color' => 'gray',
                'slug' => '',
            ];
            $label = $mapping['label'];
            if (! isset($grouped[$label])) {
                $grouped[$label] = [
                    'label' => $label,
                    'icon' => $mapping['icon'],
                    'color' => $mapping['color'],
                    'slug' => $mapping['slug'],
                    'components' => [],
                ];
            }
            $grouped[$label]['components'][] = $component;
        }

        ksort($grouped);

        $jsModuleCount = $all->filter(fn ($c): bool => $c->jsModule !== null)->count();

        return [
            'totalComponents' => $all->count(),
            'totalCategories' => count($grouped),
            'jsModuleCount' => $jsModuleCount,
            'categories' => $grouped,
        ];
    }
}
