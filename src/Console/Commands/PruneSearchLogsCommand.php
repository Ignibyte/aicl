<?php

namespace Aicl\Console\Commands;

use Aicl\Models\SearchLog;
use Illuminate\Console\Command;

class PruneSearchLogsCommand extends Command
{
    protected $signature = 'search:prune-logs';

    protected $description = 'Delete search log entries older than the configured retention period.';

    public function handle(): int
    {
        $retentionDays = (int) config('aicl.search.analytics.retention_days', 90);

        $deleted = SearchLog::query()
            ->where('searched_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->components->info("Pruned {$deleted} search log(s) older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
