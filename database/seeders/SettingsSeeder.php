<?php

namespace Aicl\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General Settings (group: general)
            ['group' => 'general', 'name' => 'site_name', 'payload' => json_encode(config('app.name', 'AICL'))],
            ['group' => 'general', 'name' => 'site_description', 'payload' => json_encode('')],
            ['group' => 'general', 'name' => 'timezone', 'payload' => json_encode(config('app.timezone', 'UTC'))],
            ['group' => 'general', 'name' => 'date_format', 'payload' => json_encode('Y-m-d')],
            ['group' => 'general', 'name' => 'items_per_page', 'payload' => json_encode(25)],
            ['group' => 'general', 'name' => 'maintenance_mode', 'payload' => json_encode(false)],

            // Mail Settings (group: mail)
            ['group' => 'mail', 'name' => 'from_address', 'payload' => json_encode(config('mail.from.address', 'noreply@aicl.test'))],
            ['group' => 'mail', 'name' => 'from_name', 'payload' => json_encode(config('app.name', 'AICL'))],
            ['group' => 'mail', 'name' => 'reply_to', 'payload' => json_encode(null)],

            // Feature Settings (group: features)
            ['group' => 'features', 'name' => 'enable_registration', 'payload' => json_encode(false)],
            ['group' => 'features', 'name' => 'enable_social_login', 'payload' => json_encode(false)],
            ['group' => 'features', 'name' => 'enable_saml', 'payload' => json_encode(false)],
            ['group' => 'features', 'name' => 'require_mfa', 'payload' => json_encode(false)],
            ['group' => 'features', 'name' => 'enable_api', 'payload' => json_encode(true)],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['group' => $setting['group'], 'name' => $setting['name']],
                array_merge($setting, ['locked' => false, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
