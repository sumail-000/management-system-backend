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
        // Create super admin user if it doesn't exist
        $superAdmin = Admin::where('email', 'admin@foodmanager.com')->first();
        
        if (!$superAdmin) {
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
            
            $this->command->info('Super admin user created successfully!');
            $this->command->info('Email: admin@foodmanager.com');
            $this->command->info('Password: admin123');
            $this->command->info('Role: Super Administrator');
        } else {
            $this->command->info('Super admin user already exists.');
        }
        
        // Create a regular admin user for testing
        $regularAdmin = Admin::where('email', 'admin2@foodmanager.com')->first();
        
        if (!$regularAdmin) {
            Admin::create([
                'name' => 'Regular Administrator',
                'email' => 'admin2@foodmanager.com',
                'password' => Hash::make('Admin@123456'),
                'role' => 'admin',
                'permissions' => Admin::getDefaultPermissions('admin'),
                'is_active' => true,
                'email_verified_at' => now(),
                'notes' => 'Regular administrator for testing',
                'created_by' => $superAdmin->id,
            ]);
            
            $this->command->info('Regular admin user created successfully!');
            $this->command->info('Email: admin2@foodmanager.com');
            $this->command->info('Password: Admin@123456');
            $this->command->info('Role: Administrator');
        } else {
            $this->command->info('Regular admin user already exists.');
        }
        
        // Create a moderator user for testing
        $moderator = Admin::where('email', 'moderator@foodmanager.com')->first();
        
        if (!$moderator) {
            Admin::create([
                'name' => 'Content Moderator',
                'email' => 'moderator@foodmanager.com',
                'password' => Hash::make('Admin@123456'),
                'role' => 'moderator',
                'permissions' => Admin::getDefaultPermissions('moderator'),
                'is_active' => true,
                'email_verified_at' => now(),
                'notes' => 'Content moderator for testing',
                'created_by' => $superAdmin->id,
            ]);
            
            $this->command->info('Moderator user created successfully!');
            $this->command->info('Email: moderator@foodmanager.com');
            $this->command->info('Password: Admin@123456');
            $this->command->info('Role: Moderator');
        } else {
            $this->command->info('Moderator user already exists.');
        }
        
        $this->command->warn('Please change the default passwords after first login!');
    }
}