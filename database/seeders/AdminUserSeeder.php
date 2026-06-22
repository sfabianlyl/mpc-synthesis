<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Ensures an admin user exists at admin@example.com.
     * If the user already exists, it is updated with the correct role.
     */
    public function run(): void
    {
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'role' => 'admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // Ensure the role is correct if user already existed
        if ($adminUser->wasRecentlyCreated === false) {
            $adminUser->update([
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);
        }

        $this->command->info('Admin user ensured: admin@example.com (password: password)');
    }
}
