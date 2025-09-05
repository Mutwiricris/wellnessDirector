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
        Schema::table('pos_payment_splits', function (Blueprint $table) {
            $table->decimal('processing_fee', 10, 2)->nullable()->after('amount');
            $table->string('authorization_code')->nullable()->after('reference_number');
            $table->string('payment_channel')->nullable()->after('authorization_code');
            $table->unsignedBigInteger('customer_id')->nullable()->after('payment_channel');
            
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_payment_splits', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn([
                'processing_fee',
                'authorization_code', 
                'payment_channel',
                'customer_id'
            ]);
        });
    }
};
