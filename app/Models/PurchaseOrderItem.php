<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_variant_id',
        'ordered_quantity',
        'received_quantity',
        'unit_cost',
        'total_cost'
    ];

    protected $casts = [
        'ordered_quantity' => 'integer',
        'received_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2'
    ];

    // Relationships
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    // Helper methods
    public function getDisplayNameAttribute()
    {
        $name = $this->product->name;
        
        if ($this->productVariant) {
            $name .= ' - ' . $this->productVariant->full_title;
        }
        
        return $name;
    }

    public function getPendingQuantityAttribute()
    {
        return $this->ordered_quantity - $this->received_quantity;
    }

    public function getReceivalPercentageAttribute()
    {
        if ($this->ordered_quantity === 0) {
            return 0;
        }
        
        return ($this->received_quantity / $this->ordered_quantity) * 100;
    }

    public function isFullyReceived()
    {
        return $this->received_quantity >= $this->ordered_quantity;
    }

    public function isPartiallyReceived()
    {
        return $this->received_quantity > 0 && 
               $this->received_quantity < $this->ordered_quantity;
    }

    public function isPending()
    {
        return $this->received_quantity === 0;
    }

    public function getFormattedUnitCostAttribute()
    {
        return number_format($this->unit_cost, 2);
    }

    public function getFormattedTotalCostAttribute()
    {
        return number_format($this->total_cost, 2);
    }

    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($item) {
            $item->total_cost = $item->ordered_quantity * $item->unit_cost;
        });
    }
}