<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'working_hours',
        'timezone',
        'status'
    ];

    protected $casts = [
        'working_hours' => 'array'
    ];

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'branch_services')
                    ->withPivot('is_available', 'custom_price')
                    ->withTimestamps();
    }

    public function activeServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'branch_services')
                    ->withPivot('is_available', 'custom_price')
                    ->wherePivot('is_available', true)
                    ->where('services.status', 'active')
                    ->withTimestamps();
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'branch_staff')
                    ->withPivot('working_hours', 'is_primary_branch')
                    ->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function managers(): HasMany
    {
        return $this->hasMany(User::class)->where('user_type', 'branch_manager');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(StaffSchedule::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Ecommerce relationships
    public function productInventory(): HasMany
    {
        return $this->hasMany(BranchProductInventory::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(ProductStockMovement::class);
    }

    public function ecommerceOrders(): HasMany
    {
        return $this->hasMany(EcommerceOrder::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(EcommerceOrderItem::class);
    }

    public function transfersFrom(): HasMany
    {
        return $this->hasMany(ProductTransfer::class, 'from_branch_id');
    }

    public function transfersTo(): HasMany
    {
        return $this->hasMany(ProductTransfer::class, 'to_branch_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    // POS and Marketing relationships
    public function discountCoupons(): HasMany
    {
        return $this->hasMany(DiscountCoupon::class);
    }

    public function giftVouchers(): HasMany
    {
        return $this->hasMany(GiftVoucher::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function posTransactions(): HasMany
    {
        return $this->hasMany(PosTransaction::class);
    }

    // Helper methods for ecommerce
    public function getAvailableProductsCount()
    {
        return $this->productInventory()
                   ->where('is_available', true)
                   ->where('quantity_on_hand', '>', 0)
                   ->count();
    }

    public function getLowStockProductsCount()
    {
        return $this->productInventory()
                   ->whereRaw('quantity_on_hand <= reorder_level')
                   ->count();
    }

    public function getTotalInventoryValue()
    {
        return $this->productInventory()
                   ->join('products', 'branch_product_inventory.product_id', '=', 'products.id')
                   ->selectRaw('SUM(quantity_on_hand * cost_price) as total_value')
                   ->value('total_value') ?? 0;
    }
}