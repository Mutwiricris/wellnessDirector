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
        // Create service_packages table
        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('package_code')->unique();
            $table->enum('package_type', ['wellness', 'beauty', 'spa', 'couples', 'premium', 'seasonal', 'membership'])->default('wellness');
            $table->decimal('total_price', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('final_price', 10, 2);
            $table->integer('validity_days')->default(365); // Days from purchase
            $table->integer('max_bookings')->nullable(); // Max simultaneous bookings
            $table->boolean('is_couple_package')->default(false);
            $table->boolean('requires_consecutive_booking')->default(false);
            $table->integer('booking_interval_days')->nullable(); // Min days between bookings
            $table->enum('status', ['active', 'inactive', 'draft', 'expired'])->default('active');
            $table->string('image_path')->nullable();
            $table->json('terms_conditions')->nullable();
            $table->boolean('popular')->default(false);
            $table->boolean('featured')->default(false);
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('created_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            
            $table->index(['branch_id', 'status']);
            $table->index(['package_type']);
            $table->index(['popular', 'featured']);
        });

        // Create service_package_items table (pivot table with additional fields)
        Schema::create('service_package_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_package_id');
            $table->unsignedBigInteger('service_id');
            $table->integer('quantity')->default(1); // How many times this service is included
            $table->integer('order')->default(1); // Order of service in package
            $table->boolean('is_required')->default(true); // Required vs optional service
            $table->text('notes')->nullable(); // Special instructions for this service
            $table->timestamps();

            $table->foreign('service_package_id')->references('id')->on('service_packages')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            
            $table->unique(['service_package_id', 'service_id', 'order'], 'unique_package_service_order');
            $table->index(['service_package_id']);
        });

        // Create package_sales table
        Schema::create('package_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_package_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('branch_id');
            $table->string('sale_reference')->unique();
            $table->decimal('original_price', 10, 2);
            $table->decimal('discount_applied', 10, 2)->default(0);
            $table->decimal('final_price', 10, 2);
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->enum('payment_method', ['cash', 'mpesa', 'card', 'bank_transfer', 'gift_voucher', 'loyalty_points'])->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['active', 'expired', 'cancelled', 'fully_used'])->default('active');
            $table->unsignedBigInteger('sold_by_staff_id')->nullable();
            $table->text('notes')->nullable();
            
            // Gift package fields
            $table->string('gift_recipient_name')->nullable();
            $table->string('gift_recipient_phone')->nullable();
            $table->string('gift_recipient_email')->nullable();
            $table->boolean('is_gift')->default(false);
            $table->string('redemption_code')->unique()->nullable();
            
            $table->timestamps();

            $table->foreign('service_package_id')->references('id')->on('service_packages')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('sold_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            
            $table->index(['user_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index(['payment_status']);
            $table->index(['expires_at']);
            $table->index(['is_gift']);
        });

        // Create package_bookings table
        Schema::create('package_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_sale_id');
            $table->unsignedBigInteger('service_package_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->string('booking_reference')->unique();
            $table->date('appointment_date');
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->integer('service_order')->default(1); // Order of service in package sequence
            $table->timestamps();

            $table->foreign('package_sale_id')->references('id')->on('package_sales')->onDelete('cascade');
            $table->foreign('service_package_id')->references('id')->on('service_packages')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('set null');
            
            $table->index(['package_sale_id', 'status']);
            $table->index(['user_id', 'appointment_date']);
            $table->index(['staff_id', 'appointment_date']);
            $table->index(['branch_id', 'appointment_date']);
            $table->index(['appointment_date', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_bookings');
        Schema::dropIfExists('package_sales');
        Schema::dropIfExists('service_package_items');
        Schema::dropIfExists('service_packages');
    }
};
