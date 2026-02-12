<?php

namespace Aicl\Database\Seeders;

use Aicl\Models\FailureReport;
use App\Models\User;
use Illuminate\Database\Seeder;

class FailureReportSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::first() ?? User::factory()->create();

        FailureReport::factory()
            ->count(5)
            ->create(['owner_id' => $owner->id]);
    }
}
