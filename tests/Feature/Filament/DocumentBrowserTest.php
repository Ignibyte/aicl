<?php

namespace Aicl\Tests\Feature\Filament;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Filament\Pages\DocumentBrowser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DocumentBrowserTest extends TestCase
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

    public function test_document_browser_accessible_by_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/documents');

        $response->assertOk();
    }

    public function test_document_browser_renders_file_list(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/documents');

        $response->assertOk();
        // The architecture directory should have .md files
        $response->assertSee('Architecture', false);
    }

    public function test_document_browser_renders_selected_file(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/admin/documents?file=.claude/architecture/README.md');

        $response->assertOk();
    }

    public function test_document_browser_not_accessible_by_viewer(): void
    {
        $response = $this->actingAs($this->viewer)->get('/admin/documents');

        $this->assertFilamentAccessDenied($response);
    }

    public function test_document_browser_page_class_structure(): void
    {
        $page = new DocumentBrowser;

        $this->assertSame('aicl::filament.pages.document-browser', (new \ReflectionProperty($page, 'view'))->getValue($page));
    }

    public function test_get_doc_paths_returns_array(): void
    {
        $page = new DocumentBrowser;
        $paths = $page->getDocPaths();

        $this->assertIsArray($paths);
        $this->assertNotEmpty($paths);
        $this->assertArrayHasKey('label', $paths[0]);
        $this->assertArrayHasKey('path', $paths[0]);
    }

    public function test_get_files_returns_markdown_files(): void
    {
        $page = new DocumentBrowser;
        $files = $page->getFiles();

        $this->assertIsArray($files);

        // The architecture directory should have at least one .md file
        if (! empty($files)) {
            $this->assertArrayHasKey('name', $files[0]);
            $this->assertArrayHasKey('path', $files[0]);
            $this->assertArrayHasKey('relative', $files[0]);
            $this->assertArrayHasKey('group', $files[0]);
        }
    }

    public function test_get_document_html_returns_empty_when_no_file_selected(): void
    {
        $page = new DocumentBrowser;
        $page->file = null;

        $this->assertSame('', $page->getDocumentHtml());
    }

    public function test_get_document_html_blocks_path_traversal(): void
    {
        $page = new DocumentBrowser;
        $page->file = '../../etc/passwd';

        $html = $page->getDocumentHtml();

        $this->assertStringContainsString('Access denied', $html);
    }

    public function test_is_allowed_path_validates_configured_directories(): void
    {
        $page = new DocumentBrowser;
        $reflection = new \ReflectionMethod($page, 'isAllowedPath');

        // A file within the configured path should be allowed
        $archDir = base_path('.claude/architecture');
        if (is_dir($archDir)) {
            $mdFiles = glob($archDir.'/*.md');
            if (! empty($mdFiles)) {
                $this->assertTrue($reflection->invoke($page, $mdFiles[0]));
            }
        }

        // A file outside configured paths should not be allowed
        $this->assertFalse($reflection->invoke($page, base_path('.env')));
    }

    public function test_can_access_returns_false_for_guests(): void
    {
        auth()->logout();

        $this->assertFalse(DocumentBrowser::canAccess());
    }

    public function test_docs_config_key_exists(): void
    {
        $config = require base_path('packages/aicl/config/aicl.php');

        $this->assertArrayHasKey('docs', $config);
        $this->assertArrayHasKey('paths', $config['docs']);
    }
}
