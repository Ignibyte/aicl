<?php

namespace Aicl\Services;

use Illuminate\Support\Facades\Cache;

class VersionService
{
    /**
     * Get the current AICL framework version from the changelog.
     * Cached forever (version doesn't change at runtime).
     */
    public function current(): string
    {
        return $this->frameworkVersion();
    }

    public function frameworkVersion(): string
    {
        return Cache::rememberForever('aicl.version.framework', fn () => $this->parseVersionFrom(base_path('CHANGELOG_FRAMEWORK.md')));
    }

    public function projectVersion(): string
    {
        return Cache::rememberForever('aicl.version.project', fn () => $this->parseVersionFrom(base_path('CHANGELOG.md')));
    }

    /**
     * Parse the version from a changelog file.
     * Looks for the first `## [x.y.z]` heading pattern.
     */
    private function parseVersionFrom(string $path): string
    {
        if (! file_exists($path)) {
            return 'unknown';
        }

        $content = file_get_contents($path);

        if (preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $content, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }
}
