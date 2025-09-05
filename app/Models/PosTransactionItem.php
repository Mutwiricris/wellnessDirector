<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosTransactionItem extends Model
{
    protected $fillable = [
        'pos_transaction_id',
        'item_type',
        'item_id',
        'item_name',
        'item_description',
        'quantity',
        'unit_price',
        'discount_amount',
        'total_price',
        'assigned_staff_id',
        'duration_minutes',
        'service_start_time',
        'service_end_time',
        'sku',
        'cost_price'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'cost_price' => 'decimal:2',
        'service_start_time' => 'datetime',
        'service_end_time' => 'datetime'
    ];

    public function posTransaction(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_staff_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'item_id')->where('item_type', 'service');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id')->where('item_type', 'product');
    }

    public function scopeServices($query)
    {
        return $query->where('item_type', 'service');
    }

    public function scopeProducts($query)
    {
        return $query->where('item_type', 'product');
    }

    public function isService(): bool
    {
        return $this->item_type === 'service';
    }

    public function isProduct(): bool
    {
        return $this->item_type === 'product';
    }

    public function getItemModel()
    {
        return match ($this->item_type) {
            'service' => Service::find($this->item_id),
            'product' => InventoryItem::find($this->item_id),
            default => null
        };
    }

    public function getTotalPriceAfterDiscount(): float
    {
        return ($this->unit_price * $this->quantity) - $this->discount_amount;
    }

    public function getDiscountPercentage(): float
    {
        $originalTotal = $this->unit_price * $this->quantity;
        return $originalTotal > 0 ? ($this->discount_amount / $originalTotal) * 100 : 0;
    }

    public function getDurationFormatted(): string
    {
        if (!$this->duration_minutes) {
            return 'N/A';
        }

        $hours = intval($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0) {
            return $hours . 'h ' . ($minutes > 0 ? $minutes . 'm' : '');
        }

        return $minutes . 'm';
    }

    public function getServiceDuration(): ?int
    {
        if ($this->service_start_time && $this->service_end_time) {
            return $this->service_start_time->diffInMinutes($this->service_end_time);
        }

        return $this->duration_minutes;
    }

    public function isServiceInProgress(): bool
    {
        return $this->isService() && 
               $this->service_start_time && 
               !$this->service_end_time &&
               $this->service_start_time->lte(now());
    }

    public function isServiceCompleted(): bool
    {
        return $this->isService() && 
               $this->service_start_time && 
               $this->service_end_time;
    }

    public function startService(): void
    {
        if ($this->isService() && !$this->service_start_time) {
            $this->update(['service_start_time' => now()]);
        }
    }

    public function completeService(): void
    {
        if ($this->isService() && $this->service_start_time && !$this->service_end_time) {
            $this->update(['service_end_time' => now()]);
        }
    }

    public function consumeInventory(): void
    {
        if ($this->isProduct()) {
            $inventoryItem = $this->inventoryItem();
            if ($inventoryItem) {
                $inventoryItem->consumeStock(
                    $this->quantity,
                    'pos_transaction',
                    $this->pos_transaction_id,
                    $this->posTransaction->staff_id
                );
            }
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($item) {
            // Auto-consume inventory for products
            if ($item->isProduct()) {
                $item->consumeInventory();
            }
        });
    }
}