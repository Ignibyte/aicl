<?php

declare(strict_types=1);

namespace Aicl\Horizon\Console;

use Aicl\Horizon\Contracts\SupervisorRepository;
use Illuminate\Console\Command;
use stdClass;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @codeCoverageIgnore Horizon process management
 */
#[AsCommand(name: 'aicl:horizon:supervisors')]
class SupervisorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:supervisors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all of the supervisors';

    /**
     * Execute the console command.
     */
    public function handle(SupervisorRepository $supervisors)
    {
        $supervisors = $supervisors->all();

        if ($supervisors === null || $supervisors === []) {
            return $this->components->info('No supervisors are running.');
        }

        $this->output->writeln('');

        /** @var array<int, stdClass> $supervisors */
        $this->table([
            'Name', 'PID', 'Status', 'Workers', 'Balancing',
        ], collect($supervisors)->map(function (stdClass $supervisor) {
            /** @var array<string, int> $processes */
            $processes = $supervisor->processes;

            return [
                $supervisor->name,
                $supervisor->pid,
                $supervisor->status,
                collect($processes)->map(function (int $count, string $queue) {
                    return $queue.' ('.$count.')';
                })->implode(', '),
                $supervisor->options['balance'],
            ];
        })->all());

        $this->output->writeln('');
    }
}
