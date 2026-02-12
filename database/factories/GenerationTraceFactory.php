<?php

namespace Aicl\Database\Factories;

use Aicl\Models\GenerationTrace;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenerationTrace>
 */
class GenerationTraceFactory extends Factory
{
    protected $model = GenerationTrace::class;

    public function definition(): array
    {
        $entityName = fake()->randomElement([
            'Project', 'Task', 'Invoice', 'Customer', 'Product',
            'Order', 'Ticket', 'Document', 'Report', 'Campaign',
        ]);

        $fields = fake()->randomElements([
            'name:string', 'description:text', 'status:enum:Status',
            'priority:enum:Priority', 'due_date:date:nullable',
            'amount:float', 'is_active:boolean:default(true)',
            'assigned_to:foreignId:users', 'category:string:index',
        ], fake()->numberBetween(3, 7));

        return [
            'entity_name' => $entityName,
            'project_hash' => fake()->optional(0.8)->sha256(),
            'scaffolder_args' => '--fields="'.implode(',', $fields).'" --widgets --notifications --no-interaction',
            'file_manifest' => $this->generateFileManifest($entityName),
            'structural_score' => fake()->optional(0.8)->randomFloat(2, 80.00, 100.00),
            'semantic_score' => fake()->optional(0.6)->randomFloat(2, 70.00, 100.00),
            'test_results' => fake()->optional(0.7)->randomElement([
                'Tests: 19 passed (28 assertions)',
                'Tests: 24 passed (42 assertions)',
                'Tests: 15 passed (22 assertions), 2 failed',
                'Tests: 27 passed (35 assertions)',
            ]),
            'fixes_applied' => fake()->optional(0.4)->randomElement([
                ['Fixed searchableColumns override', 'Added UUID primary key'],
                ['Fixed Section import namespace'],
                ['Updated observer log messages', 'Fixed factory states'],
            ]),
            'fix_iterations' => fake()->numberBetween(0, 3),
            'pipeline_duration' => fake()->optional(0.7)->numberBetween(120, 1800),
            'agent_versions' => [
                'architect' => 'claude-opus-4-'.fake()->date('Y-m-d'),
                'rlm' => 'claude-opus-4-'.fake()->date('Y-m-d'),
                'tester' => 'claude-opus-4-'.fake()->date('Y-m-d'),
            ],
            'is_processed' => false,
            'aicl_version' => fake()->randomElement(['1.0.0', '1.0.1', '1.0.2', '1.0.3', '1.0.4', '1.0.5']),
            'laravel_version' => fake()->randomElement(['11.0.0', '11.1.0', '11.2.0', '12.0.0']),
            'is_active' => true,
            'owner_id' => User::factory(),
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_processed' => true,
            'structural_score' => fake()->randomFloat(2, 90.00, 100.00),
        ]);
    }

    public function withHighScore(): static
    {
        return $this->state(fn (array $attributes): array => [
            'structural_score' => fake()->randomFloat(2, 95.00, 100.00),
            'semantic_score' => fake()->randomFloat(2, 90.00, 100.00),
            'fix_iterations' => 0,
        ]);
    }

    public function withFixes(): static
    {
        return $this->state(fn (array $attributes): array => [
            'fix_iterations' => fake()->numberBetween(1, 3),
            'fixes_applied' => [
                'Fixed searchableColumns override',
                'Updated Section/Grid imports to Filament\Schemas\Components',
            ],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * @return list<string>
     */
    private function generateFileManifest(string $entityName): array
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $entityName));

        return [
            "app/Models/{$entityName}.php",
            "database/migrations/create_{$snake}s_table.php",
            "database/factories/{$entityName}Factory.php",
            "app/Policies/{$entityName}Policy.php",
            "app/Observers/{$entityName}Observer.php",
            "app/Filament/Resources/{$entityName}s/{$entityName}Resource.php",
            "app/Http/Controllers/Api/{$entityName}Controller.php",
            "tests/Feature/Entities/{$entityName}Test.php",
        ];
    }
}
