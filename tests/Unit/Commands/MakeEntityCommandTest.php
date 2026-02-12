<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Console\Commands\MakeEntityCommand;
use Illuminate\Console\Command;
use PHPUnit\Framework\TestCase;

class MakeEntityCommandTest extends TestCase
{
    public function test_extends_command(): void
    {
        $this->assertTrue(is_subclass_of(MakeEntityCommand::class, Command::class));
    }

    public function test_has_signature(): void
    {
        $command = new MakeEntityCommand;

        $this->assertEquals('aicl:make-entity', $command->getName());
    }

    public function test_has_description(): void
    {
        $command = new MakeEntityCommand;

        $this->assertNotEmpty($command->getDescription());
    }

    public function test_name_argument_is_optional(): void
    {
        $command = new MakeEntityCommand;
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('name'));
        $this->assertFalse($definition->getArgument('name')->isRequired());
    }

    public function test_defines_select_traits_method(): void
    {
        $this->assertTrue(method_exists(MakeEntityCommand::class, 'selectTraits'));
    }

    public function test_defines_generate_model_method(): void
    {
        $reflection = new \ReflectionClass(MakeEntityCommand::class);

        $this->assertTrue($reflection->hasMethod('generateModel'));
    }

    public function test_defines_generate_migration_method(): void
    {
        $reflection = new \ReflectionClass(MakeEntityCommand::class);

        $this->assertTrue($reflection->hasMethod('generateMigration'));
    }

    public function test_defines_generate_factory_method(): void
    {
        $reflection = new \ReflectionClass(MakeEntityCommand::class);

        $this->assertTrue($reflection->hasMethod('generateFactory'));
    }

    public function test_defines_generate_policy_method(): void
    {
        $reflection = new \ReflectionClass(MakeEntityCommand::class);

        $this->assertTrue($reflection->hasMethod('generatePolicy'));
    }

    public function test_defines_generate_observer_method(): void
    {
        $reflection = new \ReflectionClass(MakeEntityCommand::class);

        $this->assertTrue($reflection->hasMethod('generateObserver'));
    }

    public function test_defines_generate_test_method(): void
    {
        $reflection = new \ReflectionClass(MakeEntityCommand::class);

        $this->assertTrue($reflection->hasMethod('generateTest'));
    }
}
