<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_id',
        'product_id',
        'product_variant_id',
        'requested_quantity',
        'sent_quantity',
        'received_quantity',
        'unit_cost',
        'notes'
    ];

    protected $casts = [
        'requested_quantity' => 'integer',
        'sent_quantity' => 'integer',
        'received_quantity' => 'integer',
        'unit_cost' => 'decimal:2'
    ];

    // Relationships
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(ProductTransfer::class, 'transfer_id');
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

    public function getVarianceAttribute()
    {
        return $this->received_quantity - $this->sent_quantity;
    }

    public function hasVariance()
    {
        return $this->variance !== 0;
    }

    public function isShortage()
    {
        return $this->variance < 0;
    }

    public function isOverage()
    {
        return $this->variance > 0;
    }

    public function getTotalCostAttribute()
    {
        return $this->received_quantity * $this->unit_cost;
    }

    public function getCompletionPercentageAttribute()
    {
        if ($this->requested_quantity == 0) {
            return 0;
        }
        
        return ($this->received_quantity / $this->requested_quantity) * 100;
    }
}