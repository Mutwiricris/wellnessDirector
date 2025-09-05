<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Staff;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ServiceStaffSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // First, create services (using category_id = 1 as default)
        $services = [
            [
                'name' => 'Swedish Massage',
                'description' => 'Relaxing full body massage using Swedish techniques',
                'duration_minutes' => 60,
                'price' => 3500.00,
                'status' => 'active',
                'category_id' => 1
            ],
            [
                'name' => 'Deep Tissue Massage',
                'description' => 'Intensive massage targeting deep muscle layers',
                'duration_minutes' => 90,
                'price' => 4500.00,
                'status' => 'active',
                'category_id' => 1
            ],
            [
                'name' => 'Facial Treatment',
                'description' => 'Rejuvenating facial with cleansing and moisturizing',
                'duration_minutes' => 45,
                'price' => 2500.00,
                'status' => 'active',
                'category_id' => 2
            ],
            [
                'name' => 'Body Scrub',
                'description' => 'Exfoliating body treatment with natural ingredients',
                'duration_minutes' => 30,
                'price' => 2000.00,
                'status' => 'active',
                'category_id' => 3
            ],
            [
                'name' => 'Manicure',
                'description' => 'Complete nail care and styling',
                'duration_minutes' => 45,
                'price' => 1500.00,
                'status' => 'active',
                'category_id' => 4
            ],
            [
                'name' => 'Pedicure',
                'description' => 'Complete foot and nail care treatment',
                'duration_minutes' => 60,
                'price' => 2000.00,
                'status' => 'active',
                'category_id' => 4
            ]
        ];

        foreach ($services as $serviceData) {
            Service::updateOrCreate(
                ['name' => $serviceData['name']],
                $serviceData
            );
        }

        // Create staff members
        $staffMembers = [
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah.johnson@wellness.com',
                'phone' => '0712345010',
                'specialties' => ['Massage Therapy', 'Swedish Massage'],
                'bio' => 'Senior therapist with 5+ years experience',
                'experience_years' => 5,
                'hourly_rate' => 1500.00,
                'status' => 'active',
                'user_type' => 'staff',
                'allergies' => 'None',
                'password' => Hash::make('password')
            ],
            [
                'name' => 'Michael Davis',
                'email' => 'michael.davis@wellness.com',
                'phone' => '0712345011',
                'specialties' => ['Deep Tissue Massage', 'Sports Massage'],
                'bio' => 'Massage therapist specializing in deep tissue work',
                'experience_years' => 3,
                'hourly_rate' => 1300.00,
                'status' => 'active',
                'user_type' => 'staff',
                'allergies' => 'None',
                'password' => Hash::make('password')
            ],
            [
                'name' => 'Emma Wilson',
                'email' => 'emma.wilson@wellness.com',
                'phone' => '0712345012',
                'specialties' => ['Facial Treatments', 'Skincare'],
                'bio' => 'Licensed aesthetician with expertise in facial treatments',
                'experience_years' => 4,
                'hourly_rate' => 1200.00,
                'status' => 'active',
                'user_type' => 'staff',
                'allergies' => 'None',
                'password' => Hash::make('password')
            ],
            [
                'name' => 'James Brown',
                'email' => 'james.brown@wellness.com',
                'phone' => '0712345013',
                'specialties' => ['Manicure', 'Pedicure', 'Nail Art'],
                'bio' => 'Professional nail technician with creative flair',
                'experience_years' => 2,
                'hourly_rate' => 1000.00,
                'status' => 'active',
                'user_type' => 'staff',
                'allergies' => 'None',
                'password' => Hash::make('password')
            ]
        ];

        foreach ($staffMembers as $staffData) {
            // Create as User first
            $user = User::updateOrCreate(
                ['email' => $staffData['email']],
                [
                    'name' => $staffData['name'],
                    'first_name' => explode(' ', $staffData['name'])[0],
                    'last_name' => explode(' ', $staffData['name'])[1] ?? '',
                    'email' => $staffData['email'],
                    'phone' => $staffData['phone'],
                    'user_type' => $staffData['user_type'],
                    'allergies' => $staffData['allergies'],
                    'password' => $staffData['password'],
                    'email_verified_at' => now(),
                ]
            );

            // Create Staff record
            Staff::updateOrCreate(
                ['email' => $staffData['email']],
                [
                    'name' => $staffData['name'],
                    'email' => $staffData['email'],
                    'phone' => $staffData['phone'],
                    'specialties' => $staffData['specialties'],
                    'bio' => $staffData['bio'],
                    'experience_years' => $staffData['experience_years'],
                    'hourly_rate' => $staffData['hourly_rate'],
                    'status' => $staffData['status'],
                ]
            );
        }

        // Assign services to all branches
        $branches = Branch::all();
        $services = Service::all();
        $staff = Staff::all();

        foreach ($branches as $branch) {
            // Attach all services to each branch
            foreach ($services as $service) {
                $branch->services()->syncWithoutDetaching([
                    $service->id => [
                        'is_available' => true,
                        'custom_price' => null
                    ]
                ]);
            }

            // Attach all staff to each branch
            foreach ($staff as $staffMember) {
                $branch->staff()->syncWithoutDetaching([
                    $staffMember->id => [
                        'working_hours' => json_encode([
                            'monday' => ['start' => '09:00', 'end' => '17:00'],
                            'tuesday' => ['start' => '09:00', 'end' => '17:00'],
                            'wednesday' => ['start' => '09:00', 'end' => '17:00'],
                            'thursday' => ['start' => '09:00', 'end' => '17:00'],
                            'friday' => ['start' => '09:00', 'end' => '17:00'],
                            'saturday' => ['start' => '10:00', 'end' => '16:00'],
                        ]),
                        'is_primary_branch' => $branch->id === 1 // First branch is primary
                    ]
                ]);
            }
        }

        // Assign services to staff based on specialties
        $massageServices = Service::whereIn('name', ['Swedish Massage', 'Deep Tissue Massage'])->get();
        $facialServices = Service::where('name', 'Facial Treatment')->get();
        $nailServices = Service::whereIn('name', ['Manicure', 'Pedicure'])->get();

        foreach ($staff as $staffMember) {
            $specialties = $staffMember->specialties ?? [];
            
            // Check if staff has massage specialties
            if (array_intersect($specialties, ['Massage Therapy', 'Swedish Massage', 'Deep Tissue Massage', 'Sports Massage'])) {
                foreach ($massageServices as $service) {
                    $staffMember->services()->syncWithoutDetaching([$service->id]);
                }
            }
            
            // Check if staff has facial specialties
            if (array_intersect($specialties, ['Facial Treatments', 'Skincare'])) {
                foreach ($facialServices as $service) {
                    $staffMember->services()->syncWithoutDetaching([$service->id]);
                }
            }
            
            // Check if staff has nail specialties
            if (array_intersect($specialties, ['Manicure', 'Pedicure', 'Nail Art'])) {
                foreach ($nailServices as $service) {
                    $staffMember->services()->syncWithoutDetaching([$service->id]);
                }
            }
        }

        $this->command->info('Created services and staff with branch assignments');
    }
}