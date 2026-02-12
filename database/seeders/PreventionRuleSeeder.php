<?php

namespace Aicl\Database\Seeders;

use Aicl\Models\PreventionRule;
use App\Models\User;
use Illuminate\Database\Seeder;

class PreventionRuleSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::first() ?? User::factory()->create();

        PreventionRule::factory()
            ->count(5)
            ->create(['owner_id' => $owner->id]);
    }
}
