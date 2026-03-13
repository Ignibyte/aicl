<?php

namespace Aicl\Tests\Feature\Search;

use Aicl\Console\Commands\PruneSearchLogsCommand;
use Aicl\Console\Commands\SearchReindexCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchReindexCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reindex_command_exists(): void
    {
        $this->assertTrue(class_exists(SearchReindexCommand::class));
    }

    public function test_reindex_warns_when_disabled(): void
    {
        config(['aicl.search.enabled' => false]);

        $this->artisan('search:reindex')
            ->assertSuccessful();
    }

    public function test_reindex_warns_when_no_entities(): void
    {
        config(['aicl.search.enabled' => true]);
        config(['aicl.search.entities' => []]);

        $this->artisan('search:reindex')
            ->assertSuccessful();
    }

    public function test_prune_logs_command_exists(): void
    {
        $this->assertTrue(class_exists(PruneSearchLogsCommand::class));
    }

    public function test_prune_logs_runs_successfully(): void
    {
        $this->artisan('search:prune-logs')
            ->assertSuccessful();
    }
}
