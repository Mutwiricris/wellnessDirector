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
            // Add service started timestamp
            $table->timestamp('service_started_at')->nullable()->after('confirmed_at');
            
            // Add service completed timestamp
            $table->timestamp('service_completed_at')->nullable()->after('service_started_at');
            
            // Modify status enum to include 'in_progress'
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'])
                  ->default('pending')
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Remove the new columns
            $table->dropColumn(['service_started_at', 'service_completed_at']);
            
            // Revert status enum to original values
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'])
                  ->default('pending')
                  ->change();
        });
    }
};
