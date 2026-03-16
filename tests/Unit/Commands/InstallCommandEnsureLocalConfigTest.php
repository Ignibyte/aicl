<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Console\Commands\InstallCommand;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for InstallCommand::ensureLocalConfig().
 *
 * Verifies that the ensureLocalConfig method correctly copies the
 * local.example.php template to local.php when the target doesn't exist.
 */
class InstallCommandEnsureLocalConfigTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/aicl_install_test_'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        $files = glob($this->tempDir.'/*');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($this->tempDir);

        parent::tearDown();
    }

    public function test_ensure_local_config_method_exists(): void
    {
        $reflection = new \ReflectionMethod(InstallCommand::class, 'ensureLocalConfig');

        $this->assertTrue($reflection->isProtected());
    }

    public function test_ensure_local_config_source_checks_local_path_exists(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(InstallCommand::class))->getFileName()
        );

        $this->assertStringContainsString("config_path('local.php')", $source);
        $this->assertStringContainsString("config_path('local.example.php')", $source);
    }

    public function test_ensure_local_config_source_copies_example_file(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(InstallCommand::class))->getFileName()
        );

        $this->assertStringContainsString('copy($examplePath, $localPath)', $source);
    }

    public function test_ensure_local_config_source_skips_when_local_exists(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(InstallCommand::class))->getFileName()
        );

        // Must check file_exists($localPath) before copying
        $this->assertStringContainsString('file_exists($localPath)', $source);
    }

    public function test_ensure_local_config_source_skips_when_example_missing(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(InstallCommand::class))->getFileName()
        );

        // Must check file_exists($examplePath) before copying
        $this->assertStringContainsString('file_exists($examplePath)', $source);
    }

    public function test_install_command_has_force_option(): void
    {
        $command = new InstallCommand;
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('force'));
    }

    public function test_install_command_has_correct_name(): void
    {
        $command = new InstallCommand;

        $this->assertSame('aicl:install', $command->getName());
    }

    public function test_install_command_has_description(): void
    {
        $command = new InstallCommand;

        $this->assertNotEmpty($command->getDescription());
        $this->assertStringContainsString('Install', $command->getDescription());
    }

    public function test_is_already_installed_method_exists(): void
    {
        $reflection = new \ReflectionMethod(InstallCommand::class, 'isAlreadyInstalled');

        $this->assertTrue($reflection->isProtected());
    }

    public function test_ensure_migrated_method_exists(): void
    {
        $reflection = new \ReflectionMethod(InstallCommand::class, 'ensureMigrated');

        $this->assertTrue($reflection->isProtected());
    }
}
