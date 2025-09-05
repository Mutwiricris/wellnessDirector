<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BranchProductInventory extends Model
{
    use HasFactory;

    protected $table = 'branch_product_inventory';

    protected $fillable = [
        'branch_id',
        'product_id',
        'product_variant_id',
        'quantity_on_hand',
        'quantity_reserved',
        'reorder_level',
        'max_stock_level',
        'branch_price',
        'is_available',
        'last_restocked_at'
    ];

    protected $casts = [
        'quantity_on_hand' => 'integer',
        'quantity_reserved' => 'integer',
        'reorder_level' => 'integer',
        'max_stock_level' => 'integer',
        'branch_price' => 'decimal:2',
        'is_available' => 'boolean',
        'last_restocked_at' => 'date'
    ];

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)
                    ->where('quantity_on_hand', '>', 0);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('quantity_on_hand <= reorder_level');
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    // Helper methods
    public function getQuantityAvailableAttribute()
    {
        return max(0, $this->quantity_on_hand - $this->quantity_reserved);
    }

    public function isLowStock()
    {
        return $this->quantity_on_hand <= $this->reorder_level;
    }

    public function canFulfillQuantity($requestedQuantity)
    {
        return $this->quantity_available >= $requestedQuantity;
    }

    public function reserveQuantity($quantity)
    {
        if ($this->canFulfillQuantity($quantity)) {
            $this->quantity_reserved += $quantity;
            $this->save();
            return true;
        }
        return false;
    }

    public function releaseQuantity($quantity)
    {
        $this->quantity_reserved = max(0, $this->quantity_reserved - $quantity);
        $this->save();
    }

    public function adjustStock($quantity, $type = 'adjustment')
    {
        $previousQuantity = $this->quantity_on_hand;
        $this->quantity_on_hand = max(0, $this->quantity_on_hand + $quantity);
        $this->save();

        // Create stock movement record
        ProductStockMovement::create([
            'branch_id' => $this->branch_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'movement_type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $previousQuantity,
            'quantity_after' => $this->quantity_on_hand,
            'movement_date' => now(),
        ]);
    }

    public function getEffectivePriceAttribute()
    {
        if ($this->branch_price) {
            return $this->branch_price;
        }
        
        if ($this->productVariant) {
            return $this->productVariant->price;
        }
        
        return $this->product->base_price;
    }
}