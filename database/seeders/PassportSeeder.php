<?php

namespace Aicl\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Client;

class PassportSeeder extends Seeder
{
    public function run(): void
    {
        if (Client::where('name', 'AICL Personal Access Client')->exists()) {
            return;
        }

        Artisan::call('passport:client', [
            '--personal' => true,
            '--name' => 'AICL Personal Access Client',
            '--no-interaction' => true,
        ]);
    }
}
