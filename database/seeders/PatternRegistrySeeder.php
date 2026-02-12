<?php

namespace Aicl\Database\Seeders;

use Aicl\Models\RlmPattern;
use Aicl\Rlm\PatternRegistry;
use App\Models\User;
use Illuminate\Database\Seeder;

class PatternRegistrySeeder extends Seeder
{
    public function run(): void
    {
        $ownerId = User::first()?->id ?? 1;

        foreach (PatternRegistry::all() as $pattern) {
            RlmPattern::query()->updateOrCreate(
                ['name' => $pattern->name],
                [
                    'description' => $pattern->description,
                    'target' => $pattern->target,
                    'check_regex' => $pattern->check,
                    'severity' => $pattern->severity,
                    'weight' => $pattern->weight,
                    'category' => 'structural',
                    'source' => 'registry',
                    'is_active' => true,
                    'owner_id' => $ownerId,
                ]
            );
        }
    }
}
