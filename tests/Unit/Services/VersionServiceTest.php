<?php

namespace Aicl\Tests\Unit\Services;

use Aicl\AiclServiceProvider;
use Aicl\Services\VersionService;
use Tests\TestCase;

class VersionServiceTest extends TestCase
{
    public function test_framework_version_returns_constant(): void
    {
        $service = new VersionService;

        $this->assertSame(AiclServiceProvider::VERSION, $service->frameworkVersion());
    }

    public function test_current_delegates_to_framework_version(): void
    {
        $service = new VersionService;

        $this->assertSame($service->frameworkVersion(), $service->current());
    }

    public function test_framework_version_matches_semver_format(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', AiclServiceProvider::VERSION);
    }

    public function test_project_version_returns_unknown_when_changelog_missing(): void
    {
        $path = base_path('CHANGELOG.md');
        $backup = $path.'.bak';
        $exists = file_exists($path);

        if ($exists) {
            rename($path, $backup);
        }

        try {
            $service = new VersionService;
            $this->assertSame('unknown', $service->projectVersion());
        } finally {
            if ($exists) {
                rename($backup, $path);
            }
        }
    }

    public function test_version_badge_view_exists(): void
    {
        /** @phpstan-ignore-next-line */
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
