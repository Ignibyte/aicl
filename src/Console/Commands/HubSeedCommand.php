<?php

namespace Aicl\Console\Commands;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmPattern;
use Aicl\Rlm\EntityPattern;
use Aicl\Rlm\PatternRegistry;
use App\Models\User;
use Illuminate\Console\Command;

class HubSeedCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'aicl:hub-seed
        {--force : Re-seed even if data already exists}';

    /**
     * @var string
     */
    protected $description = 'Seed hub entities from PatternRegistry and base failures. Idempotent.';

    /**
     * Category mapping from base-failures SQL to FailureCategory enum values.
     *
     * @var array<string, string>
     */
    protected array $categoryMap = [
        'scaffolding' => 'scaffolding',
        'process' => 'process',
        'filament' => 'filament',
        'testing' => 'testing',
        'auth' => 'auth',
        'tailwind' => 'tailwind',
        'events' => 'other',
        'migrations' => 'other',
        'configuration' => 'configuration',
        'laravel' => 'laravel',
    ];

    public function handle(): int
    {
        $owner = User::first();

        if (! $owner) {
            $this->components->error('No users found. Run database seeders first.');

            return self::FAILURE;
        }

        $patternCount = $this->seedPatterns($owner);
        $failureCount = $this->seedBaseFailures($owner);

        $this->newLine();
        $this->components->info("Hub seed complete: {$patternCount} patterns, {$failureCount} failures.");

        return self::SUCCESS;
    }

    protected function seedPatterns(User $owner): int
    {
        $patterns = PatternRegistry::all();
        $seeded = 0;

        $this->components->info('Seeding '.count($patterns).' patterns from PatternRegistry...');

        foreach ($patterns as $pattern) {
            $result = RlmPattern::query()->updateOrCreate(
                ['name' => $pattern->name],
                $this->patternToAttributes($pattern, $owner),
            );

            if ($result->wasRecentlyCreated) {
                $seeded++;
            }
        }

        $this->components->twoColumnDetail('Patterns', "{$seeded} created, ".(count($patterns) - $seeded).' updated');

        return count($patterns);
    }

    protected function seedBaseFailures(User $owner): int
    {
        $failures = $this->parseBaseFailures();
        $seeded = 0;

        $this->components->info('Seeding '.count($failures).' base failures...');

        foreach ($failures as $failure) {
            $result = RlmFailure::query()->updateOrCreate(
                ['failure_code' => $failure['failure_code']],
                array_merge($failure, ['owner_id' => $owner->id]),
            );

            if ($result->wasRecentlyCreated) {
                $seeded++;
            }
        }

        $this->components->twoColumnDetail('Failures', "{$seeded} created, ".(count($failures) - $seeded).' updated');

        return count($failures);
    }

    /**
     * Convert an EntityPattern to RlmPattern model attributes.
     *
     * @return array<string, mixed>
     */
    protected function patternToAttributes(EntityPattern $pattern, User $owner): array
    {
        return [
            'description' => $pattern->description,
            'target' => $pattern->target,
            'check_regex' => $pattern->check,
            'severity' => $pattern->severity,
            'weight' => $pattern->weight,
            'category' => $this->inferPatternCategory($pattern->target),
            'source' => 'registry',
            'is_active' => true,
            'owner_id' => $owner->id,
        ];
    }

    /**
     * Infer a category from the pattern target.
     */
    protected function inferPatternCategory(string $target): string
    {
        return match ($target) {
            'model' => 'model',
            'migration' => 'database',
            'factory' => 'testing',
            'policy' => 'security',
            'observer' => 'events',
            'filament', 'filament_resource' => 'filament',
            'test' => 'testing',
            'app_service_provider' => 'registration',
            default => 'general',
        };
    }

    /**
     * Parse the base-failures SQL file into model-friendly arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseBaseFailures(): array
    {
        $sqlFile = dirname(__DIR__, 3).'/database/rlm/seed-base-failures.sql';

        if (! file_exists($sqlFile)) {
            $this->components->warn('Base failures SQL file not found.');

            return [];
        }

        $sql = file_get_contents($sqlFile);
        $failures = [];

        // Match each INSERT statement's VALUES clause
        preg_match_all(
            "/VALUES\s*\(\s*'([^']+)',\s*'[^']+',\s*'([^']+)',\s*'((?:[^']|'')+)',\s*'((?:[^']|'')+)',\s*'((?:[^']|'')+)',\s*'([^']+)',\s*(\d+),\s*'([^']+)',\s*'([^']+)'\s*\)/s",
            $sql,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $category = $this->categoryMap[$match[2]] ?? 'other';

            // Validate enum values exist
            $categoryEnum = FailureCategory::tryFrom($category);
            if (! $categoryEnum) {
                $category = 'other';
            }

            $severityEnum = FailureSeverity::tryFrom($match[6]);
            $severity = $severityEnum ? $match[6] : 'medium';

            $failures[] = [
                'failure_code' => $match[1],
                'category' => $category,
                'title' => str_replace("''", "'", $match[3]),
                'description' => str_replace("''", "'", $match[4]),
                'preventive_rule' => str_replace("''", "'", $match[5]),
                'severity' => $severity,
                'scaffolding_fixed' => (bool) $match[7],
                'is_active' => $match[8] === 'active',
                'aicl_version' => $match[9],
                'promoted_to_base' => true,
            ];
        }

        return $failures;
    }
}
