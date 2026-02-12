<?php

namespace Aicl\Database\Seeders;

use Aicl\Models\RlmLesson;
use App\Models\User;
use Illuminate\Database\Seeder;

class RlmLessonSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::first() ?? User::factory()->create();

        RlmLesson::factory()
            ->count(5)
            ->create(['owner_id' => $owner->id]);
    }
}
