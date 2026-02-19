<?php

namespace Aicl\Tests\Feature\Filament;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Filament\Pages\Changelog;
use Aicl\Services\VersionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChangelogPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\SettingsSeeder']);

        Event::fake([
            EntityCreated::class,
            EntityUpdated::class,
            EntityDeleted::class,
        ]);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('viewer');
    }

    public function test_changelog_page_accessible_by_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/changelog');

        $response->assertOk();
    }

    public function test_changelog_page_renders_framework_changelog(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/changelog');

        $response->assertOk();
        $response->assertSee('AICL Framework Changelog', false);
    }

    public function test_changelog_page_shows_framework_tab(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/changelog');

        $response->assertOk();
        $response->assertSee('Framework', false);
    }

    public function test_changelog_page_shows_version_in_title(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/changelog');

        $response->assertOk();
        $response->assertSee('Changelog', false);
    }

    public function test_changelog_page_not_accessible_by_viewer(): void
    {
        $response = $this->actingAs($this->viewer)->get('/admin/changelog');

        $this->assertFilamentAccessDenied($response);
    }

    public function test_changelog_page_class_structure(): void
    {
        $page = new Changelog;

        $this->assertSame('aicl::filament.pages.changelog', (new \ReflectionProperty($page, 'view'))->getValue($page));

        $html = $page->getFrameworkChangelogHtml();
        $this->assertStringContainsString('AICL Framework Changelog', $html);
    }

    public function test_changelog_page_can_access_returns_false_for_guests(): void
    {
        auth()->logout();

        $this->assertFalse(Changelog::canAccess());
    }

    public function test_framework_changelog_html_returns_no_changelog_when_missing(): void
    {
        $page = new Changelog;

        // Move both possible changelog locations to test missing file handling
        $devPath = base_path('CHANGELOG_FRAMEWORK.md');
        $vendorPath = base_path('vendor/aicl/aicl/CHANGELOG_FRAMEWORK.md');
        $devBackup = $devPath.'.bak';
        $vendorBackup = $vendorPath.'.bak';
        $devExists = file_exists($devPath);
        $vendorExists = file_exists($vendorPath);

        if ($devExists) {
            rename($devPath, $devBackup);
        }
        if ($vendorExists) {
            rename($vendorPath, $vendorBackup);
        }

        try {
            $html = $page->getFrameworkChangelogHtml();
            $this->assertStringContainsString('No changelog found', $html);
        } finally {
            if ($devExists) {
                rename($devBackup, $devPath);
            }
            if ($vendorExists) {
                rename($vendorBackup, $vendorPath);
            }
        }
    }

    public function test_project_changelog_html_returns_no_changelog_when_missing(): void
    {
        $page = new Changelog;

        // Project changelog typically doesn't exist in test env
        $path = base_path('CHANGELOG.md');
        $backup = $path.'.bak';
        $exists = file_exists($path);

        if ($exists) {
            rename($path, $backup);
        }

        try {
            $html = $page->getProjectChangelogHtml();
            $this->assertStringContainsString('No changelog found', $html);
        } finally {
            if ($exists) {
                rename($backup, $path);
            }
        }
    }

    public function test_has_framework_changelog_returns_true_when_file_exists(): void
    {
        $page = new Changelog;
        $this->assertTrue($page->hasFrameworkChangelog());
    }

    public function test_has_project_changelog_returns_correct_value(): void
    {
        $page = new Changelog;
        $expected = file_exists(base_path('CHANGELOG.md'));
        $this->assertSame($expected, $page->hasProjectChangelog());
    }

    public function test_version_service_framework_version(): void
    {
        $service = app(VersionService::class);
        $version = $service->frameworkVersion();
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
    }

    public function test_version_service_current_delegates_to_framework_version(): void
    {
        $service = app(VersionService::class);
        $this->assertSame($service->frameworkVersion(), $service->current());
    }

    public function test_version_service_project_version_returns_unknown_when_missing(): void
    {
        $path = base_path('CHANGELOG.md');
        $backup = $path.'.bak';
        $exists = file_exists($path);

        if ($exists) {
            rename($path, $backup);
        }

        try {
            // Clear cached version
            cache()->forget('aicl.version.project');

            $service = new VersionService;
            $this->assertSame('unknown', $service->projectVersion());
        } finally {
            if ($exists) {
                rename($backup, $path);
            }
        }
    }

    public function test_title_shows_both_versions_when_project_changelog_exists(): void
    {
        // Create a temporary project changelog
        $path = base_path('CHANGELOG.md');
        $existed = file_exists($path);
        $originalContent = $existed ? file_get_contents($path) : null;

        file_put_contents($path, "# Changelog\n\n## [1.0.0] - 2026-02-17\n\n- Initial release\n");
        cache()->forget('aicl.version.project');

        try {
            $page = new Changelog;
            $title = $page->getTitle();
            $this->assertStringContainsString('Framework v', $title);
            $this->assertStringContainsString('Project v1.0.0', $title);
        } finally {
            if ($existed) {
                file_put_contents($path, $originalContent);
            } else {
                @unlink($path);
            }
            cache()->forget('aicl.version.project');
        }
    }

    public function test_title_only_shows_framework_version_when_no_project_changelog(): void
    {
        $path = base_path('CHANGELOG.md');
        $backup = $path.'.bak';
        $exists = file_exists($path);

        if ($exists) {
            rename($path, $backup);
        }
        cache()->forget('aicl.version.project');

        try {
            $page = new Changelog;
            $title = $page->getTitle();
            $this->assertStringContainsString('Framework v', $title);
            $this->assertStringNotContainsString('Project v', $title);
        } finally {
            if ($exists) {
                rename($backup, $path);
            }
            cache()->forget('aicl.version.project');
        }
    }

    public function test_deprecated_get_changelog_html_delegates_to_framework(): void
    {
        $page = new Changelog;
        $this->assertSame($page->getFrameworkChangelogHtml(), $page->getChangelogHtml());
    }
}
