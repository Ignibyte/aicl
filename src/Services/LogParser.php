<?php

namespace Aicl\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class LogParser
{
    /**
     * Get all log files in the logs directory.
     *
     * @return array<int, array{path: string, name: string, size: int, modified: int}>
     */
    public function getLogFiles(): array
    {
        $logsPath = storage_path('logs');

        if (! File::isDirectory($logsPath)) {
            return [];
        }

        $files = File::files($logsPath);

        return collect($files)
            ->filter(fn ($file) => $file->getExtension() === 'log')
            ->map(fn ($file) => [
                'path' => $file->getPathname(),
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
            ])
            ->sortByDesc('modified')
            ->values()
            ->toArray();
    }

    /**
     * Parse a log file and return structured entries.
     *
     * @return Collection<int, array{timestamp: string, level: string, message: string, context: string|null}>
     */
    public function parseLogFile(string $path, int $limit = 100, ?string $levelFilter = null, ?string $search = null): Collection
    {
        if (! File::exists($path)) {
            return collect();
        }

        $content = File::get($path);
        $entries = $this->parseContent($content);

        if ($levelFilter) {
            $entries = $entries->filter(fn ($entry) => strtoupper($entry['level']) === strtoupper($levelFilter));
        }

        if ($search) {
            $entries = $entries->filter(fn ($entry) => str_contains(strtolower($entry['message']), strtolower($search)));
        }

        return $entries->take($limit)->values();
    }

    /**
     * Parse log content into structured entries.
     *
     * @return Collection<int, array{timestamp: string, level: string, message: string, context: string|null}>
     */
    protected function parseContent(string $content): Collection
    {
        $pattern = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}\.?\d*[\+\-]?\d*:?\d*)\] (\w+)\.(\w+): (.+?)(?=\n\[|\z)/sm';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(function ($match) {
                $message = trim($match[4] ?? '');
                $context = null;

                if (preg_match('/^(.+?)\s*(\{.*\}|\[.*\])\s*$/s', $message, $parts)) {
                    $message = trim($parts[1]);
                    $context = $parts[2] ?? null;
                }

                $stackTrace = null;
                if (str_contains($message, "\n")) {
                    $lines = explode("\n", $message);
                    $message = array_shift($lines);
                    $stackTrace = implode("\n", $lines);
                }

                return [
                    'timestamp' => $match[1],
                    'environment' => $match[2] ?? 'local',
                    'level' => strtoupper($match[3] ?? 'INFO'),
                    'message' => $message,
                    'context' => $context,
                    'stack_trace' => $stackTrace,
                ];
            })
            ->sortByDesc('timestamp');
    }

    /**
     * Get the tail of a log file.
     *
     * @return Collection<int, array{timestamp: string, level: string, message: string, context: string|null}>
     */
    public function tail(string $path, int $lines = 50): Collection
    {
        if (! File::exists($path)) {
            return collect();
        }

        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - ($lines * 10));
        $content = '';

        $file->seek($startLine);
        while (! $file->eof()) {
            $content .= $file->fgets();
        }

        return $this->parseContent($content)->take($lines);
    }

    /**
     * Get available log levels from a file.
     *
     * @return array<string, string>
     */
    public function getAvailableLevels(): array
    {
        return [
            'DEBUG' => 'Debug',
            'INFO' => 'Info',
            'NOTICE' => 'Notice',
            'WARNING' => 'Warning',
            'ERROR' => 'Error',
            'CRITICAL' => 'Critical',
            'ALERT' => 'Alert',
            'EMERGENCY' => 'Emergency',
        ];
    }

    /**
     * Get the color for a log level.
     */
    public function getLevelColor(string $level): string
    {
        return match (strtoupper($level)) {
            'DEBUG' => 'gray',
            'INFO' => 'info',
            'NOTICE' => 'primary',
            'WARNING' => 'warning',
            'ERROR' => 'danger',
            'CRITICAL' => 'danger',
            'ALERT' => 'danger',
            'EMERGENCY' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Delete a log file.
     */
    public function deleteFile(string $path): bool
    {
        if (! File::exists($path) || ! str_ends_with($path, '.log')) {
            return false;
        }

        if (! str_starts_with(realpath($path), realpath(storage_path('logs')))) {
            return false;
        }

        return File::delete($path);
    }

    /**
     * Clear the contents of a log file.
     */
    public function clearFile(string $path): bool
    {
        if (! File::exists($path) || ! str_ends_with($path, '.log')) {
            return false;
        }

        if (! str_starts_with(realpath($path), realpath(storage_path('logs')))) {
            return false;
        }

        return File::put($path, '') !== false;
    }

    /**
     * Format file size for display.
     */
    public function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2).' '.$units[$i];
    }
}
