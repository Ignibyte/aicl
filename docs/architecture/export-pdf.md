# Export & PDF

**Version:** 1.1
**Last Updated:** 2026-02-06
**Owner:** `/architect`

---

## Overview

AICL provides two data export mechanisms: CSV/XLSX export via Filament's native export system (OpenSpout) and PDF generation via DomPDF. Both are exposed as Filament table actions that can be added to any entity resource.

---

## CSV/XLSX Export (Filament Native)

As of v1.2.0, AICL uses Filament's built-in export system instead of custom export actions. This provides queue-based, chunked exports with CSV and XLSX format support out of the box.

### Exporter Class Pattern

Each entity that supports export needs an `Exporter` subclass:

**Location:** `packages/aicl/src/Filament/Exporters/ProjectExporter.php`

```php
namespace Aicl\Filament\Exporters;

use App\Models\Project;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ProjectExporter extends Exporter
{
    protected static ?string $model = Project::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name'),
            ExportColumn::make('status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : $state),
            ExportColumn::make('priority')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : $state),
            ExportColumn::make('owner.name')->label('Owner'),
            ExportColumn::make('start_date'),
            ExportColumn::make('end_date'),
            ExportColumn::make('budget'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your project export with ' . number_format($export->successful_rows) . ' rows is ready.';
    }
}
```

### Table Integration

Use Filament's native `ExportAction` and `ExportBulkAction`:

```php
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Aicl\Filament\Exporters\ProjectExporter;

// In table configuration
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
])
```

**Features:**
- Queue-based, chunked export for large datasets
- CSV and XLSX format support
- Column selection UI (auto-generated from exporter)
- Completion notification when export finishes
- Export history tracked in `exports` table

### Required Migrations

Filament native export requires three tables (published during `aicl:install`):

- `exports` — tracks export jobs
- `imports` — tracks import jobs (for future use)
- `failed_import_rows` — failed row tracking

---

## PDF Generation

### PdfGenerator Service

**Location:** `packages/aicl/src/Services/PdfGenerator.php`

Wrapper around DomPDF for consistent PDF generation:

```php
use Aicl\Services\PdfGenerator;

$pdf = app(PdfGenerator::class);

// Generate and download
return $pdf->download(
    view: 'aicl::pdf.project-report',
    data: ['project' => $project],
    filename: "project-{$project->id}.pdf"
);

// Generate and stream
return $pdf->stream(
    view: 'aicl::pdf.projects-list',
    data: ['projects' => $projects],
    filename: 'projects.pdf'
);

// Generate raw PDF string
$pdfContent = $pdf->render(
    view: 'aicl::pdf.project-report',
    data: ['project' => $project]
);
```

### PdfAction

**Location:** `packages/aicl/src/Filament/Actions/PdfAction.php`

Row action for single-record PDF download:

```php
// In table configuration
->actions([
    PdfAction::make()
        ->pdfView('aicl::pdf.project-report')
        ->pdfData(fn ($record) => ['project' => $record])
        ->filename(fn ($record) => "project-{$record->id}.pdf"),
])
```

**Important naming:** Methods are `pdfView()`, `pdfData()`, NOT `view()`, `data()` — those conflict with Filament base class methods.

**Property type:** `$pdfFilename` is `string|\Closure|null` (not just `?string`) because `filename()` accepts closures.

---

## PDF Templates

**Location:** `packages/aicl/resources/views/pdf/`

### Template Structure

```
packages/aicl/resources/views/pdf/
├── layout.blade.php           # Base layout (HTML structure, page setup)
├── styles.blade.php           # Shared CSS styles
├── project-report.blade.php   # Single project report
└── projects-list.blade.php    # Multi-project list report
```

### Layout Template

```blade
{{-- layout.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Report')</title>
    <style>
        @include('aicl::pdf.styles')
    </style>
</head>
<body>
    <header>
        <h1>@yield('title')</h1>
        <p class="date">Generated: {{ now()->format('M d, Y H:i') }}</p>
    </header>

    <main>
        @yield('content')
    </main>

    <footer>
        <p>Page <span class="page-number"></span></p>
    </footer>
</body>
</html>
```

### Creating Custom PDF Templates

For client entities, create templates in the client project:

```blade
{{-- resources/views/pdf/invoice-report.blade.php --}}
@extends('aicl::pdf.layout')

@section('title', 'Invoice Report')

@section('content')
    <h2>{{ $invoice->number }}</h2>
    <table>
        <tr><td>Client:</td><td>{{ $invoice->client->name }}</td></tr>
        <tr><td>Amount:</td><td>${{ number_format($invoice->total, 2) }}</td></tr>
        <tr><td>Status:</td><td>{{ $invoice->status->label() }}</td></tr>
    </table>
@endsection
```

Then use in the Filament resource:

```php
PdfAction::make()
    ->pdfView('pdf.invoice-report')
    ->pdfData(fn ($record) => ['invoice' => $record])
```

---

## DomPDF Configuration

```php
// config/dompdf.php (if published)
'paper_size' => 'letter',
'orientation' => 'portrait',
'font_dir' => storage_path('fonts'),
```

**Limitations:**
- No JavaScript rendering (server-side HTML only)
- Limited CSS support (no flexbox, no grid)
- Use `<table>` for layouts in PDF templates
- Images must be absolute paths or base64-encoded

---

## Testing

```php
class PdfGeneratorTest extends TestCase
{
    public function test_pdf_generation(): void
    {
        $project = Project::factory()->create();

        $pdf = app(PdfGenerator::class);
        $content = $pdf->render('aicl::pdf.project-report', ['project' => $project]);

        $this->assertNotEmpty($content);
        $this->assertStringStartsWith('%PDF', $content);
    }
}

class ExportActionTest extends TestCase
{
    public function test_exporter_has_columns(): void
    {
        $columns = ProjectExporter::getColumns();

        $this->assertNotEmpty($columns);
        $this->assertContainsOnlyInstancesOf(ExportColumn::class, $columns);
    }

    public function test_exporter_completion_message(): void
    {
        $export = new Export();
        $export->successful_rows = 42;

        $message = ProjectExporter::getCompletedNotificationBody($export);

        $this->assertStringContainsString('42', $message);
    }
}
```

---

## Related Documents

- [Filament UI](filament-ui.md) — Table actions integration
- [Entity System](entity-system.md) — Entity data for export
