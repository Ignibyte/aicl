<?php

namespace Aicl\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the default admin user and assign super_admin role.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@aicl.test'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('super_admin');
    }
}
