# AICL Filament Integration Patterns

**Version:** 1.1
**Last Updated:** 2026-02-06
**Filament Version:** v4.7.0
**Owner:** `/architect`, `/rlm`

---

## Overview

Filament v4 is the admin panel framework for AICL. It provides:
- Declarative PHP-based resources
- Automatic CRUD operations
- Dashboard widgets
- Custom pages
- Table and form builders

This document covers Filament v4-specific patterns, namespace changes from v3, and AICL integration points.

---

## Filament v4 Key Changes

### Namespace Changes

| Component | v3 Namespace | v4 Namespace |
|-----------|--------------|--------------|
| Section | `Filament\Forms\Components\Section` | `Filament\Schemas\Components\Section` |
| Grid | `Filament\Forms\Components\Grid` | `Filament\Schemas\Components\Grid` |
| Form components | `Filament\Forms\Components\*` | Still `Filament\Forms\Components\*` |
| Actions | `Filament\Tables\Actions\*` | `Filament\Actions\*` |

### Property Changes

| Property | v3 | v4 |
|----------|----|----|
| `$navigationGroup` | `?string` | `string \| UnitEnum \| null` |
| `ChartWidget::$heading` | `protected static` | Instance property (non-static) |
| Widget `$view` | `protected static string` | Instance property (non-static) |

### Deprecated Components

| Deprecated | Replacement |
|------------|-------------|
| `DoughnutChartWidget` | `ChartWidget` with `getType()` returning `'doughnut'` |

---

## AICL Filament Plugin

**Location:** `packages/aicl/src/AiclPlugin.php`

```php
namespace Aicl;

use Filament\Contracts\Plugin;
use Filament\Panel;

class AiclPlugin implements Plugin
{
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

    public function boot(Panel $panel): void {}

    protected function getResources(): array
    {
        return [
            \Aicl\Filament\Resources\FailedJobs\FailedJobResource::class,
            \Aicl\Filament\Resources\Users\UserResource::class,
            \Aicl\Filament\Resources\Projects\ProjectResource::class,
        ];
    }

    protected function getPages(): array
    {
        return [
            \Aicl\Filament\Pages\QueueDashboard::class,
            \Aicl\Filament\Pages\LogViewer::class,
            \Aicl\Filament\Pages\ManageSettings::class,
            \Aicl\Filament\Pages\AuditLog::class,
            \Aicl\Filament\Pages\NotificationCenter::class,
            \Aicl\Filament\Pages\NotificationLogPage::class,
            \Aicl\Filament\Pages\Search::class,
            \Aicl\Filament\Pages\ApiTokens::class,
            \Aicl\Filament\Pages\Styleguide\StyleguideOverview::class,
            \Aicl\Filament\Pages\Styleguide\LayoutComponents::class,
            \Aicl\Filament\Pages\Styleguide\MetricComponents::class,
            \Aicl\Filament\Pages\Styleguide\DataDisplayComponents::class,
            \Aicl\Filament\Pages\Styleguide\ActionComponents::class,
        ];
    }

    protected function getWidgets(): array
    {
        return [
            \Aicl\Filament\Widgets\QueueStatsWidget::class,
            \Aicl\Filament\Widgets\RecentFailedJobsWidget::class,
            \Aicl\Filament\Widgets\GlobalSearchWidget::class,
            \Aicl\Filament\Widgets\ProjectStatsOverview::class,
            \Aicl\Filament\Widgets\ProjectsByStatusChart::class,
            \Aicl\Filament\Widgets\UpcomingDeadlinesWidget::class,
        ];
    }
}
```

### Registration in AdminPanelProvider

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->login(\Aicl\Filament\Pages\Auth\Login::class)
        ->registration()
        ->passwordReset()
        ->emailVerification()
        ->plugin(\Aicl\AiclPlugin::make())
        ->plugin(\BezhanSalleh\FilamentShield\FilamentShieldPlugin::make())
        ->plugin(
            \Jeffgreco13\FilamentBreezy\BreezyCore::make()
                ->myProfile(shouldRegisterNavigation: true, slug: 'profile')
                ->enableTwoFactorAuthentication()
        )
        ->viteTheme('resources/css/filament/admin/theme.css')
        // ...
}
```

---

## Resource Pattern

### Directory Structure

Package-provided resources live in `packages/aicl/src/Filament/Resources/`. Client-generated resources (via `aicl:make-entity`) go into `app/Filament/Resources/`.

```
packages/aicl/src/Filament/Resources/Projects/
├── ProjectResource.php              # Main resource class
├── Schemas/
│   └── ProjectForm.php              # Form schema
├── Tables/
│   └── ProjectsTable.php            # Table schema
└── Pages/
    ├── ListProjects.php             # List page
    ├── CreateProject.php            # Create page
    ├── EditProject.php              # Edit page
    └── ViewProject.php              # View page
```

### Resource Class

**Location:** `packages/aicl/src/Filament/Resources/Projects/ProjectResource.php`

```php
namespace Aicl\Filament\Resources\Projects;

use App\Models\Project;
use Aicl\Filament\Resources\Projects\Pages;
use Aicl\Filament\Resources\Projects\Schemas\ProjectForm;
use Aicl\Filament\Resources\Projects\Tables\ProjectsTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Heroicons\Heroicon;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static string | \UnitEnum | null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // Relationship managers
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view' => Pages\ViewProject::route('/{record}'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
```

---

## Form Schema Pattern

**Location:** `packages/aicl/src/Filament/Resources/Projects/Schemas/ProjectForm.php`

```php
namespace Aicl\Filament\Resources\Projects\Schemas;

use Aicl\Enums\ProjectPriority;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Project Details')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),

                    RichEditor::make('description')
                        ->columnSpanFull(),

                    Select::make('priority')
                        ->options(ProjectPriority::class)
                        ->required(),

                    Select::make('owner_id')
                        ->relationship('owner', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->columns(2),

            Section::make('Schedule')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            DatePicker::make('start_date'),
                            DatePicker::make('end_date'),
                        ]),

                    TextInput::make('budget')
                        ->numeric()
                        ->prefix('$'),
                ]),

            Section::make('Settings')
                ->schema([
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])
                ->collapsed(),
        ]);
    }
}
```

### Form Component Reference

| Component | Namespace | Purpose |
|-----------|-----------|---------|
| `TextInput` | `Filament\Forms\Components` | Text fields |
| `Select` | `Filament\Forms\Components` | Dropdowns |
| `Toggle` | `Filament\Forms\Components` | Boolean switches |
| `DatePicker` | `Filament\Forms\Components` | Date selection |
| `RichEditor` | `Filament\Forms\Components` | WYSIWYG editor |
| `Section` | `Filament\Schemas\Components` | Grouped fields (v4!) |
| `Grid` | `Filament\Schemas\Components` | Multi-column layout (v4!) |

---

## Table Schema Pattern

**Location:** `packages/aicl/src/Filament/Resources/Projects/Tables/ProjectsTable.php`

```php
namespace Aicl\Filament\Resources\Projects\Tables;

use Aicl\Filament\Actions\PdfAction;
use Aicl\Filament\Exporters\ProjectExporter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => $state->color())
                    ->sortable(),

                TextColumn::make('priority')
                    ->badge()
                    ->sortable(),

                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->sortable(),

                TextColumn::make('start_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('end_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([/* state morph classes */]),

                SelectFilter::make('priority')
                    ->options(\Aicl\Enums\ProjectPriority::class),

                SelectFilter::make('owner')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                PdfAction::make()
                    ->pdfView('aicl::pdf.project-report')
                    ->pdfData(fn ($record) => [
                        'project' => $record->load(['owner', 'tags']),
                        'title' => $record->name . ' Report',
                    ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(ProjectExporter::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ProjectExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

### Table Column Reference

| Column | Use Case |
|--------|----------|
| `TextColumn` | Text, numbers, relationships |
| `IconColumn` | Boolean as icon |
| `BadgeColumn` | Status badges |
| `ImageColumn` | Thumbnails |

### Table Action Reference

| Action | Namespace | Purpose |
|--------|-----------|---------|
| `ViewAction` | `Filament\Actions` | View record |
| `EditAction` | `Filament\Actions` | Edit record |
| `DeleteAction` | `Filament\Actions` | Delete record |
| `ExportAction` | `Filament\Actions` | Export table to CSV/XLSX (Filament native) |
| `ExportBulkAction` | `Filament\Actions` | Export selected to CSV/XLSX (Filament native) |
| `PdfAction` | `Aicl\Filament\Actions` | Generate PDF |

---

## Page Patterns

### List Page

```php
namespace Aicl\Filament\Resources\Projects\Pages;

use Aicl\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

### Create Page

```php
namespace Aicl\Filament\Resources\Projects\Pages;

use Aicl\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
```

### Edit Page

```php
namespace Aicl\Filament\Resources\Projects\Pages;

use Aicl\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
```

### View Page

```php
namespace Aicl\Filament\Resources\Projects\Pages;

use Aicl\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
```

---

## Custom Page Pattern

**Location:** `packages/aicl/src/Filament/Pages/QueueDashboard.php`

```php
namespace Aicl\Filament\Pages;

use Filament\Pages\Page;

class QueueDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static string | \UnitEnum | null $navigationGroup = 'System';

    protected static ?int $navigationSort = 100;

    // Non-static view property in v4!
    protected string $view = 'aicl::filament.pages.queue-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }
}
```

**View:** `packages/aicl/resources/views/filament/pages/queue-dashboard.blade.php`

```blade
<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        @livewire(\Aicl\Filament\Widgets\QueueStatsWidget::class)
    </div>

    <div class="mb-6">
        @livewire(\Aicl\Filament\Widgets\RecentFailedJobsWidget::class)
    </div>
</x-filament-panels::page>
```

---

## Widget Patterns

### Stats Widget

```php
namespace Aicl\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class QueueStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Pending Jobs', $this->getPendingCount())
                ->description('In queue')
                ->icon('heroicon-o-clock'),

            Stat::make('Processed Today', $this->getProcessedToday())
                ->description('Last 24 hours')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Failed Jobs', $this->getFailedCount())
                ->description('Needs attention')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }

    private function getPendingCount(): int
    {
        return DB::table('jobs')->count();
    }
    // ...
}
```

### Chart Widget

```php
namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Widgets\ChartWidget;

class ProjectsByStatusChart extends ChartWidget
{
    // Non-static in v4!
    protected string $heading = 'Projects by Status';

    protected function getData(): array
    {
        $data = Project::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'datasets' => [
                [
                    'data' => array_values($data),
                    'backgroundColor' => ['#9ca3af', '#22c55e', '#eab308', '#3b82f6', '#6b7280'],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut'; // Not DoughnutChartWidget!
    }
}
```

### Table Widget

```php
namespace Aicl\Filament\Widgets;

use Aicl\Models\FailedJob;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentFailedJobsWidget extends BaseWidget
{
    protected string $heading = 'Recent Failed Jobs';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(FailedJob::query()->latest()->limit(5))
            ->columns([
                TextColumn::make('queue'),
                TextColumn::make('failed_at')->dateTime(),
                TextColumn::make('exception')->limit(50),
            ])
            ->paginated(false);
    }
}
```

---

## AICL Custom Actions

> **Note:** CSV/XLSX export is now handled by Filament's native `ExportAction`/`ExportBulkAction` with `Exporter` subclasses. See [Export & PDF](export-pdf.md) for details.

### PdfAction

**Location:** `packages/aicl/src/Filament/Actions/PdfAction.php`

Row action for single-record PDF generation:

```php
use Aicl\Services\PdfGenerator;
use Filament\Actions\Action;

class PdfAction extends Action
{
    protected ?string $pdfViewName = null;
    protected ?\Closure $pdfDataCallback = null;

    public static function make(?string $name = 'pdf'): static
    {
        return parent::make($name)
            ->label('Download PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->action(function ($record, PdfAction $action) {
                $pdf = app(PdfGenerator::class);

                return $pdf->download(
                    $action->getPdfView(),
                    $action->getPdfData($record),
                    $action->getPdfFilename($record)
                );
            });
    }

    // Note: pdfView() and pdfData() method names avoid conflicts with base class
    public function pdfView(string $view): static
    {
        $this->pdfViewName = $view;
        return $this;
    }

    public function pdfData(\Closure $callback): static
    {
        $this->pdfDataCallback = $callback;
        return $this;
    }
}
```

**Important:** Method names are `pdfView()` and `pdfData()`, NOT `view()` and `data()` — those conflict with Filament base class methods.

---

## Relationship Managers

For managing related records inline:

```php
namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('status'),
                TextColumn::make('due_date')->date(),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }
}
```

Register in resource:

```php
public static function getRelations(): array
{
    return [
        RelationManagers\TasksRelationManager::class,
    ];
}
```

---

## Filament v4 Gotchas

### 1. Section/Grid Namespace

```php
// Wrong (v3)
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;

// Correct (v4)
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
```

### 2. Navigation Group Type

```php
// Wrong
protected static ?string $navigationGroup = 'Content';

// Correct
protected static string | \UnitEnum | null $navigationGroup = 'Content';
```

### 3. Widget Heading

```php
// Wrong (v3 - static)
protected static ?string $heading = 'My Widget';

// Correct (v4 - instance)
protected string $heading = 'My Widget';
```

### 4. Widget View

```php
// Wrong (v3 - static)
protected static string $view = 'my-view';

// Correct (v4 - instance)
protected string $view = 'my-view';
```

### 5. Actions Namespace

```php
// Table-specific actions still work
use Filament\Tables\Actions\EditAction;

// But standalone actions use:
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
```

### 6. Doughnut Charts

```php
// Deprecated
class MyChart extends DoughnutChartWidget { }

// Correct
class MyChart extends ChartWidget
{
    protected function getType(): string
    {
        return 'doughnut';
    }
}
```

---

## Testing Filament Resources

```php
class ProjectResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_list_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/admin/projects')
            ->assertOk()
            ->assertSee('Projects');
    }

    public function test_can_create_project(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        Livewire::actingAs($user)
            ->test(CreateProject::class)
            ->fillForm([
                'name' => 'Test Project',
                'priority' => 'medium',
                'owner_id' => $user->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('projects', ['name' => 'Test Project']);
    }

    public function test_can_edit_project(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $project = Project::factory()->create();

        Livewire::actingAs($user)
            ->test(EditProject::class, ['record' => $project->id])
            ->fillForm([
                'name' => 'Updated Project',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('Updated Project', $project->fresh()->name);
    }
}
```

---

## Related Documents

- [Foundation](foundation.md)
- [Component Library](component-library.md)
- [AI Generation Pipeline](ai-generation-pipeline.md)
- [Entity System](entity-system.md)
- [Export & PDF](export-pdf.md)
