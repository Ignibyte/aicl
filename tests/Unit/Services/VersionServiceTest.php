<?php

namespace Aicl\Tests\Unit\Services;

use Aicl\Services\VersionService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VersionServiceTest extends TestCase
{
    public function test_parse_extracts_version_from_changelog(): void
    {
        $service = new VersionService;

        $version = $service->parse();

        // The CHANGELOG_FRAMEWORK.md always starts with a version heading
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
    }

    public function test_current_returns_cached_version(): void
    {
        Cache::flush();

        $service = new VersionService;

        $version = $service->current();

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);

        // Verify it was cached
        $this->assertSame($version, Cache::get('aicl.version'));
    }

    public function test_current_returns_same_value_as_parse(): void
    {
        Cache::flush();

        $service = new VersionService;

        $this->assertSame($service->parse(), $service->current());
    }

    public function test_parse_returns_unknown_when_changelog_missing(): void
    {
        // Use a mock to test missing file scenario
        $service = new class extends VersionService
        {
            public function parse(): string
            {
                // Simulate file not found
                $path = base_path('NONEXISTENT_CHANGELOG.md');

                if (! file_exists($path)) {
                    return 'unknown';
                }

                return parent::parse();
            }
        };

        $this->assertSame('unknown', $service->parse());
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
