<?php

namespace Aicl\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PdfGenerator
{
    protected string $paper = 'a4';

    protected string $orientation = 'portrait';

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

    public function portrait(): static
    {
        $this->orientation = 'portrait';

        return $this;
    }

    public function generate(string $view, array $data = []): string
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper($this->paper, $this->orientation);

        return $pdf->output();
    }

    public function download(string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper($this->paper, $this->orientation);

        return $pdf->download($filename);
    }

    public function stream(string $view, array $data = []): Response
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper($this->paper, $this->orientation);

        return $pdf->stream();
    }

    public function save(string $view, array $data, string $path, ?string $disk = null): bool
    {
        $content = $this->generate($view, $data);

        if ($disk) {
            return Storage::disk($disk)->put($path, $content);
        }

        return Storage::put($path, $content);
    }

    public static function make(): static
    {
        return new static;
    }
}
