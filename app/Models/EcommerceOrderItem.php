<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EcommerceOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'branch_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_title',
        'sku',
        'quantity',
        'unit_price',
        'total_price',
        'cost_price',
        'product_snapshot',
        'special_instructions'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'product_snapshot' => 'array'
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'order_id');
    }

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

    // Helper methods
    public function getDisplayNameAttribute()
    {
        if ($this->variant_title) {
            return $this->product_name . ' - ' . $this->variant_title;
        }
        return $this->product_name;
    }

    public function getFormattedUnitPriceAttribute()
    {
        return number_format($this->unit_price, 2);
    }

    public function getFormattedTotalPriceAttribute()
    {
        return number_format($this->total_price, 2);
    }

    public function getProfitMarginAttribute()
    {
        if ($this->cost_price && $this->unit_price > $this->cost_price) {
            return $this->unit_price - $this->cost_price;
        }
        return 0;
    }

    public function getProfitMarginPercentageAttribute()
    {
        if ($this->cost_price && $this->cost_price > 0) {
            return (($this->unit_price - $this->cost_price) / $this->cost_price) * 100;
        }
        return 0;
    }

    public function getTotalProfitAttribute()
    {
        return $this->quantity * $this->profit_margin;
    }
}