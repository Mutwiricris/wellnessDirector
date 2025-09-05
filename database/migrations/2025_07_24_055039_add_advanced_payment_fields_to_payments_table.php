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
        Schema::table('payments', function (Blueprint $table) {
            // Add missing fields from the original design
            $table->string('mpesa_transaction_id')->nullable()->after('mpesa_checkout_request_id');
            $table->text('notes')->nullable()->after('processed_at');
            $table->decimal('refund_amount', 10, 2)->nullable()->after('notes');
            $table->timestamp('refunded_at')->nullable()->after('refund_amount');
            $table->json('gateway_response')->nullable()->after('refunded_at');
            $table->string('card_last_four', 4)->nullable()->after('gateway_response');
            $table->string('card_brand')->nullable()->after('card_last_four');
            
            // Add new advanced fields
            $table->string('bank_reference')->nullable()->after('card_brand');
            $table->string('authorization_code')->nullable()->after('bank_reference');
            $table->string('payment_channel')->nullable()->after('authorization_code');
            $table->unsignedBigInteger('customer_id')->nullable()->after('payment_channel');
            $table->unsignedBigInteger('staff_id')->nullable()->after('customer_id');
            
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['staff_id']);
            $table->dropColumn([
                'mpesa_transaction_id',
                'notes',
                'refund_amount',
                'refunded_at',
                'gateway_response',
                'card_last_four',
                'card_brand',
                'bank_reference',
                'authorization_code',
                'payment_channel',
                'customer_id',
                'staff_id'
            ]);
        });
    }
};
