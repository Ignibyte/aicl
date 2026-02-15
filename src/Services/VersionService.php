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
        return Cache::rememberForever('aicl.version', fn () => $this->parse());
    }

    /**
     * Parse the version from CHANGELOG_FRAMEWORK.md.
     * Looks for the first `## [x.y.z]` heading pattern.
     */
    public function parse(): string
    {
        $path = base_path('CHANGELOG_FRAMEWORK.md');

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
