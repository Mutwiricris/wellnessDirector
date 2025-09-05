<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DirectorUserSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create a director user for testing
        User::updateOrCreate(
            ['email' => 'director@wellness.com'],
            [
                'name' => 'Wellness Director',
                'first_name' => 'Wellness',
                'last_name' => 'Director',
                'email' => 'director@wellness.com',
                'password' => Hash::make('password'),
                'user_type' => 'director',
                'phone' => '0712345678',
                'allergies' => 'None',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Director user created: director@wellness.com / password');
    }
}