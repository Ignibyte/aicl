<?php

declare(strict_types=1);

namespace Aicl\Console\Commands;

use Aicl\Support\RlmBridge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Artisan command to install the AICL package.
 *
 * Performs a full installation sequence: publishes config and brand assets,
 * runs migrations, generates Shield permissions, seeds default roles and
 * the admin user, optionally seeds RLM data, seeds notification channels,
 * publishes Filament assets, clears version cache, and syncs project-level
 * files. Idempotent by default (skips if already installed), use --force
 * to re-run all steps.
 *
 * @see UpgradeCommand  Syncs project-level files on update
 *
 * @codeCoverageIgnore Artisan command
 */
class InstallCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'aicl:install
        {--force : Overwrite existing config files}';

    /**
     * @var string
     */
    protected $description = 'Install the AICL package — publish config, run migrations, seed roles.';

    /**
     * Execute the install command.
     *
     * If already installed and --force is not set, only runs pending migrations
     * and ensures config/local.php exists. Otherwise runs the full installation
     * sequence: config publish, asset publish, migrations, Shield permissions,
     * role seeding, admin user seeding, RLM seeding (if installed), notification
     * channel seeding, Filament assets, version cache clear, and project file sync.
     *
     * @return int Exit code (SUCCESS)
     */
    public function handle(): int
    {
        if (! $this->option('force') && $this->isAlreadyInstalled()) {
            $this->components->info('AICL is already installed. Use --force to re-run.');
            $this->ensureMigrated();
            $this->ensureLocalConfig();

            return self::SUCCESS;
        }

        $this->components->info('Installing AICL...');

        $this->publishAssets();
        $this->runMigrationsAndPermissions();
        $this->ensureLocalConfig();
        $this->seedData();
        $this->finalizeInstall();

        $this->newLine();
        $this->components->info('AICL installed successfully.');
        $this->components->info('Login: admin@aicl.test / password');

        return self::SUCCESS;
    }

    /**
     * Publish config, brand assets, and Filament Shield config.
     */
    protected function publishAssets(): void
    {
        $this->components->task('Publishing AICL config', function (): void {
            $params = ['--provider' => 'Aicl\AiclServiceProvider', '--tag' => 'aicl-config'];
            if ($this->option('force')) {
                $params['--force'] = true;
            }
            $this->callSilently('vendor:publish', $params);
        });

        $this->components->task('Publishing AICL brand assets', function (): void {
            $this->callSilently('vendor:publish', [
                '--provider' => 'Aicl\AiclServiceProvider',
                '--tag' => 'aicl-assets',
                '--force' => true,
            ]);
        });

        $this->components->task('Publishing Filament Shield config', function (): void {
            $params = ['--tag' => 'filament-shield-config'];
            if ($this->option('force')) {
                $params['--force'] = true;
            }
            $this->callSilently('vendor:publish', $params);
        });
    }

    /**
     * Run migrations and generate Shield permissions.
     */
    protected function runMigrationsAndPermissions(): void
    {
        $this->components->task('Running migrations', function (): void {
            $this->callSilently('migrate', ['--force' => true]);
        });

        $this->components->task('Generating Shield permissions', function (): void {
            $this->callSilently('shield:generate', [
                '--all' => true,
                '--panel' => 'admin',
                '--option' => 'permissions',
            ]);
        });

        $this->components->task('Seeding default roles', function (): void {
            $this->callSilently('db:seed', [
                '--class' => 'Aicl\Database\Seeders\RoleSeeder',
                '--force' => true,
            ]);
        });
    }

    /**
     * Seed admin user, RLM data, and notification channels.
     */
    protected function seedData(): void
    {
        $this->components->task('Seeding admin user', function (): void {
            $this->callSilently('db:seed', [
                '--class' => 'Aicl\Database\Seeders\AdminUserSeeder',
                '--force' => true,
            ]);
        });

        $this->seedRlmIfInstalled();

        $this->components->task('Seeding notification channels', function (): void {
            $this->callSilently('db:seed', [
                '--class' => 'Aicl\Database\Seeders\NotificationChannelSeeder',
                '--force' => true,
            ]);
        });
    }

    /**
     * Seed RLM data if the package is installed.
     */
    protected function seedRlmIfInstalled(): void
    {
        if (RlmBridge::installed()) {
            $this->callSilently('rlm:seed');

            return;
        }

        $this->components->info('Skipping RLM seeding (ignibyte/rlm not installed).');
    }

    /**
     * Publish Filament assets, clear caches, and sync project files.
     */
    protected function finalizeInstall(): void
    {
        $this->components->task('Publishing Filament assets', function (): void {
            $this->callSilently('filament:assets');
        });

        $this->components->task('Clearing version cache', function (): void {
            Cache::forget('aicl.version.framework');
            Cache::forget('aicl.version.project');
        });

        $this->components->task('Syncing project files (aicl:upgrade)', function (): void {
            $this->callSilently('aicl:upgrade', ['--force' => true]);
        });
    }

    /**
     * Run any pending migrations on subsequent starts (e.g. after package update).
     */
    protected function ensureMigrated(): void
    {
        $this->components->task('Running pending migrations', function (): void {
            $this->callSilently('migrate', ['--force' => true]);
        });
    }

    /**
     * Copy config/local.example.php to config/local.php if it doesn't exist.
     */
    protected function ensureLocalConfig(): void
    {
        $localPath = config_path('local.php');
        $examplePath = config_path('local.example.php');

        if (file_exists($localPath)) {
            return;
        }

        if (! file_exists($examplePath)) {
            return;
        }

        $this->components->task('Creating config/local.php from template', function () use ($localPath, $examplePath): void {
            copy($examplePath, $localPath);
        });
    }

    /**
     * Check if AICL has already been installed by verifying the super_admin role exists.
     */
    protected function isAlreadyInstalled(): bool
    {
        if (! Schema::hasTable('roles')) {
            return false;
        }

        return Role::where('name', 'super_admin')->exists();
    }
}
