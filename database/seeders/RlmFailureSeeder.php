<?php

namespace Aicl\Database\Seeders;

use Aicl\Models\RlmFailure;
use App\Models\User;
use Illuminate\Database\Seeder;

class RlmFailureSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::first() ?? User::factory()->create();

        RlmFailure::factory()
            ->count(5)
            ->create(['owner_id' => $owner->id]);
    }
}
