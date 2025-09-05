<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class InventoryItem extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'sku',
        'category',
        'type',
        'description',
        'cost_price',
        'selling_price',
        'current_stock',
        'minimum_stock',
        'maximum_stock',
        'unit',
        'supplier_name',
        'supplier_contact',
        'last_restocked',
        'reorder_level',
        'track_expiry',
        'expiry_date',
        'is_active'
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'current_stock' => 'integer',
        'minimum_stock' => 'integer',
        'maximum_stock' => 'integer',
        'reorder_level' => 'decimal:2',
        'last_restocked' => 'date',
        'expiry_date' => 'date',
        'track_expiry' => 'boolean',
        'is_active' => 'boolean'
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('current_stock <= minimum_stock');
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('track_expiry', true)
            ->whereBetween('expiry_date', [now(), now()->addDays($days)]);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function isLowStock(): bool
    {
        return $this->current_stock <= $this->minimum_stock;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->track_expiry || !$this->expiry_date) {
            return false;
        }

        return $this->expiry_date->lte(now()->addDays($days));
    }

    public function isExpired(): bool
    {
        if (!$this->track_expiry || !$this->expiry_date) {
            return false;
        }

        return $this->expiry_date->lt(now());
    }

    public function addStock(int $quantity, string $notes = null, int $staffId = null): void
    {
        $this->current_stock += $quantity;
        $this->last_restocked = now();
        $this->save();

        $this->transactions()->create([
            'branch_id' => $this->branch_id,
            'transaction_type' => 'in',
            'quantity' => $quantity,
            'unit_cost' => $this->cost_price,
            'staff_id' => $staffId,
            'notes' => $notes,
            'transaction_date' => now()
        ]);
    }

    public function consumeStock(int $quantity, string $referenceType = null, int $referenceId = null, int $staffId = null): bool
    {
        if ($this->current_stock < $quantity) {
            return false;
        }

        $this->current_stock -= $quantity;
        $this->save();

        $this->transactions()->create([
            'branch_id' => $this->branch_id,
            'transaction_type' => 'out',
            'quantity' => -$quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'staff_id' => $staffId,
            'transaction_date' => now()
        ]);

        return true;
    }

    public function adjustStock(int $newQuantity, string $reason = null, int $staffId = null): void
    {
        $difference = $newQuantity - $this->current_stock;
        $this->current_stock = $newQuantity;
        $this->save();

        $this->transactions()->create([
            'branch_id' => $this->branch_id,
            'transaction_type' => 'adjustment',
            'quantity' => $difference,
            'staff_id' => $staffId,
            'notes' => $reason,
            'transaction_date' => now()
        ]);
    }

    public function getStockValue(): float
    {
        return $this->current_stock * $this->cost_price;
    }

    public function getStockStatus(): string
    {
        if ($this->current_stock <= 0) {
            return 'out_of_stock';
        }

        if ($this->isLowStock()) {
            return 'low_stock';
        }

        if ($this->maximum_stock && $this->current_stock >= $this->maximum_stock) {
            return 'overstock';
        }

        return 'in_stock';
    }

    public function getConsumptionRate(int $days = 30): float
    {
        $consumption = $this->transactions()
            ->where('transaction_type', 'out')
            ->where('transaction_date', '>=', now()->subDays($days))
            ->sum('quantity');

        return abs($consumption) / $days;
    }

    public function getProjectedStockoutDate(): ?Carbon
    {
        $consumptionRate = $this->getConsumptionRate();

        if ($consumptionRate <= 0) {
            return null;
        }

        $daysRemaining = $this->current_stock / $consumptionRate;
        return now()->addDays(ceil($daysRemaining));
    }

    public static function getCategories(): array
    {
        return [
            'products' => 'Retail Products',
            'supplies' => 'Service Supplies',
            'equipment' => 'Equipment & Tools',
            'consumables' => 'Consumables',
            'linens' => 'Linens & Towels'
        ];
    }

    public static function getTypes(): array
    {
        return [
            'consumable' => 'Consumable',
            'non-consumable' => 'Non-Consumable'
        ];
    }

    public static function getUnits(): array
    {
        return [
            'pieces' => 'Pieces',
            'bottles' => 'Bottles',
            'tubes' => 'Tubes',
            'jars' => 'Jars',
            'kg' => 'Kilograms',
            'g' => 'Grams',
            'liters' => 'Liters',
            'ml' => 'Milliliters',
            'sets' => 'Sets',
            'pairs' => 'Pairs'
        ];
    }

    public function getFormattedStockAttribute(): string
    {
        return $this->current_stock . ' ' . $this->unit;
    }

    public function getCategoryLabelAttribute(): string
    {
        return static::getCategories()[$this->category] ?? ucfirst($this->category);
    }

    public function getStockStatusLabelAttribute(): string
    {
        return match ($this->getStockStatus()) {
            'out_of_stock' => 'Out of Stock',
            'low_stock' => 'Low Stock',
            'overstock' => 'Overstock',
            default => 'In Stock'
        };
    }
}