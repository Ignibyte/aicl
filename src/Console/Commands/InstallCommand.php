<?php

namespace Aicl\Console\Commands;

use Aicl\Support\RlmBridge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

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

    public function handle(): int
    {
        if (! $this->option('force') && $this->isAlreadyInstalled()) {
            $this->components->info('AICL is already installed. Use --force to re-run.');
            $this->ensureMigrated();
            $this->ensureSettingsSeeded();

            return self::SUCCESS;
        }

        $this->components->info('Installing AICL...');

        // Publish config
        $this->components->task('Publishing AICL config', function (): void {
            $params = ['--provider' => 'Aicl\AiclServiceProvider', '--tag' => 'aicl-config'];
            if ($this->option('force')) {
                $params['--force'] = true;
            }
            $this->callSilently('vendor:publish', $params);
        });

        // Publish brand assets (logo, images)
        $this->components->task('Publishing AICL brand assets', function (): void {
            $this->callSilently('vendor:publish', [
                '--provider' => 'Aicl\AiclServiceProvider',
                '--tag' => 'aicl-assets',
                '--force' => true,
            ]);
        });

        // Publish Filament Shield config
        $this->components->task('Publishing Filament Shield config', function (): void {
            $params = ['--tag' => 'filament-shield-config'];
            if ($this->option('force')) {
                $params['--force'] = true;
            }
            $this->callSilently('vendor:publish', $params);
        });

        // Run migrations
        $this->components->task('Running migrations', function (): void {
            $this->callSilently('migrate', ['--force' => true]);
        });

        // Generate Shield permissions
        $this->components->task('Generating Shield permissions', function (): void {
            $this->callSilently('shield:generate', [
                '--all' => true,
                '--panel' => 'admin',
                '--option' => 'permissions',
            ]);
        });

        // Seed roles
        $this->components->task('Seeding default roles', function (): void {
            $this->callSilently('db:seed', [
                '--class' => 'Aicl\Database\Seeders\RoleSeeder',
                '--force' => true,
            ]);
        });

        // Seed default settings
        $this->components->task('Seeding default settings', function (): void {
            $this->callSilently('db:seed', [
                '--class' => 'Aicl\Database\Seeders\SettingsSeeder',
                '--force' => true,
            ]);
        });

        // Seed admin user (required before RLM seeding — RLM uses owner_id => 1)
        $this->components->task('Seeding admin user', function (): void {
            $this->callSilently('db:seed', [
                '--class' => 'Aicl\Database\Seeders\AdminUserSeeder',
                '--force' => true,
            ]);
        });

        if (RlmBridge::installed()) {
            $this->callSilently('rlm:seed');
        } else {
            $this->components->info('Skipping RLM seeding (ignibyte/rlm not installed).');
        }

        // Seed notification channels
        $this->components->task('Seeding notification channels', function (): void {
            $this->callSilently('db:seed', [
                '--class' => 'Aicl\Database\Seeders\NotificationChannelSeeder',
                '--force' => true,
            ]);
        });

        // Publish Filament assets
        $this->components->task('Publishing Filament assets', function (): void {
            $this->callSilently('filament:assets');
        });

        // Clear cached version strings so they reflect the newly installed version
        $this->components->task('Clearing version cache', function (): void {
            \Illuminate\Support\Facades\Cache::forget('aicl.version.framework');
            \Illuminate\Support\Facades\Cache::forget('aicl.version.project');
        });

        // Sync project-level files (agents, config stubs, planning docs)
        $this->components->task('Syncing project files (aicl:upgrade)', function (): void {
            $this->callSilently('aicl:upgrade', ['--force' => true]);
        });

        $this->newLine();
        $this->components->info('AICL installed successfully.');
        $this->components->info('Login: admin@aicl.test / password');

        return self::SUCCESS;
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
     * Ensure settings are seeded even on subsequent runs (idempotent).
     */
    protected function ensureSettingsSeeded(): void
    {
        if (Schema::hasTable('settings') && \Illuminate\Support\Facades\DB::table('settings')->count() === 0) {
            $this->components->task('Seeding missing settings', function (): void {
                $this->callSilently('db:seed', [
                    '--class' => 'Aicl\Database\Seeders\SettingsSeeder',
                    '--force' => true,
                ]);
            });
        }
    }

    protected function isAlreadyInstalled(): bool
    {
        if (! Schema::hasTable('roles')) {
            return false;
        }

        return \Spatie\Permission\Models\Role::where('name', 'super_admin')->exists();
    }
}
