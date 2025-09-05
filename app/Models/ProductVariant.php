<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'title',
        'option1',
        'option2',
        'option3',
        'sku',
        'price',
        'compare_at_price',
        'cost_price',
        'weight',
        'barcode',
        'image',
        'track_inventory',
        'position',
        'status'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'track_inventory' => 'boolean'
    ];

    // Parent product
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Branch inventory for this variant
    public function branchInventory(): HasMany
    {
        return $this->hasMany(BranchProductInventory::class);
    }

    // Stock movements for this variant
    public function stockMovements(): HasMany
    {
        return $this->hasMany(ProductStockMovement::class);
    }

    // Order items for this variant
    public function orderItems(): HasMany
    {
        return $this->hasMany(EcommerceOrderItem::class);
    }

    // Transfer items for this variant
    public function transferItems(): HasMany
    {
        return $this->hasMany(ProductTransferItem::class);
    }

    // Purchase order items for this variant
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Helper methods
    public function getAvailableQuantityForBranch($branchId)
    {
        $inventory = $this->branchInventory()
                         ->where('branch_id', $branchId)
                         ->first();
        
        return $inventory ? $inventory->quantity_available : 0;
    }

    public function isAvailableInBranch($branchId)
    {
        $inventory = $this->branchInventory()
                         ->where('branch_id', $branchId)
                         ->where('is_available', true)
                         ->first();
        
        return $inventory && $inventory->quantity_available > 0;
    }

    public function getFullTitleAttribute()
    {
        $options = array_filter([$this->option1, $this->option2, $this->option3]);
        return $this->title ?: implode(' / ', $options);
    }

    public function getDisplayPriceAttribute()
    {
        return $this->price ?? $this->product->base_price;
    }
}