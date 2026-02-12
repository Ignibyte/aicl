<?php

namespace Aicl\Database\Seeders;

use Aicl\Models\GenerationTrace;
use App\Models\User;
use Illuminate\Database\Seeder;

class GenerationTraceSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::first() ?? User::factory()->create();

        GenerationTrace::factory()
            ->count(5)
            ->create(['owner_id' => $owner->id]);
    }
}
