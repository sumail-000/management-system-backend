<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = Admin::create([
            'name' => 'System Administrator',
            'email' => 'admin@foodmanager.com',
            'password' => Hash::make('Admin@123456'),
            'role' => 'super_admin',
            'permissions' => Admin::getAvailablePermissions(),
            'is_active' => true,
            'email_verified_at' => now(),
            'notes' => 'System super administrator with full access',
        ]);
    }
}
