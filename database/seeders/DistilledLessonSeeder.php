<?php

namespace Aicl\Database\Seeders;

use Aicl\Rlm\DistillationService;
use Illuminate\Database\Seeder;

class DistilledLessonSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(DistillationService::class);
        $service->distill();
    }
}
