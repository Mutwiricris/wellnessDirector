<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'branch_id',
        'transaction_type',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'staff_id',
        'notes',
        'transaction_date'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'reference_id' => 'integer',
        'transaction_date' => 'datetime'
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('transaction_type', $type);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    public function getTotalValue(): float
    {
        return abs($this->quantity) * ($this->unit_cost ?? 0);
    }

    public static function getTransactionTypes(): array
    {
        return [
            'in' => 'Stock In',
            'out' => 'Stock Out',
            'adjustment' => 'Stock Adjustment',
            'waste' => 'Waste/Loss'
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return static::getTransactionTypes()[$this->transaction_type] ?? ucfirst($this->transaction_type);
    }

    public function getFormattedQuantityAttribute(): string
    {
        $sign = $this->transaction_type === 'out' || $this->quantity < 0 ? '-' : '+';
        return $sign . abs($this->quantity);
    }
}