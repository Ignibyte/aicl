<?php

namespace Aicl\Tests\Feature\Components;

use Tests\TestCase;

class ComponentsCommandTest extends TestCase
{
    public function test_list_command_shows_all_components(): void
    {
        $this->artisan('aicl:components', ['action' => 'list'])
            ->assertExitCode(0)
            ->expectsOutputToContain('stat-card')
            ->expectsOutputToContain('modal')
            ->expectsOutputToContain('badge');
    }

    public function test_list_with_category_filter(): void
    {
        $this->artisan('aicl:components', ['action' => 'list', '--category' => 'metric'])
            ->assertExitCode(0)
            ->expectsOutputToContain('stat-card');
    }

    public function test_show_command_displays_component_details(): void
    {
        $this->artisan('aicl:components', ['action' => 'show', '--tag' => 'stat-card'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Stat Card');
    }

    public function test_show_command_fails_for_unknown_tag(): void
    {
        $this->artisan('aicl:components', ['action' => 'show', '--tag' => 'nonexistent'])
            ->assertExitCode(1);
    }

    public function test_validate_command_checks_all_manifests(): void
    {
        $this->artisan('aicl:components', ['action' => 'validate'])
            ->assertExitCode(0);
    }

    public function test_recommend_command_with_fields(): void
    {
        $this->artisan('aicl:components', [
            'action' => 'recommend',
            '--fields' => 'status:enum,budget:float,name:string',
        ])
            ->assertExitCode(0);
    }

    public function test_tree_command_generates_markdown(): void
    {
        $this->artisan('aicl:components', ['action' => 'tree'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Decision tree written to:');
    }

    public function test_cache_command_creates_cache(): void
    {
        $this->artisan('aicl:components', ['action' => 'cache'])
            ->assertExitCode(0);

        // Cleanup
        $this->artisan('aicl:components', ['action' => 'clear']);
    }

    public function test_clear_command_removes_cache(): void
    {
        // Create cache first
        $this->artisan('aicl:components', ['action' => 'cache']);

        $this->artisan('aicl:components', ['action' => 'clear'])
            ->assertExitCode(0);
    }

    public function test_unknown_action_shows_error(): void
    {
        $this->artisan('aicl:components', ['action' => 'nonexistent'])
            ->assertExitCode(1);
    }
}
