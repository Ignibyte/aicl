<?php

declare(strict_types=1);

namespace Aicl\Horizon\Console;

use Aicl\Horizon\MasterSupervisor;
use Aicl\Horizon\ProvisioningPlan;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * TimeoutCommand.
 *
 * @codeCoverageIgnore Reason: horizon-process -- Requires real Horizon provisioning plan and environment config
 */
#[AsCommand(name: 'aicl:horizon:timeout')]
class TimeoutCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:timeout {environment=production : The environment name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the maximum timeout for the given environment';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $plan = ProvisioningPlan::get(MasterSupervisor::name())->plan;

        $environment = $this->argument('environment');

        /** @var array<string, mixed> $environmentPlan */
        $environmentPlan = $plan[$this->argument('environment')] ?? [];

        $timeout = collect($environmentPlan)->max('timeout') ?? 60;

        $this->components->info('Maximum timeout for '.$environment.' environment: '.$timeout.' seconds.');
    }
}
