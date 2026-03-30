<?php

declare(strict_types=1);

namespace Aicl\Horizon\Console;

use Aicl\Horizon\Contracts\MasterSupervisorRepository;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @codeCoverageIgnore Horizon process management
 */
#[AsCommand(name: 'aicl:horizon:status')]
class StatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the current status of Horizon';

    /**
     * Execute the console command.
     *
     * @return int
     *
     * @SuppressWarnings(PHPMD.LongVariable)
     * @SuppressWarnings(PHPMD.IfStatementAssignment)
     */
    public function handle(MasterSupervisorRepository $masterSupervisorRepository)
    {
        $masters = $masterSupervisorRepository->all();

        if ($masters === null || $masters === []) {
            $this->components->error('Horizon is inactive.');

            return 2;
        }

        if (collect($masters)->contains(function ($master) {
            return $master->status === 'paused';
        })) {
            $this->components->warn('Horizon is paused.');

            return 1;
        }

        $this->components->info('Horizon is running.');

        return 0;
    }
}
