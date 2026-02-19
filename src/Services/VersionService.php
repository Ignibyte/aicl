<?php

namespace Aicl\Services;

use Composer\InstalledVersions;
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
        return Cache::rememberForever('aicl.version.framework', function (): string {
            // 1. Dev environment: changelog at project root
            $version = $this->parseVersionFrom(base_path('CHANGELOG_FRAMEWORK.md'));

            if ($version !== 'unknown') {
                return $version;
            }

            // 2. Shipped projects: read from Composer's installed package metadata
            return $this->parseComposerVersion('aicl/aicl');
        });
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

    /**
     * Get the version of an installed Composer package.
     * Returns the pretty version (e.g. "2.5.0") or "unknown".
     */
    private function parseComposerVersion(string $package): string
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled($package)) {
            return 'unknown';
        }

        $version = InstalledVersions::getPrettyVersion($package);

        if ($version && preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }
}
