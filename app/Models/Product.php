<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'description',
        'short_description',
        'images',
        'base_price',
        'cost_price',
        'compare_at_price',
        'track_inventory',
        'weight_unit',
        'weight',
        'dimensions',
        'status',
        'type',
        'requires_shipping',
        'is_featured',
        'tags',
        'meta_data',
        'sort_order',
        'published_at'
    ];

    protected $casts = [
        'images' => 'array',
        'dimensions' => 'array',
        'tags' => 'array',
        'meta_data' => 'array',
        'track_inventory' => 'boolean',
        'requires_shipping' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
        'base_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'weight' => 'decimal:2'
    ];

    // Product categories relationship
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category_relationships');
    }

    // Product variants
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    // Branch inventory - shows stock levels across all branches
    public function branchInventory(): HasMany
    {
        return $this->hasMany(BranchProductInventory::class);
    }

    // Stock movements across all branches
    public function stockMovements(): HasMany
    {
        return $this->hasMany(ProductStockMovement::class);
    }

    // Order items
    public function orderItems(): HasMany
    {
        return $this->hasMany(EcommerceOrderItem::class);
    }

    // Reviews
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    // Transfer items
    public function transferItems(): HasMany
    {
        return $this->hasMany(ProductTransferItem::class);
    }

    // Purchase order items
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'active')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // Helper methods
    public function getAvailableQuantityForBranch($branchId)
    {
        $inventory = $this->branchInventory()
                         ->where('branch_id', $branchId)
                         ->first();
        
        return $inventory ? $inventory->quantity_available : 0;
    }

    public function getTotalQuantityAcrossAllBranches()
    {
        return $this->branchInventory()->sum('quantity_on_hand');
    }

    public function isAvailableInBranch($branchId)
    {
        $inventory = $this->branchInventory()
                         ->where('branch_id', $branchId)
                         ->where('is_available', true)
                         ->first();
        
        return $inventory && $inventory->quantity_available > 0;
    }

    public function getMainImage()
    {
        return $this->images[0] ?? null;
    }
}