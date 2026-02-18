<?php

namespace Aicl\Tests\Unit\Services;

use Aicl\Services\VersionService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VersionServiceTest extends TestCase
{
    public function test_framework_version_extracts_version_from_changelog(): void
    {
        Cache::forget('aicl.version.framework');

        $service = new VersionService;

        $version = $service->frameworkVersion();

        // The CHANGELOG_FRAMEWORK.md always starts with a version heading
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
    }

    public function test_current_returns_cached_version(): void
    {
        Cache::forget('aicl.version.framework');

        $service = new VersionService;

        $version = $service->current();

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);

        // Verify it was cached under the new key
        $this->assertSame($version, Cache::get('aicl.version.framework'));
    }

    public function test_current_delegates_to_framework_version(): void
    {
        Cache::forget('aicl.version.framework');

        $service = new VersionService;

        $this->assertSame($service->frameworkVersion(), $service->current());
    }

    public function test_project_version_returns_unknown_when_changelog_missing(): void
    {
        $path = base_path('CHANGELOG.md');
        $backup = $path.'.bak';
        $exists = file_exists($path);

        if ($exists) {
            rename($path, $backup);
        }

        Cache::forget('aicl.version.project');

        try {
            $service = new VersionService;
            $this->assertSame('unknown', $service->projectVersion());
        } finally {
            if ($exists) {
                rename($backup, $path);
            }
            Cache::forget('aicl.version.project');
        }
    }

    public function test_version_badge_view_exists(): void
    {
        $this->assertTrue(view()->exists('aicl::components.version-badge'));
    }

    public function test_version_badge_view_renders_with_version(): void
    {
        $html = view('aicl::components.version-badge', ['version' => '1.11.0'])->render();

        $this->assertStringContainsString('v1.11.0', $html);
        $this->assertStringContainsString('fi-topbar-version-badge', $html);
        $this->assertStringContainsString('changelog', $html);
    }
}
