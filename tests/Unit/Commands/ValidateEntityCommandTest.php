<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Console\Commands\ValidateEntityCommand;
use Illuminate\Console\Command;
use PHPUnit\Framework\TestCase;

class ValidateEntityCommandTest extends TestCase
{
    public function test_extends_command(): void
    {
        $this->assertTrue(is_subclass_of(ValidateEntityCommand::class, Command::class));
    }

    public function test_has_signature(): void
    {
        $command = new ValidateEntityCommand;

        $this->assertEquals('aicl:validate', $command->getName());
    }

    public function test_has_description(): void
    {
        $command = new ValidateEntityCommand;

        $this->assertNotEmpty($command->getDescription());
    }

    public function test_name_argument_is_required(): void
    {
        $command = new ValidateEntityCommand;
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('name'));
        $this->assertTrue($definition->getArgument('name')->isRequired());
    }
}
