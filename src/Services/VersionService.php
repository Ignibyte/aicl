<?php

namespace Aicl\Services;

use Aicl\AiclServiceProvider;

class VersionService
{
    /**
     * Get the current AICL framework version.
     */
    public function current(): string
    {
        return $this->frameworkVersion();
    }

    public function frameworkVersion(): string
    {
        return AiclServiceProvider::VERSION;
    }

    public function projectVersion(): string
    {
        return $this->parseVersionFrom(base_path('CHANGELOG.md'));
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
