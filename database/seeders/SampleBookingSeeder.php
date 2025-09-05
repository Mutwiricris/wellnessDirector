<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\User;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleBookingSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create some sample customers
        $customers = [
            [
                'name' => 'Alice Smith',
                'first_name' => 'Alice',
                'last_name' => 'Smith',
                'email' => 'alice.smith@example.com',
                'phone' => '0712000001',
                'user_type' => 'user',
                'allergies' => 'None',
                'gender' => 'female',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Bob Johnson',
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'email' => 'bob.johnson@example.com',
                'phone' => '0712000002',
                'user_type' => 'user',
                'allergies' => 'Latex sensitivity',
                'gender' => 'male',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        ];

        foreach ($customers as $customerData) {
            User::updateOrCreate(
                ['email' => $customerData['email']],
                $customerData
            );
        }

        // Get data for bookings
        $branches = Branch::take(3)->get();
        $services = Service::take(4)->get();
        $staff = Staff::take(3)->get();
        $customers = User::where('user_type', 'user')->take(2)->get();

        // Create sample bookings
        $bookings = [
            [
                'branch_id' => $branches[0]->id ?? 1,
                'service_id' => $services[0]->id ?? 1,
                'client_id' => $customers[0]->id ?? 1,
                'staff_id' => $staff[0]->id ?? null,
                'appointment_date' => today()->addDays(1),
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'status' => 'pending',
                'notes' => 'First booking request',
                'total_amount' => 3500.00,
                'payment_status' => 'pending',
                'payment_method' => 'mpesa',
            ],
            [
                'branch_id' => $branches[1]->id ?? 2,
                'service_id' => $services[1]->id ?? 2,
                'client_id' => $customers[1]->id ?? 2,
                'staff_id' => $staff[1]->id ?? null,
                'appointment_date' => today()->addDays(2),
                'start_time' => '14:00:00',
                'end_time' => '15:30:00',
                'status' => 'confirmed',
                'notes' => 'Regular customer',
                'total_amount' => 4500.00,
                'payment_status' => 'completed',
                'payment_method' => 'cash',
            ],
            [
                'branch_id' => $branches[0]->id ?? 1,
                'service_id' => $services[2]->id ?? 3,
                'client_id' => $customers[0]->id ?? 1,
                'staff_id' => $staff[2]->id ?? null,
                'appointment_date' => today(),
                'start_time' => '16:00:00',
                'end_time' => '16:45:00',
                'status' => 'completed',
                'notes' => 'Facial treatment completed',
                'total_amount' => 2500.00,
                'payment_status' => 'completed',
                'payment_method' => 'card',
            ]
        ];

        foreach ($bookings as $bookingData) {
            Booking::create($bookingData);
        }

        $this->command->info('Created sample bookings for testing');
    }
}