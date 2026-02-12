<?php

namespace Aicl\Database\Seeders;

use Aicl\Models\RlmPattern;
use App\Models\User;
use Illuminate\Database\Seeder;

class RlmPatternSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::first() ?? User::factory()->create();

        RlmPattern::factory()
            ->count(5)
            ->create(['owner_id' => $owner->id]);
    }
}
