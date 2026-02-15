<?php

namespace Aicl\Console\Commands;

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

        // Publish activity log migrations
        $this->components->task('Publishing activity log migrations', function (): void {
            $this->callSilently('vendor:publish', [
                '--provider' => 'Spatie\Activitylog\ActivitylogServiceProvider',
                '--tag' => 'activitylog-migrations',
            ]);
        });

        // Publish media library migrations
        $this->components->task('Publishing media library migrations', function (): void {
            $this->callSilently('vendor:publish', [
                '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
                '--tag' => 'medialibrary-migrations',
            ]);
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

        // Seed RLM base failures
        $this->components->task('Seeding RLM base failures', function (): void {
            $this->callSilently('db:seed', [
                '--class' => 'Aicl\Database\Seeders\BaseFailureSeeder',
                '--force' => true,
            ]);
        });

        // Seed RLM lessons (curated, linked to base failures)
        $this->components->task('Seeding RLM lessons', function (): void {
            $this->callSilently('db:seed', [
                '--class' => 'Aicl\Database\Seeders\RlmLessonSeeder',
                '--force' => true,
            ]);
        });

        // Seed RLM prevention rules (curated, linked to base failures)
        $this->components->task('Seeding RLM prevention rules', function (): void {
            $this->callSilently('db:seed', [
                '--class' => 'Aicl\Database\Seeders\PreventionRuleSeeder',
                '--force' => true,
            ]);
        });

        // Seed RLM patterns from PatternRegistry
        $this->components->task('Seeding RLM patterns', function (): void {
            $this->callSilently('db:seed', [
                '--class' => 'Aicl\Database\Seeders\PatternRegistrySeeder',
                '--force' => true,
            ]);
        });

        // Seed golden annotations
        $this->components->task('Seeding golden annotations', function (): void {
            $this->callSilently('db:seed', [
                '--class' => 'Aicl\Database\Seeders\GoldenAnnotationSeeder',
                '--force' => true,
            ]);
        });

        // Seed distilled lessons (runs distillation pipeline on base failures)
        $this->components->task('Seeding distilled lessons', function (): void {
            $this->callSilently('db:seed', [
                '--class' => 'Aicl\Database\Seeders\DistilledLessonSeeder',
                '--force' => true,
            ]);
        });

        // Seed notification channels
        $this->components->task('Seeding notification channels', function (): void {
            $this->callSilently('db:seed', [
                '--class' => 'Aicl\Database\Seeders\NotificationChannelSeeder',
                '--force' => true,
            ]);
        });

        // Index RLM data into Elasticsearch (if available)
        $this->components->task('Indexing RLM data into Elasticsearch', function (): bool {
            if (! config('aicl.features.rlm_search', true)) {
                return false;
            }

            $this->callSilently('aicl:rlm', ['action' => 'index', '--all' => true]);

            return true;
        });

        // Generate embeddings for RLM data (if available)
        $this->components->task('Generating RLM embeddings', function (): bool {
            $embeddingService = app(\Aicl\Rlm\EmbeddingService::class);

            if (! $embeddingService->isAvailable()) {
                return false;
            }

            $this->callSilently('aicl:rlm', ['action' => 'embed', '--backfill' => true]);

            return true;
        });

        // Publish Filament assets
        $this->components->task('Publishing Filament assets', function (): void {
            $this->callSilently('filament:assets');
        });

        $this->newLine();
        $this->components->info('AICL installed successfully.');
        $this->components->info('Create your admin user with: php artisan db:seed --class="Aicl\Database\Seeders\AdminUserSeeder"');

        return self::SUCCESS;
    }

    /**
     * Check if AICL has already been installed by verifying
     * the roles table exists and has the expected roles.
     */
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
