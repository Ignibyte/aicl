# AICL Component Library Reference

**Version:** 1.1
**Last Updated:** 2026-02-15
**Component Count:** 21 (20 Blade + 1 Livewire)

---

## Overview

The AICL component library provides pre-built, composable UI components for dashboard applications. All components use the `<x-aicl-*>` prefix and follow Filament's design language (Tailwind CSS tokens, consistent spacing).

These components are the building blocks that AI uses to compose rich dashboard UIs. Each component has documented "when to use" rules that guide AI decision-making.

---

## Component Hierarchy

### Level 2 — Layout Components
Structure the page layout and organize content.

| Component | Purpose |
|-----------|---------|
| `SplitLayout` | Main + sidebar with configurable ratio |
| `AuthSplitLayout` | Auth page layout (login, register) with branding sidebar |
| `CardGrid` | Responsive grid of cards (1-4 columns) |
| `StatsRow` | Horizontal row of stat cards |
| `EmptyState` | Empty state with illustration + CTA |

### Level 3 — Display Components
Show data and provide interactivity.

| Category | Components |
|----------|------------|
| **Metrics** | StatCard, KpiCard, TrendCard, ProgressCard |
| **Data** | MetadataList, InfoCard, StatusBadge, Timeline |
| **Actions** | ActionBar, QuickAction, AlertBanner, Divider |
| **Navigation** | Tabs, TabPanel |
| **Utility** | Spinner |
| **Live** | ActivityFeed (Livewire) |

---

## Layout Components

### SplitLayout

Two-column layout with main content and sidebar.

**Location:** `packages/aicl/src/View/Components/SplitLayout.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `ratio` | string | `'2/3'` | Width ratio (main/sidebar): `'1/2'`, `'2/3'`, `'3/4'` |
| `reverse` | bool | `false` | Put sidebar on left |

**Slots:**
- Default slot: Main content
- `sidebar`: Sidebar content

**Usage:**
```blade
<x-aicl-split-layout ratio="2/3">
    <x-aicl-info-card title="Project Details" :items="$details" />

    <x-slot:sidebar>
        <x-aicl-metadata-list :items="$metadata" />
    </x-slot:sidebar>
</x-aicl-split-layout>
```

**AI Decision Rule:**
Use when displaying a detail page with primary content and supplementary metadata/actions.

---

### CardGrid

Responsive grid for arranging cards.

**Location:** `packages/aicl/src/View/Components/CardGrid.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `cols` | int | `3` | Number of columns (1-4) |
| `gap` | string | `'md'` | Gap size: `'sm'`, `'md'`, `'lg'` |

**Usage:**
```blade
<x-aicl-card-grid cols="2" gap="lg">
    <x-aicl-info-card title="Overview" :items="$overview" />
    <x-aicl-info-card title="Statistics" :items="$stats" />
</x-aicl-card-grid>
```

**AI Decision Rule:**
Use for dashboard layouts with multiple cards of similar importance. Choose `cols` based on content width needs.

---

### StatsRow

Horizontal row optimized for stat cards.

**Location:** `packages/aicl/src/View/Components/StatsRow.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `justify` | string | `'between'` | Flex justify: `'start'`, `'center'`, `'between'`, `'around'` |

**Usage:**
```blade
<x-aicl-stats-row>
    <x-aicl-stat-card label="Total" :value="$total" icon="chart-bar" />
    <x-aicl-stat-card label="Active" :value="$active" color="green" />
    <x-aicl-stat-card label="Pending" :value="$pending" color="yellow" />
    <x-aicl-stat-card label="Completed" :value="$completed" color="blue" />
</x-aicl-stats-row>
```

**AI Decision Rule:**
Use at the top of dashboards and overview pages. Place 3-5 stat cards showing key metrics.

---

### EmptyState

Placeholder for empty lists/tables.

**Location:** `packages/aicl/src/View/Components/EmptyState.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | string | required | Heading text |
| `description` | string | `null` | Subtext |
| `icon` | string | `'inbox'` | Heroicon name |
| `actionLabel` | string | `null` | CTA button text |
| `actionUrl` | string | `null` | CTA button URL |

**Usage:**
```blade
<x-aicl-empty-state
    title="No projects yet"
    description="Create your first project to get started."
    icon="folder-plus"
    actionLabel="Create Project"
    actionUrl="{{ route('filament.admin.resources.projects.create') }}"
/>
```

**AI Decision Rule:**
Use inside conditional blocks when a list/table might be empty. Always provide an action to create the first item.

---

## Metric Components

### StatCard

Simple metric display with label, value, and optional icon/trend.

**Location:** `packages/aicl/src/View/Components/StatCard.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `label` | string | required | Metric name |
| `value` | string\|int | required | Metric value |
| `icon` | string | `null` | Heroicon name |
| `color` | string | `'gray'` | Color: `'gray'`, `'blue'`, `'green'`, `'yellow'`, `'red'` |
| `trend` | string | `null` | Trend text (e.g., "+12% from last month") |
| `trendUp` | bool | `null` | Trend direction for styling |

**Usage:**
```blade
<x-aicl-stat-card
    label="Total Revenue"
    value="$45,231"
    icon="currency-dollar"
    color="green"
    trend="+12% from last month"
    :trendUp="true"
/>
```

**AI Decision Rule:**
- Use when entity has a countable relationship → generate count stat
- Use when entity has monetary field → generate total/average stat
- Use when entity has status enum → generate per-status count

---

### KpiCard

Metric with target/actual comparison and progress ring.

**Location:** `packages/aicl/src/View/Components/KpiCard.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `label` | string | required | KPI name |
| `actual` | int\|float | required | Current value |
| `target` | int\|float | required | Target value |
| `format` | string | `'number'` | Format: `'number'`, `'currency'`, `'percent'` |
| `color` | string | `'blue'` | Progress ring color |

**Usage:**
```blade
<x-aicl-kpi-card
    label="Sales Target"
    :actual="75000"
    :target="100000"
    format="currency"
    color="green"
/>
```

**AI Decision Rule:**
Use when entity has both an actual value and a target/goal field. Common for budgets, quotas, goals.

---

### TrendCard

Metric with sparkline mini-chart.

**Location:** `packages/aicl/src/View/Components/TrendCard.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `label` | string | required | Metric name |
| `value` | string\|int | required | Current value |
| `data` | array | required | Array of values for sparkline |
| `color` | string | `'blue'` | Sparkline color |
| `trend` | string | `null` | Trend text |
| `trendUp` | bool | `null` | Trend direction |

**Usage:**
```blade
<x-aicl-trend-card
    label="Weekly Signups"
    value="234"
    :data="[45, 52, 38, 65, 72, 80, 234]"
    color="green"
    trend="+192% this week"
    :trendUp="true"
/>
```

**AI Decision Rule:**
Use when entity has `created_at` and historical data is meaningful. Good for showing trends over time.

---

### ProgressCard

Metric with progress bar.

**Location:** `packages/aicl/src/View/Components/ProgressCard.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `label` | string | required | Metric name |
| `value` | int | required | Current value (0-100 or raw) |
| `max` | int | `100` | Maximum value |
| `color` | string | `'blue'` | Progress bar color |
| `showPercent` | bool | `true` | Show percentage |

**Usage:**
```blade
<x-aicl-progress-card
    label="Profile Completion"
    :value="75"
    color="green"
/>

<x-aicl-progress-card
    label="Tasks Done"
    :value="8"
    :max="12"
    color="blue"
/>
```

**AI Decision Rule:**
Use for completion percentages, progress tracking, or any value with a clear maximum.

---

## Data Components

### MetadataList

Key-value list for entity attributes.

**Location:** `packages/aicl/src/View/Components/MetadataList.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `items` | array | required | Array of `['label' => '...', 'value' => '...']` |
| `columns` | int | `1` | Number of columns (1-2) |

**Usage:**
```blade
<x-aicl-metadata-list :items="[
    ['label' => 'Created', 'value' => $project->created_at->format('M d, Y')],
    ['label' => 'Owner', 'value' => $project->owner->name],
    ['label' => 'Priority', 'value' => $project->priority->getLabel()],
]" />
```

**AI Decision Rule:**
Use in sidebars for displaying entity metadata. Good for created/updated dates, owner, status, category.

---

### InfoCard

Card with heading and key-value content.

**Location:** `packages/aicl/src/View/Components/InfoCard.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | string | required | Card heading |
| `items` | array | required | Array of `['label' => '...', 'value' => '...']` |
| `icon` | string | `null` | Heroicon for header |

**Usage:**
```blade
<x-aicl-info-card
    title="Project Details"
    icon="information-circle"
    :items="[
        ['label' => 'Name', 'value' => $project->name],
        ['label' => 'Description', 'value' => $project->description],
        ['label' => 'Budget', 'value' => '$' . number_format($project->budget)],
    ]"
/>
```

**AI Decision Rule:**
Use for grouped information that needs a heading. Better than MetadataList when content needs visual separation.

---

### StatusBadge

Colored badge for status/enum values.

**Location:** `packages/aicl/src/View/Components/StatusBadge.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `status` | string | required | Status text |
| `color` | string | `'gray'` | Badge color: `'gray'`, `'blue'`, `'green'`, `'yellow'`, `'red'`, `'purple'` |
| `size` | string | `'md'` | Size: `'sm'`, `'md'`, `'lg'` |

**Usage:**
```blade
<x-aicl-status-badge status="Active" color="green" />
<x-aicl-status-badge status="Pending Review" color="yellow" />
<x-aicl-status-badge status="Archived" color="gray" />
```

**AI Decision Rule:**
Use for any status/state field. Map enum values to appropriate colors (green=active/success, yellow=warning/pending, red=error/blocked).

---

### Timeline

Vertical timeline for activity/history.

**Location:** `packages/aicl/src/View/Components/Timeline.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `items` | array | required | Array of timeline entries |

Each item: `['title' => '...', 'description' => '...', 'time' => '...', 'icon' => '...', 'color' => '...']`

**Usage:**
```blade
<x-aicl-timeline :items="[
    [
        'title' => 'Project created',
        'description' => 'Created by Admin',
        'time' => '2 hours ago',
        'icon' => 'plus-circle',
        'color' => 'green',
    ],
    [
        'title' => 'Status changed to Active',
        'description' => 'Approved by Manager',
        'time' => '1 hour ago',
        'icon' => 'check-circle',
        'color' => 'blue',
    ],
]" />
```

**AI Decision Rule:**
Use for audit trails, activity history, or any chronological sequence of events. Pull data from activity log.

---

## Action Components

### ActionBar

Horizontal button group for page actions.

**Location:** `packages/aicl/src/View/Components/ActionBar.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `align` | string | `'right'` | Alignment: `'left'`, `'center'`, `'right'` |

**Slots:**
- Default slot: Button content

**Usage:**
```blade
<x-aicl-action-bar>
    <x-filament::button color="gray" tag="a" :href="$backUrl">
        Cancel
    </x-filament::button>
    <x-filament::button wire:click="save">
        Save Changes
    </x-filament::button>
</x-aicl-action-bar>
```

**AI Decision Rule:**
Use at the top or bottom of forms/pages for primary actions. Keep to 2-4 buttons maximum.

---

### QuickAction

Icon button with tooltip.

**Location:** `packages/aicl/src/View/Components/QuickAction.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `icon` | string | required | Heroicon name |
| `label` | string | required | Tooltip text |
| `url` | string | `null` | Link URL (makes it an anchor) |
| `color` | string | `'gray'` | Button color |

**Usage:**
```blade
<x-aicl-quick-action icon="pencil" label="Edit" :url="$editUrl" />
<x-aicl-quick-action icon="trash" label="Delete" color="red" wire:click="delete" />
<x-aicl-quick-action icon="download" label="Export PDF" wire:click="exportPdf" />
```

**AI Decision Rule:**
Use for secondary actions that don't need visible text labels. Group in a row for related actions.

---

### AlertBanner

Dismissible alert/notice.

**Location:** `packages/aicl/src/View/Components/AlertBanner.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `type` | string | `'info'` | Alert type: `'info'`, `'success'`, `'warning'`, `'error'` |
| `title` | string | `null` | Alert heading |
| `dismissible` | bool | `true` | Show dismiss button |

**Slots:**
- Default slot: Alert message

**Usage:**
```blade
<x-aicl-alert-banner type="warning" title="Action Required">
    Please complete your profile to access all features.
</x-aicl-alert-banner>

<x-aicl-alert-banner type="success" :dismissible="false">
    Your changes have been saved successfully.
</x-aicl-alert-banner>
```

**AI Decision Rule:**
Use for important messages, validation summaries, or system notifications. Place at the top of the content area.

---

### Divider

Horizontal rule with optional label.

**Location:** `packages/aicl/src/View/Components/Divider.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `label` | string | `null` | Center label text |

**Usage:**
```blade
<x-aicl-divider />

<x-aicl-divider label="Or" />

<x-aicl-divider label="Additional Options" />
```

**AI Decision Rule:**
Use sparingly to separate distinct sections within a page. Prefer Section components for major divisions.

---

## Livewire Components

### ActivityFeed

Real-time activity stream with polling.

**Location:** `packages/aicl/src/Livewire/ActivityFeed.php`

**Props:**
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `subject` | Model | `null` | Filter to specific model |
| `limit` | int | `10` | Number of entries to show |
| `pollInterval` | string | `'30s'` | Polling interval |

**Usage:**
```blade
{{-- All recent activity --}}
<livewire:aicl-activity-feed :limit="15" />

{{-- Activity for specific model --}}
<livewire:aicl-activity-feed :subject="$project" :limit="10" />
```

**AI Decision Rule:**
Use on detail pages to show entity-specific activity, or on dashboards for system-wide activity. Requires spatie/laravel-activitylog.

---

## Page Composition Patterns

### Dashboard Page
```blade
{{-- Stats at top --}}
<x-aicl-stats-row>
    <x-aicl-stat-card ... />
    <x-aicl-stat-card ... />
    <x-aicl-stat-card ... />
</x-aicl-stats-row>

{{-- Charts/widgets in grid --}}
<x-aicl-card-grid cols="2">
    {{-- Filament chart widgets --}}
    @livewire(\App\Filament\Widgets\ProjectsByStatusChart::class)
    @livewire(\App\Filament\Widgets\RevenueChart::class)
</x-aicl-card-grid>

{{-- Activity feed --}}
<livewire:aicl-activity-feed :limit="10" />
```

### Detail/View Page
```blade
<x-aicl-action-bar>
    <x-filament::button tag="a" :href="$editUrl">Edit</x-filament::button>
</x-aicl-action-bar>

<x-aicl-split-layout ratio="2/3">
    {{-- Main content --}}
    <x-aicl-info-card title="Details" :items="$details" />

    <x-slot:sidebar>
        <x-aicl-status-badge :status="$project->status->label()" :color="$statusColor" />
        <x-aicl-metadata-list :items="$metadata" />
        <x-aicl-divider label="Activity" />
        <x-aicl-timeline :items="$activities" />
    </x-slot:sidebar>
</x-aicl-split-layout>
```

### Empty State Pattern
```blade
@if($projects->isEmpty())
    <x-aicl-empty-state
        title="No projects found"
        description="Get started by creating your first project."
        icon="folder-plus"
        actionLabel="Create Project"
        :actionUrl="route('filament.admin.resources.projects.create')"
    />
@else
    {{-- Project list/table --}}
@endif
```

---

## Tailwind v4 Integration

All components use Tailwind CSS classes. The custom theme at `resources/css/filament/admin/theme.css` includes:

```css
@source "../../../packages/aicl/resources/views/**/*.blade.php";
@source "../../../packages/aicl/src/**/*.php";
```

**Important:** Dynamic class interpolation doesn't work in Tailwind v4. Components use explicit `match` expressions for color classes:

```php
// In component class
public function iconBgClass(): string
{
    return match ($this->color) {
        'blue' => 'bg-blue-100 dark:bg-blue-900',
        'green' => 'bg-green-100 dark:bg-green-900',
        // ...
    };
}
```

---

## Related Documents

- [Foundation](foundation.md)
- [AI Generation Pipeline](ai-generation-pipeline.md)
- [Filament UI](filament-ui.md)
