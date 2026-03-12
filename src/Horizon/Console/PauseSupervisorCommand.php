<?php

namespace Aicl\Horizon\Console;

use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\MasterSupervisor;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'aicl:horizon:pause-supervisor')]
class PauseSupervisorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:pause-supervisor
                            {name : The name of the supervisor to pause}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pause a supervisor';

    /**
     * Execute the console command.
     *
     * @return int|void
     */
    public function handle(SupervisorRepository $supervisors)
    {
        $processId = optional(collect($supervisors->all())->first(function ($supervisor) {
            return Str::startsWith($supervisor->name, MasterSupervisor::basename())
                    && Str::endsWith($supervisor->name, $this->argument('name'));
        }))->pid;

        if (is_null($processId)) {
            $this->components->error('Failed to find a supervisor with this name');

            return 1;
        }

        $this->components->info("Sending USR2 signal to process: {$processId}");

        if (! posix_kill($processId, SIGUSR2)) {
            $this->components->error("Failed to send USR2 signal to process: {$processId} (".posix_strerror(posix_get_last_error()).')');
        }
    }
}
