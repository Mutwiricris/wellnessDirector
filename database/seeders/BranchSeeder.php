<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Downtown Spa',
                'address' => '123 Main Street, Downtown, Nairobi',
                'phone' => '0712345001',
                'email' => 'downtown@wellness.com',
                'status' => 'active',
                'timezone' => 'Africa/Nairobi',
                'working_hours' => [
                    ['day' => 'monday', 'open_time' => '08:00', 'close_time' => '20:00', 'is_closed' => false],
                    ['day' => 'tuesday', 'open_time' => '08:00', 'close_time' => '20:00', 'is_closed' => false],
                    ['day' => 'wednesday', 'open_time' => '08:00', 'close_time' => '20:00', 'is_closed' => false],
                    ['day' => 'thursday', 'open_time' => '08:00', 'close_time' => '20:00', 'is_closed' => false],
                    ['day' => 'friday', 'open_time' => '08:00', 'close_time' => '21:00', 'is_closed' => false],
                    ['day' => 'saturday', 'open_time' => '09:00', 'close_time' => '21:00', 'is_closed' => false],
                    ['day' => 'sunday', 'open_time' => '10:00', 'close_time' => '18:00', 'is_closed' => false],
                ]
            ],
            [
                'name' => 'Westlands Branch',
                'address' => '456 Westlands Road, Westlands, Nairobi',
                'phone' => '0712345002',
                'email' => 'westlands@wellness.com',
                'status' => 'active',
                'timezone' => 'Africa/Nairobi',
                'working_hours' => [
                    ['day' => 'monday', 'open_time' => '09:00', 'close_time' => '19:00', 'is_closed' => false],
                    ['day' => 'tuesday', 'open_time' => '09:00', 'close_time' => '19:00', 'is_closed' => false],
                    ['day' => 'wednesday', 'open_time' => '09:00', 'close_time' => '19:00', 'is_closed' => false],
                    ['day' => 'thursday', 'open_time' => '09:00', 'close_time' => '19:00', 'is_closed' => false],
                    ['day' => 'friday', 'open_time' => '09:00', 'close_time' => '20:00', 'is_closed' => false],
                    ['day' => 'saturday', 'open_time' => '10:00', 'close_time' => '20:00', 'is_closed' => false],
                    ['day' => 'sunday', 'open_time' => '00:00', 'close_time' => '00:00', 'is_closed' => true],
                ]
            ],
            [
                'name' => 'Karen Wellness Center',
                'address' => '789 Karen Road, Karen, Nairobi',
                'phone' => '0712345003',
                'email' => 'karen@wellness.com',
                'status' => 'active',
                'timezone' => 'Africa/Nairobi',
                'working_hours' => [
                    ['day' => 'monday', 'open_time' => '08:30', 'close_time' => '18:30', 'is_closed' => false],
                    ['day' => 'tuesday', 'open_time' => '08:30', 'close_time' => '18:30', 'is_closed' => false],
                    ['day' => 'wednesday', 'open_time' => '08:30', 'close_time' => '18:30', 'is_closed' => false],
                    ['day' => 'thursday', 'open_time' => '08:30', 'close_time' => '18:30', 'is_closed' => false],
                    ['day' => 'friday', 'open_time' => '08:30', 'close_time' => '19:30', 'is_closed' => false],
                    ['day' => 'saturday', 'open_time' => '09:30', 'close_time' => '19:30', 'is_closed' => false],
                    ['day' => 'sunday', 'open_time' => '11:00', 'close_time' => '17:00', 'is_closed' => false],
                ]
            ]
        ];

        foreach ($branches as $branchData) {
            Branch::updateOrCreate(
                ['email' => $branchData['email']],
                $branchData
            );
        }

        $this->command->info('Created 3 sample branches for director navigation');
    }
}