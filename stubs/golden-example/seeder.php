<?php

// PATTERN: Seeder creates a realistic dataset using factory states.
// PATTERN: Creates records in each status to populate widgets and UI.
// PATTERN: Associates members via pivot relationships where applicable.

namespace Aicl\Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        // PATTERN: Reuse the admin user if it already exists.
        $admin = User::where('email', 'admin@aicl.test')->first();

        if (! $admin) {
            $admin = User::factory()->create([
                'name' => 'Admin',
                'email' => 'admin@aicl.test',
            ]);
        }

        // PATTERN: Create extra users for relationships.
        $members = User::factory()->count(5)->create();

        // PATTERN: Create records in EACH status for demo data.
        // Draft projects
        Project::factory()
            ->count(3)
            ->draft()
            ->create(['owner_id' => $admin->id]);

        // Active projects with members
        $activeProjects = Project::factory()
            ->count(5)
            ->active()
            ->create(['owner_id' => $admin->id]);

        // PATTERN: Attach pivot relationships.
        foreach ($activeProjects as $project) {
            $project->members()->attach(
                $members->random(rand(2, 4))->pluck('id')->toArray(),
                ['role' => 'member']
            );
        }

        // On hold projects
        Project::factory()
            ->count(2)
            ->onHold()
            ->create(['owner_id' => $members->random()->id]);

        // Completed projects
        Project::factory()
            ->count(3)
            ->completed()
            ->create(['owner_id' => $admin->id]);

        // Archived project
        Project::factory()
            ->archived()
            ->create(['owner_id' => $admin->id]);

        // PATTERN: Include edge cases (overdue, high priority) for widget testing.
        Project::factory()
            ->overdue()
            ->highPriority()
            ->create(['owner_id' => $admin->id]);
    }
}
