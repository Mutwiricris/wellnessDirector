<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'branch_id',
        'supplier_id',
        'status',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'total_amount',
        'order_date',
        'expected_delivery_date',
        'received_date',
        'created_by_staff_id',
        'notes'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'received_date' => 'date'
    ];

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // Scopes
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForSupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'sent', 'confirmed']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('expected_delivery_date', '<', now())
                    ->whereIn('status', ['sent', 'confirmed']);
    }

    // Helper methods
    public function getStatusDisplayAttribute()
    {
        return match($this->status) {
            'draft' => 'Draft',
            'sent' => 'Sent to Supplier',
            'confirmed' => 'Confirmed',
            'received' => 'Received',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status)
        };
    }

    public function canBeEdited()
    {
        return $this->status === 'draft';
    }

    public function canBeSent()
    {
        return $this->status === 'draft';
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['draft', 'sent', 'confirmed']);
    }

    public function canBeReceived()
    {
        return $this->status === 'confirmed';
    }

    public function isOverdue()
    {
        return $this->expected_delivery_date < now() && 
               in_array($this->status, ['sent', 'confirmed']);
    }

    public function getTotalItemsCountAttribute()
    {
        return $this->items->sum('ordered_quantity');
    }

    public function getTotalReceivedCountAttribute()
    {
        return $this->items->sum('received_quantity');
    }

    public function getReceivalPercentageAttribute()
    {
        $totalOrdered = $this->total_items_count;
        
        if ($totalOrdered === 0) {
            return 0;
        }
        
        return ($this->total_received_count / $totalOrdered) * 100;
    }

    public function isFullyReceived()
    {
        return $this->total_items_count > 0 && 
               $this->total_received_count >= $this->total_items_count;
    }

    public function isPartiallyReceived()
    {
        return $this->total_received_count > 0 && 
               $this->total_received_count < $this->total_items_count;
    }

    public function markAsReceived($receivedItems = [])
    {
        foreach ($this->items as $item) {
            $receivedQty = $receivedItems[$item->id] ?? $item->ordered_quantity;
            
            // Update received quantity
            $item->update(['received_quantity' => $receivedQty]);
            
            // Add to branch inventory
            $inventory = BranchProductInventory::firstOrCreate([
                'branch_id' => $this->branch_id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
            ], [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'reorder_level' => 10,
                'is_available' => true
            ]);
            
            $inventory->adjustStock($receivedQty, 'in');
            $inventory->update(['last_restocked_at' => now()]);
        }

        $this->update([
            'status' => 'received',
            'received_date' => now()
        ]);
    }

    public function getFormattedTotalAttribute()
    {
        return number_format($this->total_amount, 2);
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($purchaseOrder) {
            if (!$purchaseOrder->po_number) {
                $purchaseOrder->po_number = 'PO-' . strtoupper(uniqid());
            }
            
            if (!$purchaseOrder->order_date) {
                $purchaseOrder->order_date = now();
            }
        });
    }
}