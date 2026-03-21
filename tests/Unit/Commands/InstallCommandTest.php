<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Console\Commands\InstallCommand;
use Illuminate\Console\Command;
use PHPUnit\Framework\TestCase;

class InstallCommandTest extends TestCase
{
    public function test_extends_command(): void
    {
        $this->assertTrue((new \ReflectionClass(InstallCommand::class))->isSubclassOf(Command::class));
    }

    public function test_has_signature(): void
    {
        $command = new InstallCommand;

        $this->assertNotEmpty($command->getName());
        $this->assertEquals('aicl:install', $command->getName());
    }

    public function test_has_description(): void
    {
        $command = new InstallCommand;

        $this->assertNotEmpty($command->getDescription());
    }

    public function test_has_force_option(): void
    {
        $command = new InstallCommand;

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('force'));
    }
}
