<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Console\Commands\ScoutImportCommand;
use Aicl\Traits\HasSearchableFields;
use App\Models\User;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ScoutImportCommandTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        $this->artisan('aicl:scout-import --help')
            /** @phpstan-ignore-next-line */
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

        $this->assertInstanceOf(Collection::class, $models);
    }

    public function test_command_get_traits_recursive(): void
    {
        $command = new ScoutImportCommand;

        $reflection = new \ReflectionMethod($command, 'getTraitsRecursive');
        $reflection->setAccessible(true);

        // User model doesn't use HasSearchableFields, so it shouldn't contain it
        $traits = $reflection->invoke($command, User::class);

        $this->assertIsArray($traits);
        $this->assertNotContains(HasSearchableFields::class, $traits);
    }

    public function test_command_discovers_searchable_models_returns_collection(): void
    {
        $command = new ScoutImportCommand;

        $reflection = new \ReflectionMethod($command, 'discoverSearchableModels');
        $models = $reflection->invoke($command);

        // Returns a collection (may be empty if no models use HasSearchableFields)
        $this->assertInstanceOf(Collection::class, $models);
    }
}
