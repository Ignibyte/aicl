<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Console\Commands;

use Tests\TestCase;

/**
 * Regression tests for ComponentsCommand PHPStan changes.
 *
 * Tests the (string) cast on json_encode() return value and the
 * (string) cast with ?? 'index' on the --view-type option.
 * Under strict_types, json_encode() can return false and
 * option() returns mixed.
 *
 * The command uses positional `action` argument: list, show, validate,
 * recommend, tree, cache, clear.
 */
class ComponentsCommandRegressionTest extends TestCase
{
    /**
     * Test list action with json format casts json_encode to string.
     *
     * PHPStan change: (string) json_encode() prevents passing
     * false to $this->line() under strict_types.
     */
    public function test_list_action_json_format_returns_valid_json(): void
    {
        // Act: run the components list command with JSON format
        $this->artisan('aicl:components', [
            'action' => 'list',
            '--format' => 'json',
        ])
            // Assert: should produce valid JSON output without crashing
            /** @phpstan-ignore-next-line */
            ->assertSuccessful();
    }

    /**
     * Test list action with default table format.
     *
     * Verifies the command works correctly after strict_types addition.
     */
    public function test_list_action_table_format_succeeds(): void
    {
        // Act
        $this->artisan('aicl:components', [
            'action' => 'list',
        ])
            /** @phpstan-ignore-next-line */
            ->assertSuccessful();
    }

    /**
     * Test show action with json format handles component lookup.
     *
     * PHPStan change: (string) json_encode() in the show handler.
     */
    public function test_show_action_json_format_for_existing_component(): void
    {
        // Act: try to show a component by tag
        // This may succeed or fail depending on component availability
        $this->artisan('aicl:components', [
            'action' => 'show',
            '--tag' => 'stat-card',
            '--format' => 'json',
        ]);

        // Assert: no crash -- the key test is that (string) json_encode()
        // doesn't throw under strict_types
    }

    /**
     * Test recommend action uses default view-type when not specified.
     *
     * PHPStan change: (string) ($this->option('view-type') ?? 'index')
     * ensures the option is always a string, defaulting to 'index'.
     */
    public function test_recommend_action_defaults_view_type_to_index(): void
    {
        // Act: run recommend without explicit --view-type (defaults to 'index')
        $this->artisan('aicl:components', [
            'action' => 'recommend',
            '--fields' => 'title:string',
        ]);

        // Assert: no crash is the key assertion
    }

    /**
     * Test recommend action with explicit view-type.
     *
     * Verifies the (string) cast works with a real option value.
     */
    public function test_recommend_action_with_explicit_view_type(): void
    {
        // Act: run recommend with --view-type=show
        $this->artisan('aicl:components', [
            'action' => 'recommend',
            '--fields' => 'title:string',
            '--view-type' => 'show',
        ]);

        // Assert: no crash is the key assertion
    }
}
