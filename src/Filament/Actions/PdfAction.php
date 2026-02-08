<?php

namespace Aicl\Filament\Actions;

use Aicl\Services\PdfGenerator;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

class PdfAction extends Action
{
    protected ?string $pdfView = null;

    protected string|\Closure|null $pdfFilename = null;

    protected ?\Closure $dataCallback = null;

    protected string $paper = 'a4';

    protected string $orientation = 'portrait';

    public static function getDefaultName(): ?string
    {
        return 'download_pdf';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Download PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->action(function (Model $record): Response {
                return $this->generatePdf($record);
            });
    }

    public function pdfView(string $view): static
    {
        $this->pdfView = $view;

        return $this;
    }

    public function filename(string|\Closure $filename): static
    {
        $this->pdfFilename = $filename;

        return $this;
    }

    public function pdfData(\Closure $callback): static
    {
        $this->dataCallback = $callback;

        return $this;
    }

    public function paper(string $paper): static
    {
        $this->paper = $paper;

        return $this;
    }

    public function orientation(string $orientation): static
    {
        $this->orientation = $orientation;

        return $this;
    }

    public function landscape(): static
    {
        $this->orientation = 'landscape';

        return $this;
    }

    protected function generatePdf(Model $record): Response
    {
        $view = $this->pdfView ?? $this->getDefaultPdfView($record);
        $filename = $this->getPdfFilename($record);
        $data = $this->getPdfData($record);

        return PdfGenerator::make()
            ->paper($this->paper)
            ->orientation($this->orientation)
            ->download($view, $data, $filename);
    }

    protected function getDefaultPdfView(Model $record): string
    {
        $modelName = strtolower(class_basename($record));

        return "aicl::pdf.{$modelName}-report";
    }

    protected function getPdfFilename(Model $record): string
    {
        if ($this->pdfFilename) {
            if ($this->pdfFilename instanceof \Closure) {
                return ($this->pdfFilename)($record);
            }

            return $this->pdfFilename;
        }

        $modelName = strtolower(class_basename($record));
        $timestamp = now()->format('Y-m-d_His');

        return "{$modelName}_{$record->getKey()}_{$timestamp}.pdf";
    }

    protected function getPdfData(Model $record): array
    {
        if ($this->dataCallback) {
            return ($this->dataCallback)($record);
        }

        $modelName = strtolower(class_basename($record));

        return [
            $modelName => $record,
            'title' => class_basename($record).' Report',
        ];
    }
}
