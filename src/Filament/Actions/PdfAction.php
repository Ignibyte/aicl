<?php

declare(strict_types=1);

namespace Aicl\Filament\Actions;

use Aicl\Services\PdfGenerator;
use Closure;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filament action for generating and downloading PDF reports from entity records.
 *
 * Provides a fluent builder API to configure the Blade view, filename, data callback,
 * paper size, and orientation. Uses PdfGenerator under the hood.
 *
 * Usage in a Filament page or resource:
 *   PdfAction::make()
 *       ->pdfView('pdf.invoice')
 *       ->filename(fn (Model $record) => "invoice-{$record->id}.pdf")
 *       ->pdfData(fn (Model $record) => ['invoice' => $record])
 *       ->landscape()
 *
 * @see PdfGenerator  The underlying PDF rendering service
 *
 * @codeCoverageIgnore Reason: filament-closure -- Filament action setUp closure
 */
class PdfAction extends Action
{
    /** @var string|null Custom Blade view for the PDF */
    protected ?string $pdfView = null;

    /** @var string|Closure|null Filename or closure that receives the record */
    protected string|Closure|null $pdfFilename = null;

    /** @var Closure|null Closure that returns view data from the record */
    protected ?Closure $dataCallback = null;

    /** @var string Paper size (e.g. 'a4', 'letter') */
    protected string $paper = 'a4';

    /** @var string Page orientation ('portrait' or 'landscape') */
    protected string $orientation = 'portrait';

    /**
     * Get the default action name.
     */
    public static function getDefaultName(): ?string
    {
        return 'download_pdf';
    }

    /**
     * Configure the action with default label, icon, color, and PDF generation callback.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Download PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->action(function (Model $record): Response {
                // @codeCoverageIgnoreStart — Filament Livewire rendering
                return $this->generatePdf($record);
                // @codeCoverageIgnoreEnd
            });
    }

    /**
     * Set the Blade view used to render the PDF.
     *
     * @param string $view Blade view name (e.g. 'pdf.invoice')
     */
    public function pdfView(string $view): static
    {
        $this->pdfView = $view;

        return $this;
    }

    /**
     * Set the download filename (static string or closure receiving the record).
     *
     * @param string|Closure $filename Filename or closure(Model $record): string
     */
    public function filename(string|Closure $filename): static
    {
        $this->pdfFilename = $filename;

        return $this;
    }

    /**
     * Set the data callback that provides view variables from the record.
     *
     * @param Closure $callback Closure(Model $record): array<string, mixed>
     */
    public function pdfData(Closure $callback): static
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        $this->dataCallback = $callback;

        return $this;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Set the paper size for the PDF.
     *
     * @param string $paper Paper size identifier (e.g. 'a4', 'letter')
     */
    public function paper(string $paper): static
    {
        $this->paper = $paper;

        return $this;
    }

    /**
     * Set the page orientation for the PDF.
     *
     * @param string $orientation Either 'portrait' or 'landscape'
     */
    public function orientation(string $orientation): static
    {
        $this->orientation = $orientation;

        return $this;
    }

    /**
     * Set the PDF orientation to landscape.
     */
    public function landscape(): static
    {
        $this->orientation = 'landscape';

        return $this;
    }

    /**
     * Generate and download the PDF for the given record.
     *
     * @param Model $record The entity record to generate a PDF for
     *
     * @return Response Download response with the PDF content
     */
    protected function generatePdf(Model $record): Response
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        $view = $this->pdfView ?? $this->getDefaultPdfView($record);
        $filename = $this->getPdfFilename($record);
        $data = $this->getPdfData($record);

        return PdfGenerator::make()
            ->paper($this->paper)
            ->orientation($this->orientation)
            ->download($view, $data, $filename);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Derive the default PDF view name from the model class.
     *
     * @param Model $record The entity record
     *
     * @return string Blade view name (e.g. 'aicl::pdf.user-report')
     */
    protected function getDefaultPdfView(Model $record): string
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        $modelName = strtolower(class_basename($record));

        return "aicl::pdf.{$modelName}-report";
        // @codeCoverageIgnoreEnd
    }

    /**
     * Resolve the PDF filename from the configured closure, string, or default.
     *
     * @param Model $record The entity record
     *
     * @return string Filename with .pdf extension
     */
    protected function getPdfFilename(Model $record): string
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        if ($this->pdfFilename) {
            if ($this->pdfFilename instanceof Closure) {
                return ($this->pdfFilename)($record);
            }

            return $this->pdfFilename;
        }

        $modelName = strtolower(class_basename($record));
        $timestamp = now()->format('Y-m-d_His');

        return "{$modelName}_{$record->getKey()}_{$timestamp}.pdf";
        // @codeCoverageIgnoreEnd
    }

    /**
     * Resolve the view data from the configured callback or use defaults.
     *
     * @param Model $record The entity record
     *
     * @return array<string, mixed> View data for the PDF template
     */
    protected function getPdfData(Model $record): array
    {
        // @codeCoverageIgnoreStart — Filament Livewire rendering
        if ($this->dataCallback) {
            return ($this->dataCallback)($record);
        }

        $modelName = strtolower(class_basename($record));

        return [
            $modelName => $record,
            'title' => class_basename($record).' Report',
        ];
        // @codeCoverageIgnoreEnd
    }
}
