<?php

declare(strict_types=1);

namespace Aicl\Horizon\Console;

use Aicl\Horizon\Contracts\MasterSupervisorRepository;
use Aicl\Horizon\MasterSupervisor;
use Aicl\Horizon\ProvisioningPlan;
use Illuminate\Console\Command;
use Predis\Client;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * HorizonCommand.
 *
 * @codeCoverageIgnore Reason: horizon-process -- Requires real Redis workers, pcntl signals, and MasterSupervisor process management
 */
#[AsCommand(name: 'aicl:horizon')]
class HorizonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon {--environment= : The environment name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a master supervisor in the foreground';

    /**
     * Execute the console command.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handle(MasterSupervisorRepository $masters)
    {
        $client = config('database.redis.client');

        if ($client === 'phpredis' && ! extension_loaded('redis')) {
            return $this->components->error('The PHP Redis extension is not installed.');
        }

        if ($client === 'predis' && ! class_exists(Client::class)) {
            return $this->components->error('Predis client is not installed. Run: composer require predis/predis');
        }

        if ($masters->find(MasterSupervisor::name()) !== null) {
            return $this->components->warn('A master supervisor is already running on this machine.');
        }

        $environment = $this->option('environment') ?? config('aicl-horizon.env') ?? config('app.env');

        $master = (new MasterSupervisor($environment))->handleOutputUsing(function ($type, $line) {
            $this->output->write($line);
        });

        ProvisioningPlan::get(MasterSupervisor::name())->deploy($environment);

        $this->components->info('Horizon started successfully.');

        pcntl_async_signals(true);

        pcntl_signal(SIGINT, function () use ($master) {
            $this->output->writeln('');

            $this->components->info('Shutting down.');

            return $master->terminate();
        });

        $master->monitor();
    }
}
