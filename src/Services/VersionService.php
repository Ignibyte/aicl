<?php

declare(strict_types=1);

namespace Aicl\Services;

use Aicl\AiclServiceProvider;

/**
 * Version information service for framework and project versions.
 *
 * Reads the framework version from the AiclServiceProvider::VERSION constant
 * and the project version by parsing the first semver heading from CHANGELOG.md.
 * Used by the version badge widget in the admin panel topbar.
 *
 * Memoizes results per-worker to avoid repeated file reads in Swoole/Octane.
 *
 * @see AiclServiceProvider::VERSION  Framework version constant
 */
class VersionService
{
    /** Memoized project version — survives across requests in Octane workers. */
    private static ?string $cachedProjectVersion = null;

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
     * Memoized per-worker — the file is only read once per Octane worker lifecycle.
     *
     * @return string Semantic version string, or 'unknown' if not found
     */
    public function projectVersion(): string
    {
        return self::$cachedProjectVersion ??= $this->parseVersionFrom(base_path('CHANGELOG.md'));
    }

    /**
     * Reset cached version. Called after deploys or in tests.
     */
    public static function resetCache(): void
    {
        self::$cachedProjectVersion = null;
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

        if ($content === false) {
            return 'unknown';
        }

        if (preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $content, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }
}
