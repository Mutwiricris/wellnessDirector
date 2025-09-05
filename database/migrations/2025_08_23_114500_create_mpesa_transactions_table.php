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
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_transaction_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('checkout_request_id')->nullable()->index();
            $table->string('merchant_request_id')->nullable()->index();
            $table->string('phone_number');
            $table->decimal('amount', 10, 2);
            $table->string('mpesa_receipt_number')->nullable()->index();
            $table->timestamp('transaction_date')->nullable();
            $table->enum('status', ['pending', 'success', 'failed', 'cancelled', 'timeout'])->default('pending');
            $table->string('result_code')->nullable();
            $table->text('result_desc')->nullable();
            $table->json('callback_data')->nullable();
            $table->string('account_reference')->nullable();
            $table->string('transaction_desc')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['phone_number', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
    }
};
