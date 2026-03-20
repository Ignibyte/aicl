<?php

namespace Aicl\Horizon\Console;

use Aicl\Horizon\Contracts\MasterSupervisorRepository;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'aicl:horizon:list')]
class ListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all of the deployed machines';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(MasterSupervisorRepository $masters)
    {
        $masters = $masters->all();

        if (empty($masters)) {
            return $this->components->info('No machines are running.');
        }

        $this->output->writeln('');

        /** @var array<int, \stdClass> $masters */
        $this->table([
            'Name', 'PID', 'Supervisors', 'Status',
        ], collect($masters)->map(function (\stdClass $master) {
            /** @var array<int, string>|null $supervisors */
            $supervisors = $master->supervisors;

            return [
                $master->name,
                $master->pid,
                $supervisors ? collect($supervisors)->map(function (string $supervisor) {
                    return explode(':', $supervisor, 2)[1];
                })->implode(', ') : 'None',
                $master->status,
            ];
        })->all());

        $this->output->writeln('');
    }
}
