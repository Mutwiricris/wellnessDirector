<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Products Table (Global - accessible by all branches)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->json('images')->nullable(); // Multiple product images
            $table->decimal('base_price', 12, 2);
            $table->decimal('cost_price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable(); // For discounts
            $table->boolean('track_inventory')->default(true);
            $table->string('weight_unit')->default('kg')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->json('dimensions')->nullable(); // length, width, height
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->enum('type', ['physical', 'digital', 'service'])->default('physical');
            $table->boolean('requires_shipping')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->json('tags')->nullable();
            $table->json('meta_data')->nullable(); // SEO meta, custom fields
            $table->integer('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['is_featured', 'status']);
            $table->fullText(['name', 'description']);
        });

        // Product Categories (Global)
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->json('meta_data')->nullable();
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('product_categories')->onDelete('set null');
            $table->index(['parent_id', 'status']);
        });

        // Product Category Relationships
        Schema::create('product_category_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_category_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['product_id', 'product_category_id'], 'product_category_unique');
        });

        // Product Variants (Global)
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('title'); // e.g., "Small", "Red", "Cotton"
            $table->string('option1')->nullable(); // Size
            $table->string('option2')->nullable(); // Color
            $table->string('option3')->nullable(); // Material
            $table->string('sku')->unique();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2);
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('barcode')->nullable();
            $table->string('image')->nullable();
            $table->boolean('track_inventory')->default(true);
            $table->integer('position')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });

        // Branch Product Inventory (Branch-specific stock levels)
        Schema::create('branch_product_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0); // For pending orders
            $table->integer('quantity_available')->storedAs('quantity_on_hand - quantity_reserved');
            $table->integer('reorder_level')->default(10);
            $table->integer('max_stock_level')->nullable();
            $table->decimal('branch_price', 12, 2)->nullable(); // Branch-specific pricing
            $table->boolean('is_available')->default(true);
            $table->date('last_restocked_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'product_id', 'product_variant_id'], 'branch_inventory_unique');
            $table->index(['branch_id', 'is_available']);
            $table->index(['quantity_on_hand', 'reorder_level']);
        });

        // Product Stock Movements (Branch-specific)
        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('movement_type', ['in', 'out', 'transfer_in', 'transfer_out', 'adjustment', 'sale', 'return', 'waste']);
            $table->integer('quantity'); // Can be negative for out movements
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reference_type')->nullable(); // order, transfer, adjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('staff_id')->nullable()->constrained()->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamp('movement_date');
            $table->timestamps();

            $table->index(['branch_id', 'movement_type', 'movement_date'], 'stock_movement_branch_idx');
            $table->index(['reference_type', 'reference_id'], 'stock_movement_ref_idx');
        });

        // E-commerce Orders (Branch-specific - where customer places order)
        Schema::create('ecommerce_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade'); // Target branch
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'partial', 'failed', 'refunded'])->default('pending');
            $table->enum('fulfillment_status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            
            // Financial
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('KES');
            
            // Customer Info
            $table->json('billing_address');
            $table->json('shipping_address');
            $table->string('customer_email');
            $table->string('customer_phone');
            
            // Fulfillment
            $table->enum('delivery_method', ['pickup', 'delivery', 'shipping'])->default('pickup');
            $table->text('special_instructions')->nullable();
            $table->timestamp('requested_delivery_date')->nullable();
            $table->json('delivery_time_slot')->nullable(); // start_time, end_time
            
            // Payment
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            // Tracking
            $table->string('tracking_number')->nullable();
            $table->json('tracking_updates')->nullable();
            
            // Metadata
            $table->string('source')->default('website'); // website, mobile_app, phone, walk_in
            $table->json('meta_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'created_at'], 'order_branch_status_idx');
            $table->index(['customer_id', 'status'], 'order_customer_status_idx');
            $table->index(['payment_status', 'created_at'], 'order_payment_date_idx');
            $table->index(['order_number'], 'order_number_idx');
        });

        // Order Items (Branch-specific)
        Schema::create('ecommerce_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('ecommerce_orders')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('product_name'); // Snapshot at time of order
            $table->string('variant_title')->nullable();
            $table->string('sku');
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->json('product_snapshot')->nullable(); // Full product data at time of order
            $table->text('special_instructions')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'branch_id'], 'order_item_branch_idx');
            $table->index(['product_id', 'created_at'], 'order_item_product_idx');
        });

        // Inter-branch Product Transfers
        Schema::create('product_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->foreignId('from_branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('to_branch_id')->constrained('branches')->onDelete('cascade');
            $table->enum('status', ['pending', 'in_transit', 'received', 'cancelled'])->default('pending');
            $table->foreignId('requested_by_staff_id')->constrained('staff')->onDelete('cascade');
            $table->foreignId('sent_by_staff_id')->nullable()->constrained('staff')->onDelete('set null');
            $table->foreignId('received_by_staff_id')->nullable()->constrained('staff')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['from_branch_id', 'status'], 'transfer_from_branch_idx');
            $table->index(['to_branch_id', 'status'], 'transfer_to_branch_idx');
        });

        // Transfer Items
        Schema::create('product_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('product_transfers')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('requested_quantity');
            $table->integer('sent_quantity')->default(0);
            $table->integer('received_quantity')->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['transfer_id', 'product_id'], 'transfer_item_idx');
        });

        // Suppliers (Global)
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('tax_number')->nullable();
            $table->json('payment_terms')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('meta_data')->nullable();
            $table->timestamps();

            $table->index(['status', 'name'], 'supplier_status_name_idx');
        });

        // Purchase Orders (Branch-specific)
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['draft', 'sent', 'confirmed', 'received', 'cancelled'])->default('draft');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('received_date')->nullable();
            $table->foreignId('created_by_staff_id')->constrained('staff')->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status'], 'po_branch_status_idx');
            $table->index(['supplier_id', 'status'], 'po_supplier_status_idx');
        });

        // Purchase Order Items
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('ordered_quantity');
            $table->integer('received_quantity')->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->timestamps();

            $table->index(['purchase_order_id', 'product_id'], 'po_item_idx');
        });

        // E-commerce Website Settings (Global)
        Schema::create('ecommerce_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Shopping Cart (Temporary - for website)
        Schema::create('shopping_carts', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->foreignId('customer_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade'); // Target branch for order
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->json('product_snapshot'); // Price, name, etc at time of add
            $table->timestamps();

            $table->index(['session_id', 'branch_id'], 'cart_session_branch_idx');
            $table->index(['customer_id', 'branch_id'], 'cart_customer_branch_idx');
        });

        // Wishlists
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['customer_id', 'product_id', 'product_variant_id'], 'wishlist_unique');
        });

        // Customer Reviews
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('ecommerce_orders')->onDelete('set null');
            $table->integer('rating'); // 1-5 stars
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->json('images')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('is_verified_purchase')->default(false);
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->index(['product_id', 'status', 'rating'], 'review_product_idx');
            $table->index(['customer_id', 'status'], 'review_customer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('shopping_carts');
        Schema::dropIfExists('ecommerce_settings');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('product_transfer_items');
        Schema::dropIfExists('product_transfers');
        Schema::dropIfExists('ecommerce_order_items');
        Schema::dropIfExists('ecommerce_orders');
        Schema::dropIfExists('product_stock_movements');
        Schema::dropIfExists('branch_product_inventory');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_category_relationships');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('products');
    }
};