# AICL Component Decision Tree

> Auto-generated from `component.json` manifests via `artisan aicl:components tree`
> Generated: 2026-03-31 01:00:49
> Components: 35

## metric (4)

### `x-aicl-kpi-card`

- **Decision Rule:** Use when entity has target/goal field alongside actual/current value. Use for budget tracking, completion rates, quota progress.
- **Context:** blade, livewire, filament-widget, email, pdf
- **Not For:** filament-form, filament-table, filament-infolist
- **Field Signals:** target_actual_pair
- **Composable In:** stats-row, card-grid, split-layout
- **Filament Equivalent:** `Filament\Widgets\StatsOverviewWidget` (In Filament admin, use StatsOverviewWidget with progress Stat::make())

### `x-aicl-progress-card`

- **Decision Rule:** Use for completion tracking and capacity/usage metrics. Simpler than KpiCard when you only have a percentage, not target/actual.
- **Context:** blade, livewire, filament-widget, email, pdf
- **Not For:** filament-form, filament-table, filament-infolist
- **Field Signals:** progress_percentage, integer
- **Composable In:** stats-row, card-grid, split-layout
- **Filament Equivalent:** `Filament\Widgets\StatsOverviewWidget` (In Filament admin, use StatsOverviewWidget with Stat progress)

### `x-aicl-stat-card`

- **Decision Rule:** Use when displaying a single count, total, or summary metric. Use for countable relationships, monetary fields, or status counts.
- **Context:** blade, livewire, filament-widget, email, pdf
- **Not For:** filament-form, filament-table, filament-infolist
- **Field Signals:** integer, float, count_aggregate
- **Composable In:** stats-row, card-grid, split-layout
- **Filament Equivalent:** `Filament\Widgets\StatsOverviewWidget` (In Filament admin resources, use StatsOverviewWidget with Stat::make() instead)

### `x-aicl-trend-card`

- **Decision Rule:** Use when entity has created_at and user wants trends over time. Provide 7-30 data points for sparkline.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, filament-infolist, email, pdf
- **Field Signals:** datetime, created_at_trend
- **Composable In:** stats-row, card-grid, split-layout
- **Filament Equivalent:** `Filament\Widgets\ChartWidget` (In Filament admin, use ChartWidget for trend charts)

## data (3)

### `x-aicl-avatar`

- **Decision Rule:** Use for user/profile display in cards, lists, and headers. Provide name prop for initials fallback. Use status for presence indicators.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email
- **Composable In:** card-grid, split-layout, info-card, metadata-list
- **Filament Equivalent:** `Filament\Panel` (Filament has built-in avatar support in user menus)

### `x-aicl-info-card`

- **Decision Rule:** Use for grouped metadata sections within detail pages. Combine with CardGrid for multiple info sections side by side.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table
- **Composable In:** card-grid, split-layout
- **Filament Equivalent:** `Filament\Schemas\Components\Section` (In Filament infolists, use Section with entries inside)

### `x-aicl-metadata-list`

- **Decision Rule:** Use in detail/view pages to display entity attributes as key-value pairs. Ideal for sidebar metadata in SplitLayout.
- **Context:** blade, livewire, filament-widget, email, pdf
- **Not For:** filament-form, filament-table
- **Field Signals:** object, key_value_pairs
- **Composable In:** split-layout, card-grid, info-card
- **Filament Equivalent:** `Filament\Infolists\Infolist` (In Filament infoolists, use TextEntry, IconEntry, etc.)

## collection (1)

### `x-aicl-data-table`

- **Decision Rule:** Use for static/pre-loaded data display outside Filament. In Filament admin, use the declarative Table builder instead. Keep column count to 4-8 for readability.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Composable In:** split-layout
- **Filament Equivalent:** `Filament\Tables\Table` (In Filament, use the declarative table builder Table::make())

## action (5)

### `x-aicl-action-bar`

- **Decision Rule:** Use at top of detail pages for entity actions (Edit, Delete, Export). Use in card headers for section-specific actions.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Composable In:** split-layout, card-grid
- **Filament Equivalent:** `Filament\Tables\Actions\ActionGroup` (In Filament resources, use table actions and header actions)

### `x-aicl-combobox`

- **Decision Rule:** Use for searchable select inputs outside Filament. For Filament forms, use Select::make()->searchable() instead. Use multiple for tag-like multi-selection.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Filament Equivalent:** `Filament\Forms\Components\Select` (In Filament forms, use Select::make()->searchable())

### `x-aicl-command-palette`

- **Decision Rule:** Use as global search overlay triggered by Cmd+K. Place once per page layout. In Filament admin, use built-in global search instead.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Filament Equivalent:** `Filament\Panel` (Filament has built-in global search — use that in admin context)

### `x-aicl-dropdown`

- **Decision Rule:** Use for context menus, action lists, and option selectors. For searchable selection, use combobox. In Filament, use Action::make()->dropdown().
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Filament Equivalent:** `Filament\Actions\Action` (In Filament, use native dropdown menus or Action::make()->dropdown())

### `x-aicl-quick-action`

- **Decision Rule:** Use inside ActionBar for compact icon-only actions. Always include tooltip label for accessibility.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Composable In:** action-bar
- **Filament Equivalent:** `Filament\Tables\Actions\Action` (In Filament, use Action::make() with icon())

## status (2)

### `x-aicl-badge`

- **Decision Rule:** Use for tags, labels, counts, and inline indicators. For entity status fields, prefer status-badge instead. Use dot for connection status, removable for filter chips.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email
- **Composable In:** card-grid, split-layout, info-card, metadata-list
- **Filament Equivalent:** `Filament\Tables\Columns\TextColumn` (In Filament, use TextColumn::make()->badge() or BadgeEntry)

### `x-aicl-status-badge`

- **Decision Rule:** Use for any status, state, or category field in display contexts. Color should match the enum/state's defined color.
- **Context:** blade, livewire, filament-widget, email, pdf
- **Not For:** filament-form
- **Field Signals:** enum, state, status
- **Composable In:** stats-row, card-grid, split-layout, info-card, metadata-list
- **Filament Equivalent:** `Filament\Tables\Columns\TextColumn` (In Filament tables, use TextColumn::make()->badge())

## timeline (1)

### `x-aicl-timeline`

- **Decision Rule:** Use for audit logs, activity history, and change tracking. Each entry should have timestamp and description.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Field Signals:** audit_trail, activity_log
- **Composable In:** split-layout, card-grid
- **Filament Equivalent:** `Filament\Schemas\Components\Section` (In Filament infolists, build a custom section for timeline data)

## layout (10)

### `x-aicl-accordion`

- **Decision Rule:** Use for FAQ sections, grouped settings, and progressive disclosure. Prefer tabs for 2-5 equally-important sections. In Filament forms, use Section with collapsible() instead.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Composable In:** split-layout, card-grid
- **Filament Equivalent:** `Filament\Schemas\Components\Section` (In Filament, use Section::make()->collapsible() for collapsible sections)

### `x-aicl-accordion-item`

- **Decision Rule:** Each item must have a unique name within its parent Accordion. Keep labels short (1-5 words).
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Composable In:** accordion
- **Filament Equivalent:** `Filament\Schemas\Components\Section` (In Filament, each Section with collapsible() acts as an accordion item)

### `x-aicl-auth-split-layout`

- **Decision Rule:** Use for login, registration, and password reset pages. Right side hidden on mobile (< lg breakpoint).
- **Context:** blade
- **Not For:** filament-form, filament-table, filament-infolist, email, pdf
- **Filament Equivalent:** `Filament\Pages\Auth\Login` (Filament has built-in auth pages — use this for custom auth layouts)

### `x-aicl-card-grid`

- **Decision Rule:** Use for displaying multiple equal-weight items in a grid. Use cols=2 for paired items, cols=3 for dashboards, cols=4 for compact grids.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table
- **Composable In:** split-layout
- **Filament Equivalent:** `Filament\Schemas\Components\Grid` (In Filament forms, use Grid with columns() for multi-column layouts)

### `x-aicl-drawer`

- **Decision Rule:** Use for detail panels, filters, and forms that don't need full-page context. Position right for detail/edit, left for navigation. In Filament, use Action::make()->slideOver() instead.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Filament Equivalent:** `Filament\Actions\Action` (In Filament, use Action::make()->slideOver() for slide-over panels)

### `x-aicl-empty-state`

- **Decision Rule:** Use when a list, table, or section has no data. Always include heading and description. Include action button when user can create the missing item.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form
- **Composable In:** split-layout, card-grid
- **Filament Equivalent:** `Filament\Tables\Table` (In Filament tables, use $table->emptyStateActions() and emptyStateHeading())

### `x-aicl-split-layout`

- **Decision Rule:** Use for main content area + contextual sidebar. Default 2/3 + 1/3 for detail views, 3/4 + 1/4 for content-heavy pages.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table
- **Filament Equivalent:** `Filament\Schemas\Components\Grid` (In Filament forms, use Grid with columns() for multi-column layouts)

### `x-aicl-stats-row`

- **Decision Rule:** Use at the top of dashboards or detail pages for key metrics. Always use 3-4 stats per row.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table
- **Composable In:** split-layout, card-grid
- **Filament Equivalent:** `Filament\Widgets\StatsOverviewWidget` (In Filament admin, use StatsOverviewWidget with Stat::make() instead)

### `x-aicl-tab-panel`

- **Decision Rule:** Each TabPanel must have unique name within parent Tabs. Label displayed in tab button (keep short, 1-3 words). Use badge for counts.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Composable In:** tabs
- **Filament Equivalent:** `Filament\Schemas\Components\Tabs\Tab` (In Filament, use Tabs\Tab::make())

### `x-aicl-tabs`

- **Decision Rule:** Use to organize related content into switchable panels. Prefer tabs over accordions for 2-5 sections of equal importance. Use underline for page-level, pills for card-level, boxed for toggles, vertical for settings.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Composable In:** split-layout, card-grid
- **Filament Equivalent:** `Filament\Schemas\Components\Tabs` (In Filament forms/infolists, use Filament's Tabs component)

## feedback (3)

### `x-aicl-alert-banner`

- **Decision Rule:** Use for system-wide messages or contextual warnings within a section. Type determines color: info (blue), success (green), warning (yellow), danger (red).
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Composable In:** split-layout, card-grid
- **Filament Equivalent:** `Filament\Schemas\Components\Placeholder` (In Filament forms, use Placeholder with custom HTML content)

### `x-aicl-modal`

- **Decision Rule:** Use for confirmations, detail views, and overlaid content. Size md for confirmations, lg for forms, xl for detail views. In Filament admin, use Action::make()->modal() instead.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Filament Equivalent:** `Filament\Actions\Action` (In Filament, use Action::make()->modal() for modal dialogs)

### `x-aicl-toast`

- **Decision Rule:** Use for transient notifications. Place once per page layout. Trigger via Alpine.store('toasts').add(). In Filament, use Notification::make() instead.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Filament Equivalent:** `Filament\Notifications\Notification` (In Filament, use Notification::make() for toast-style notifications)

## utility (6)

### `x-aicl-code-block`

- **Decision Rule:** Use for displaying code snippets with copy functionality. Pass code as a raw string via the :code prop.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Composable In:** split-layout, card-grid

### `x-aicl-component-reference`

- **Decision Rule:** Use in styleguide pages below each component demo to show props, decision rules, and context tags.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf

### `x-aicl-divider`

- **Decision Rule:** Use to separate content sections within page or card. Use with label for named section breaks.
- **Context:** blade, livewire, filament-widget, email, pdf
- **Not For:** filament-form, filament-table
- **Composable In:** split-layout, card-grid
- **Filament Equivalent:** `Filament\Schemas\Components\Section` (In Filament forms, use Section headings to separate content)

### `x-aicl-ignibyte-logo`

- **Decision Rule:** Use for brand identity in navigation, auth pages, and footers. Icon-only mode for collapsed sidebar.
- **Context:** blade, livewire, filament-widget, email, pdf
- **Not For:** filament-form, filament-table
- **Filament Equivalent:** `Filament\Panel` (Filament panels have built-in brand logo support via brandLogo())

### `x-aicl-spinner`

- **Decision Rule:** Use inside buttons with Alpine x-show during form submission. Use for placeholder content while data loads. Size sm for inline, md for sections, lg for full-page.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Filament Equivalent:** `Filament\Support\Components\Component` (Filament has built-in loading indicators)

### `x-aicl-tooltip`

- **Decision Rule:** Use for supplementary info on hover/focus (icons, abbreviations, truncated text). Keep content short. In Filament, use ->tooltip() method instead.
- **Context:** blade, livewire, filament-widget
- **Not For:** filament-form, filament-table, email, pdf
- **Filament Equivalent:** `Filament\Tables\Columns\Column` (In Filament, use Column::make()->tooltip() for tooltips)

## Composition Hierarchy

Components define which parents they can be nested in via `composable_in`:

- **`accordion`** accepts: `x-aicl-accordion-item`
- **`action-bar`** accepts: `x-aicl-quick-action`
- **`card-grid`** accepts: `x-aicl-accordion`, `x-aicl-action-bar`, `x-aicl-alert-banner`, `x-aicl-avatar`, `x-aicl-badge`, `x-aicl-code-block`, `x-aicl-divider`, `x-aicl-empty-state`, `x-aicl-info-card`, `x-aicl-kpi-card`, `x-aicl-metadata-list`, `x-aicl-progress-card`, `x-aicl-stat-card`, `x-aicl-stats-row`, `x-aicl-status-badge`, `x-aicl-tabs`, `x-aicl-timeline`, `x-aicl-trend-card`
- **`info-card`** accepts: `x-aicl-avatar`, `x-aicl-badge`, `x-aicl-metadata-list`, `x-aicl-status-badge`
- **`metadata-list`** accepts: `x-aicl-avatar`, `x-aicl-badge`, `x-aicl-status-badge`
- **`split-layout`** accepts: `x-aicl-accordion`, `x-aicl-action-bar`, `x-aicl-alert-banner`, `x-aicl-avatar`, `x-aicl-badge`, `x-aicl-card-grid`, `x-aicl-code-block`, `x-aicl-data-table`, `x-aicl-divider`, `x-aicl-empty-state`, `x-aicl-info-card`, `x-aicl-kpi-card`, `x-aicl-metadata-list`, `x-aicl-progress-card`, `x-aicl-stat-card`, `x-aicl-stats-row`, `x-aicl-status-badge`, `x-aicl-tabs`, `x-aicl-timeline`, `x-aicl-trend-card`
- **`stats-row`** accepts: `x-aicl-kpi-card`, `x-aicl-progress-card`, `x-aicl-stat-card`, `x-aicl-status-badge`, `x-aicl-trend-card`
- **`tabs`** accepts: `x-aicl-tab-panel`

## Context Crosswalk

| Component | blade | livewire | filament-widget | filament-form | filament-table | email | pdf |
|-----------|:-----:|:--------:|:---------------:|:-------------:|:--------------:|:-----:|:---:|
`x-aicl-accordion` | Y | Y | Y | - | - | - | - |
`x-aicl-accordion-item` | Y | Y | Y | - | - | - | - |
`x-aicl-action-bar` | Y | Y | Y | - | - | - | - |
`x-aicl-alert-banner` | Y | Y | Y | - | - | - | - |
`x-aicl-auth-split-layout` | Y | - | - | - | - | - | - |
`x-aicl-avatar` | Y | Y | Y | - | - | - | - |
`x-aicl-badge` | Y | Y | Y | - | - | - | - |
`x-aicl-card-grid` | Y | Y | Y | - | - | - | - |
`x-aicl-code-block` | Y | Y | Y | - | - | - | - |
`x-aicl-combobox` | Y | Y | Y | - | - | - | - |
`x-aicl-command-palette` | Y | Y | Y | - | - | - | - |
`x-aicl-component-reference` | Y | Y | Y | - | - | - | - |
`x-aicl-data-table` | Y | Y | Y | - | - | - | - |
`x-aicl-divider` | Y | Y | Y | - | - | Y | Y |
`x-aicl-drawer` | Y | Y | Y | - | - | - | - |
`x-aicl-dropdown` | Y | Y | Y | - | - | - | - |
`x-aicl-empty-state` | Y | Y | Y | - | - | - | - |
`x-aicl-ignibyte-logo` | Y | Y | Y | - | - | Y | Y |
`x-aicl-info-card` | Y | Y | Y | - | - | - | - |
`x-aicl-kpi-card` | Y | Y | Y | - | - | Y | Y |
`x-aicl-metadata-list` | Y | Y | Y | - | - | Y | Y |
`x-aicl-modal` | Y | Y | Y | - | - | - | - |
`x-aicl-progress-card` | Y | Y | Y | - | - | Y | Y |
`x-aicl-quick-action` | Y | Y | Y | - | - | - | - |
`x-aicl-spinner` | Y | Y | Y | - | - | - | - |
`x-aicl-split-layout` | Y | Y | Y | - | - | - | - |
`x-aicl-stat-card` | Y | Y | Y | - | - | Y | Y |
`x-aicl-stats-row` | Y | Y | Y | - | - | - | - |
`x-aicl-status-badge` | Y | Y | Y | - | - | Y | Y |
`x-aicl-tab-panel` | Y | Y | Y | - | - | - | - |
`x-aicl-tabs` | Y | Y | Y | - | - | - | - |
`x-aicl-timeline` | Y | Y | Y | - | - | - | - |
`x-aicl-toast` | Y | Y | Y | - | - | - | - |
`x-aicl-tooltip` | Y | Y | Y | - | - | - | - |
`x-aicl-trend-card` | Y | Y | Y | - | - | - | - |
