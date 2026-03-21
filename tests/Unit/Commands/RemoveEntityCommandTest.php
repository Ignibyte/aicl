<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Console\Commands\RemoveEntityCommand;
use Illuminate\Console\Command;
use PHPUnit\Framework\TestCase;

class RemoveEntityCommandTest extends TestCase
{
    public function test_extends_command(): void
    {
        $this->assertTrue((new \ReflectionClass(RemoveEntityCommand::class))->isSubclassOf(Command::class));
    }

    public function test_has_signature(): void
    {
        $command = new RemoveEntityCommand;

        $this->assertEquals('aicl:remove-entity', $command->getName());
    }

    public function test_has_description(): void
    {
        $command = new RemoveEntityCommand;

        $this->assertNotEmpty($command->getDescription());
    }

    public function test_name_argument_is_required(): void
    {
        $command = new RemoveEntityCommand;
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('name'));
        $this->assertTrue($definition->getArgument('name')->isRequired());
    }

    public function test_has_dry_run_option(): void
    {
        $command = new RemoveEntityCommand;
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('dry-run'));
    }

    public function test_has_force_option(): void
    {
        $command = new RemoveEntityCommand;
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('force'));
    }

    public function test_defines_discovery_methods(): void
    {
        $reflection = new \ReflectionClass(RemoveEntityCommand::class);

        $this->assertTrue($reflection->hasMethod('discoverEntityFiles'));
        $this->assertTrue($reflection->hasMethod('discoverSharedFileCleanups'));
    }

    public function test_defines_execution_methods(): void
    {
        $reflection = new \ReflectionClass(RemoveEntityCommand::class);

        $this->assertTrue($reflection->hasMethod('executeRemoval'));
        $this->assertTrue($reflection->hasMethod('executeSharedFileCleanups'));
    }

    public function test_defines_shared_file_scan_methods(): void
    {
        $reflection = new \ReflectionClass(RemoveEntityCommand::class);

        $this->assertTrue($reflection->hasMethod('scanAppServiceProvider'));
        $this->assertTrue($reflection->hasMethod('scanApiRoutes'));
        $this->assertTrue($reflection->hasMethod('scanChannelsFile'));
        $this->assertTrue($reflection->hasMethod('scanDatabaseSeeder'));
    }
}
