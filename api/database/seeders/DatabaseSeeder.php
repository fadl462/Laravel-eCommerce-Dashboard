<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $superAdminRole = Role::where('name', 'super_admin')->first();

        User::firstOrCreate(
            ['email' => 'john@storehub.com'],
            [
                'name' => 'John Admin',
                'password' => Hash::make('password'), // change immediately in production
                'role_id' => $superAdminRole->id,
                'status' => 'active',
            ]
        );

        // Baseline localization settings the Settings > Localization tab reads/writes.
        \App\Models\Setting::set('localization.default_language', 'ar', 'localization');
        \App\Models\Setting::set('localization.direction', 'rtl', 'localization');
        \App\Models\Setting::set('localization.currency', 'SAR', 'localization');
        \App\Models\Setting::set('localization.date_format', 'DD/MM/YYYY', 'localization');

        // Uncomment during local development to populate demo catalog/orders data:
        // $this->call(DemoDataSeeder::class);
    }
}
