<?php

namespace Aicl\Tests\Feature\Search;

use Aicl\Models\SearchLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_log_can_be_created(): void
    {
        $user = User::factory()->create();

        $log = SearchLog::query()->create([
            'query' => 'test search',
            'user_id' => $user->getKey(),
            'entity_type_filter' => null,
            'results_count' => 5,
            'searched_at' => now(),
        ]);

        $this->assertDatabaseHas('search_logs', [
            'id' => $log->id,
            'query' => 'test search',
            'results_count' => 5,
        ]);
    }

    public function test_search_log_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $log = SearchLog::query()->create([
            'query' => 'test',
            'user_id' => $user->getKey(),
            'results_count' => 0,
            'searched_at' => now(),
        ]);

        $this->assertSame($user->getKey(), $log->user->getKey());
    }

    public function test_search_log_prunable_respects_retention(): void
    {
        config(['aicl.search.analytics.retention_days' => 30]);

        SearchLog::query()->create([
            'query' => 'old search',
            'user_id' => null,
            'results_count' => 0,
            'searched_at' => now()->subDays(31),
        ]);

        SearchLog::query()->create([
            'query' => 'recent search',
            'user_id' => null,
            'results_count' => 0,
            'searched_at' => now()->subDays(5),
        ]);

        $prunable = (new SearchLog)->prunable()->count();

        $this->assertSame(1, $prunable);
    }
}
