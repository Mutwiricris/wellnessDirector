<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductStockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'product_id',
        'product_variant_id',
        'movement_type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'reference_type',
        'reference_id',
        'staff_id',
        'notes',
        'movement_date'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'unit_cost' => 'decimal:2',
        'movement_date' => 'datetime'
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

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    // Polymorphic relationship to reference
    public function reference()
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('movement_type', $type);
    }

    public function scopeIncoming($query)
    {
        return $query->whereIn('movement_type', ['in', 'transfer_in', 'return']);
    }

    public function scopeOutgoing($query)
    {
        return $query->whereIn('movement_type', ['out', 'transfer_out', 'sale', 'waste']);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('movement_date', [$startDate, $endDate]);
    }

    // Helper methods
    public function getMovementTypeDisplayAttribute()
    {
        return match($this->movement_type) {
            'in' => 'Stock In',
            'out' => 'Stock Out',
            'transfer_in' => 'Transfer In',
            'transfer_out' => 'Transfer Out',
            'adjustment' => 'Adjustment',
            'sale' => 'Sale',
            'return' => 'Return',
            'waste' => 'Waste/Damage',
            default => ucfirst($this->movement_type)
        };
    }

    public function isIncoming()
    {
        return in_array($this->movement_type, ['in', 'transfer_in', 'return']);
    }

    public function isOutgoing()
    {
        return in_array($this->movement_type, ['out', 'transfer_out', 'sale', 'waste']);
    }

    public function getTotalValueAttribute()
    {
        return abs($this->quantity) * ($this->unit_cost ?? 0);
    }
}