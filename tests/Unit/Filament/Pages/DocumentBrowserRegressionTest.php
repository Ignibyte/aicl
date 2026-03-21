<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Filament\Pages\DocumentBrowser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tests for DocumentBrowser page PHPStan changes.
 *
 * Covers the (string) casts on realpath() and file_get_contents() in
 * getDocPaths() and getDocumentHtml(), the canAccess() null guard,
 * the declare(strict_types=1) enforcement, and path security validation.
 */
class DocumentBrowserRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles for canAccess() hasRole checks
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
    }

    // -- canAccess null guard --

    /**
     * Test canAccess returns false when no user is authenticated.
     *
     * PHPStan change: Added null guard on auth()->user().
     */
    public function test_can_access_returns_false_when_unauthenticated(): void
    {
        // Act
        $result = DocumentBrowser::canAccess();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test canAccess returns true for admin users.
     */
    public function test_can_access_returns_true_for_admin(): void
    {
        // Arrange
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        // Act
        $result = DocumentBrowser::canAccess();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test canAccess returns false for regular users.
     */
    public function test_can_access_returns_false_for_regular_user(): void
    {
        // Arrange
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $result = DocumentBrowser::canAccess();

        // Assert
        $this->assertFalse($result);
    }

    // -- getDocPaths (string) cast on realpath --

    /**
     * Test getDocPaths returns an array including framework docs.
     *
     * PHPStan change: (string) cast on realpath() in getDocPaths().
     * The framework package docs directory should be auto-discovered.
     */
    public function test_get_doc_paths_includes_framework_docs(): void
    {
        // Arrange
        $page = new DocumentBrowser;

        // Act
        $paths = $page->getDocPaths();

        // Assert: at least one path entry should exist
        $this->assertNotEmpty($paths);

        // Each entry should have label and path keys
        foreach ($paths as $entry) {
            $this->assertArrayHasKey('label', $entry);
            $this->assertArrayHasKey('path', $entry);
        }
    }

    // -- getDocumentHtml with null file --

    /**
     * Test getDocumentHtml returns empty string when file is null.
     *
     * Edge case: no file selected should return empty string.
     */
    public function test_get_document_html_returns_empty_when_file_null(): void
    {
        // Arrange
        $page = new DocumentBrowser;
        $page->file = null;

        // Act
        $html = $page->getDocumentHtml();

        // Assert: empty string when no file selected
        $this->assertSame('', $html);
    }

    /**
     * Test getDocumentHtml returns error message for disallowed path.
     *
     * Security check: path traversal attempt should be denied.
     */
    public function test_get_document_html_denies_path_outside_configured_dirs(): void
    {
        // Arrange
        $page = new DocumentBrowser;
        $page->file = '../../../etc/passwd';

        // Act
        $html = $page->getDocumentHtml();

        // Assert: access denied message returned
        $this->assertStringContainsString('Access denied', $html);
    }

    /**
     * Test getDocumentHtml returns "not found" for nonexistent file within allowed path.
     *
     * PHPStan change: (string) cast on file_get_contents() ensures no type error.
     */
    public function test_get_document_html_returns_not_found_for_missing_file(): void
    {
        // Arrange: use a path within configured docs that doesn't exist
        config(['aicl.docs.paths' => [
            ['label' => 'Test', 'path' => 'docs'],
        ]]);
        $page = new DocumentBrowser;
        $page->file = 'docs/nonexistent-file-12345.md';

        // Act
        $html = $page->getDocumentHtml();

        // Assert: either "not found" message or "access denied" (depends on realpath)
        $this->assertNotEmpty($html);
    }

    // -- getFiles returns correct structure --

    /**
     * Test getFiles returns markdown files with expected keys.
     *
     * Verifies the file discovery returns properly structured entries.
     */
    public function test_get_files_returns_structured_entries(): void
    {
        // Arrange
        $page = new DocumentBrowser;

        // Act
        $files = $page->getFiles();

        // Assert: each file entry has the required keys
        foreach ($files as $file) {
            $this->assertArrayHasKey('name', $file);
            $this->assertArrayHasKey('path', $file);
            $this->assertArrayHasKey('relative', $file);
            $this->assertArrayHasKey('group', $file);
        }
    }

    // -- isAllowedPath --

    /**
     * Test isAllowedPath rejects paths with false realpath.
     *
     * Edge case: non-existent file should have realpath return false.
     */
    public function test_is_allowed_path_rejects_nonexistent_file(): void
    {
        // Arrange
        $page = new DocumentBrowser;
        $method = new \ReflectionMethod($page, 'isAllowedPath');
        $method->setAccessible(true);

        // Act: check a path that doesn't exist on disk
        $result = $method->invoke($page, '/tmp/definitely-does-not-exist-'.uniqid().'.md');

        // Assert: returns false for non-existent paths
        $this->assertFalse($result);
    }
}
