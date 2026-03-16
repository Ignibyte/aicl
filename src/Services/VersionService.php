<?php

namespace Aicl\Services;

use Aicl\AiclServiceProvider;

/**
 * Version information service for framework and project versions.
 *
 * Reads the framework version from the AiclServiceProvider::VERSION constant
 * and the project version by parsing the first semver heading from CHANGELOG.md.
 * Used by the version badge widget in the admin panel topbar.
 *
 * @see AiclServiceProvider::VERSION  Framework version constant
 */
class VersionService
{
    /**
     * Get the current AICL framework version (alias for frameworkVersion).
     *
     * @return string Semantic version string (e.g. '1.6.1')
     */
    public function current(): string
    {
        return $this->frameworkVersion();
    }

    /**
     * Get the AICL framework package version.
     *
     * @return string Semantic version string from AiclServiceProvider::VERSION
     */
    public function frameworkVersion(): string
    {
        return AiclServiceProvider::VERSION;
    }

    /**
     * Get the project version by parsing the root CHANGELOG.md.
     *
     * @return string Semantic version string, or 'unknown' if not found
     */
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
