<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Drop the old unique constraint that prevents concurrent bookings
            $table->dropUnique('unique_staff_appointment');
            
            // Add a new constraint that prevents exact duplicate bookings
            // (same client, service, staff, date, and time)
            $table->unique([
                'client_id', 
                'service_id', 
                'staff_id', 
                'appointment_date', 
                'start_time'
            ], 'unique_client_service_appointment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Drop the new constraint
            $table->dropUnique('unique_client_service_appointment');
            
            // Restore the old constraint
            $table->unique([
                'staff_id', 
                'appointment_date', 
                'start_time'
            ], 'unique_staff_appointment');
        });
    }
};
