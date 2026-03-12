<?php

namespace Aicl\Console\Commands;

use Aicl\Models\ScheduleHistory;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'schedule:prune-history')]
class PruneScheduleHistoryCommand extends Command
{
    protected $signature = 'schedule:prune-history {--days= : Number of days to retain}';

    protected $description = 'Delete schedule history records older than the configured retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('aicl.scheduler.history_retention_days', 30));

        $deleted = ScheduleHistory::query()
            ->where('started_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} schedule history records older than {$days} days.");

        return self::SUCCESS;
    }
}
