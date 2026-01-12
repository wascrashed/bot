<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AdminUser::create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin'),
            'name' => 'Administrator',
            'is_active' => true,
        ]);

        $this->command->info('Admin user created:');
        $this->command->info('Username: admin');
        $this->command->info('Password: admin');
        $this->command->warn('⚠️  Please change the default password after first login!');
    }
}
