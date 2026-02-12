<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Console\Commands\ScoutImportCommand;
use Aicl\Traits\HasSearchableFields;
use Tests\TestCase;

class ScoutImportCommandTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        $this->artisan('aicl:scout-import --help')
            ->assertSuccessful();
    }

    public function test_command_has_flush_option(): void
    {
        $reflection = new \ReflectionClass(ScoutImportCommand::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertStringContainsString('--flush', $defaults['signature']);
    }

    public function test_command_discovers_searchable_models(): void
    {
        $command = new ScoutImportCommand;

        $reflection = new \ReflectionMethod($command, 'discoverSearchableModels');
        $reflection->setAccessible(true);

        $models = $reflection->invoke($command);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $models);
    }

    public function test_command_get_traits_recursive(): void
    {
        $command = new ScoutImportCommand;

        $reflection = new \ReflectionMethod($command, 'getTraitsRecursive');
        $reflection->setAccessible(true);

        // User model doesn't use HasSearchableFields, so it shouldn't contain it
        $traits = $reflection->invoke($command, \App\Models\User::class);

        $this->assertIsArray($traits);
        $this->assertNotContains(HasSearchableFields::class, $traits);
    }

    public function test_command_discovers_hub_models_with_searchable_fields(): void
    {
        $command = new ScoutImportCommand;

        $reflection = new \ReflectionMethod($command, 'discoverSearchableModels');
        $models = $reflection->invoke($command);

        // Hub models (RlmFailure, RlmPattern, etc.) use HasSearchableFields
        $this->assertTrue($models->isNotEmpty());
    }
}
