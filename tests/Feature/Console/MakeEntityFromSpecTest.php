<?php

namespace Aicl\Tests\Feature\Console;

use Illuminate\Console\Command;
use Tests\TestCase;

class MakeEntityFromSpecTest extends TestCase
{
    /**
     * Temp files created during tests, cleaned up in tearDown.
     *
     * @var array<int, string>
     */
    protected array $tempFiles = [];

    /**
     * Entity names generated during this test, cleaned up in tearDown.
     *
     * @var array<int, string>
     */
    protected array $generatedEntities = [];

    protected function tearDown(): void
    {
        // Clean up any generated entities
        foreach (array_unique($this->generatedEntities) as $name) {
            $this->artisan('aicl:remove-entity', [
                'name' => $name,
                '--force' => true,
                '--no-interaction' => true,
            ]);
        }

        $this->generatedEntities = [];

        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        $this->tempFiles = [];

        parent::tearDown();
    }

    /**
     * Write a spec file to a temp path and track for cleanup.
     */
    protected function writeSpecFile(string $content, ?string $filename = null): string
    {
        $dir = sys_get_temp_dir().'/aicl_spec_test_'.getmypid();

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $filename ?? 'TestEntity.entity.md';
        $path = $dir.'/'.$filename;
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    protected function simpleSpec(): string
    {
        return <<<'MD'
# Gadget

A simple gadget entity for testing spec-based generation.

---

## Fields

| Name | Type | Modifiers | Description |
|------|------|-----------|-------------|
| name | string | | Gadget name |
| description | text | | Gadget description |
| price | float | | Unit price |
| is_available | boolean | | Availability flag |
MD;
    }

    // ========================================================================
    // 1. --from-spec with entity name uses specs/{Name}.entity.md
    // ========================================================================

    public function test_from_spec_with_entity_name_uses_specs_directory(): void
    {
        // This test verifies the path resolution logic: when --from-spec is used
        // with an entity name, it looks for specs/{Name}.entity.md
        // Since that file likely doesn't exist in the test env, we expect a spec file error
        $this->artisan('aicl:make-entity', [
            'name' => 'NonexistentSpecEntity',
            '--from-spec' => true,
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Spec file error');
    }

    // ========================================================================
    // 2. --from-spec with --spec-path=path uses explicit path
    // ========================================================================

    public function test_from_spec_with_spec_path_uses_explicit_path(): void
    {
        $specPath = $this->writeSpecFile($this->simpleSpec(), 'Gadget.entity.md');
        $this->generatedEntities[] = 'Gadget';

        $this->artisan('aicl:make-entity', [
            '--from-spec' => true,
            '--spec-path' => $specPath,
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::SUCCESS);

        // Verify that the entity was generated from the spec
        $this->assertFileExists(app_path('Models/Gadget.php'));
    }

    // ========================================================================
    // 3. --from-spec with --fields flag shows error and fails
    // ========================================================================

    public function test_from_spec_with_fields_flag_shows_error(): void
    {
        $specPath = $this->writeSpecFile($this->simpleSpec(), 'Gadget.entity.md');

        $this->artisan('aicl:make-entity', [
            'name' => 'Gadget',
            '--from-spec' => true,
            '--spec-path' => $specPath,
            '--fields' => 'name:string',
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Cannot use --fields with --from-spec');
    }

    // ========================================================================
    // 4. --from-spec with --states flag shows error and fails
    // ========================================================================

    public function test_from_spec_with_states_flag_shows_error(): void
    {
        $specPath = $this->writeSpecFile($this->simpleSpec(), 'Gadget.entity.md');

        $this->artisan('aicl:make-entity', [
            'name' => 'Gadget',
            '--from-spec' => true,
            '--spec-path' => $specPath,
            '--states' => 'draft,active',
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Cannot use --states with --from-spec');
    }

    // ========================================================================
    // 5. --from-spec with --relationships flag shows error and fails
    // ========================================================================

    public function test_from_spec_with_relationships_flag_shows_error(): void
    {
        $specPath = $this->writeSpecFile($this->simpleSpec(), 'Gadget.entity.md');

        $this->artisan('aicl:make-entity', [
            'name' => 'Gadget',
            '--from-spec' => true,
            '--spec-path' => $specPath,
            '--relationships' => 'items:hasMany:Item',
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Cannot use --relationships with --from-spec');
    }

    // ========================================================================
    // 6. --from-spec without entity name and without --spec-path shows error
    // ========================================================================

    public function test_from_spec_without_name_and_without_spec_path_shows_error(): void
    {
        $this->artisan('aicl:make-entity', [
            '--from-spec' => true,
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Provide the entity name or use --spec-path');
    }

    // ========================================================================
    // Additional: --spec-path triggers from-spec mode even without --from-spec
    // ========================================================================

    public function test_spec_path_alone_triggers_from_spec_mode(): void
    {
        $specPath = $this->writeSpecFile($this->simpleSpec(), 'Gadget.entity.md');
        $this->generatedEntities[] = 'Gadget';

        // Using just --spec-path without --from-spec should still work
        // because handleFromSpec is triggered when spec-path is not null
        $this->artisan('aicl:make-entity', [
            '--spec-path' => $specPath,
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::SUCCESS);

        $this->assertFileExists(app_path('Models/Gadget.php'));
    }

    // ========================================================================
    // Additional: --from-spec with --base flag shows error
    // ========================================================================

    public function test_from_spec_with_base_flag_shows_error(): void
    {
        $specPath = $this->writeSpecFile($this->simpleSpec(), 'Gadget.entity.md');

        $this->artisan('aicl:make-entity', [
            'name' => 'Gadget',
            '--from-spec' => true,
            '--spec-path' => $specPath,
            '--base' => 'App\Models\Base\BaseModel',
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Cannot use --base with --from-spec');
    }

    // ========================================================================
    // Additional: --from-spec with nonexistent spec-path shows error
    // ========================================================================

    public function test_from_spec_with_nonexistent_spec_path_shows_error(): void
    {
        $this->artisan('aicl:make-entity', [
            '--from-spec' => true,
            '--spec-path' => '/tmp/totally_nonexistent_spec_file.entity.md',
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Spec file error');
    }

    // ========================================================================
    // Additional: --from-spec generates model with spec-defined fields
    // ========================================================================

    public function test_from_spec_generates_model_with_spec_fields(): void
    {
        $specPath = $this->writeSpecFile($this->simpleSpec(), 'Gadget.entity.md');
        $this->generatedEntities[] = 'Gadget';

        $this->artisan('aicl:make-entity', [
            '--spec-path' => $specPath,
            '--no-interaction' => true,
        ])->assertExitCode(Command::SUCCESS);

        $modelContent = file_get_contents(app_path('Models/Gadget.php'));

        // Verify spec fields are in the generated model
        $this->assertStringContainsString("'name'", $modelContent);
        $this->assertStringContainsString("'description'", $modelContent);
        $this->assertStringContainsString("'price'", $modelContent);
        $this->assertStringContainsString("'is_available'", $modelContent);
    }
}
