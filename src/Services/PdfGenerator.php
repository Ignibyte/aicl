<?php

declare(strict_types=1);

namespace Aicl\Services;

use Aicl\Filament\Actions\PdfAction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fluent PDF generation service backed by DomPDF.
 *
 * Provides a builder-pattern API for generating PDF documents from Blade views
 * with configurable paper size and orientation. Supports output as raw string,
 * download response, inline stream, or file storage.
 *
 * @see PdfAction  Filament action that uses this service
 */
class PdfGenerator
{
    /** @var string Paper size (e.g. 'a4', 'letter') */
    protected string $paper = 'a4';

    /** @var string Page orientation ('portrait' or 'landscape') */
    protected string $orientation = 'portrait';

    /**
     * Set the paper size.
     *
     * @param  string  $paper  Paper size identifier (e.g. 'a4', 'letter', 'legal')
     */
    public function paper(string $paper): static
    {
        $this->paper = $paper;

        return $this;
    }

    /**
     * Set the page orientation.
     *
     * @param  string  $orientation  Either 'portrait' or 'landscape'
     */
    public function orientation(string $orientation): static
    {
        $this->orientation = $orientation;

        return $this;
    }

    /**
     * Set orientation to landscape.
     */
    public function landscape(): static
    {
        $this->orientation = 'landscape';

        return $this;
    }

    /**
     * Set orientation to portrait.
     */
    public function portrait(): static
    {
        $this->orientation = 'portrait';

        return $this;
    }

    /**
     * Generate PDF content as a raw string.
     *
     * @param  string  $view  Blade view name
     * @param  array<string, mixed>  $data  View data
     * @return string Raw PDF binary content
     */
    public function generate(string $view, array $data = []): string
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper($this->paper, $this->orientation);

        return $pdf->output();
    }

    /**
     * Generate a PDF and return a download response.
     *
     * @param  string  $view  Blade view name
     * @param  array<string, mixed>  $data  View data
     * @param  string  $filename  Download filename (e.g. 'report.pdf')
     */
    public function download(string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper($this->paper, $this->orientation);

        return $pdf->download($filename);
    }

    /**
     * Generate a PDF and return an inline stream response (displays in browser).
     *
     * @param  string  $view  Blade view name
     * @param  array<string, mixed>  $data  View data
     */
    public function stream(string $view, array $data = []): Response
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper($this->paper, $this->orientation);

        return $pdf->stream();
    }

    /**
     * Generate a PDF and save it to disk.
     *
     * @param  string  $view  Blade view name
     * @param  array<string, mixed>  $data  View data
     * @param  string  $path  Storage path for the file
     * @param  string|null  $disk  Storage disk name, or null for default disk
     * @return bool Whether the file was saved successfully
     */
    public function save(string $view, array $data, string $path, ?string $disk = null): bool
    {
        $content = $this->generate($view, $data);

        if ($disk) {
            return (bool) Storage::disk($disk)->put($path, $content);
        }

        return (bool) Storage::put($path, $content);
    }

    /**
     * Create a new PdfGenerator instance (static factory).
     */
    public static function make(): static
    {
        return new static;
    }
}
