<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_number',
        'from_branch_id',
        'to_branch_id',
        'status',
        'requested_by_staff_id',
        'sent_by_staff_id',
        'received_by_staff_id',
        'notes',
        'requested_at',
        'sent_at',
        'received_at'
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'sent_at' => 'datetime',
        'received_at' => 'datetime'
    ];

    // Relationships
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function requestedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'requested_by_staff_id');
    }

    public function sentByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'sent_by_staff_id');
    }

    public function receivedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'received_by_staff_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductTransferItem::class, 'transfer_id');
    }

    // Scopes
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('from_branch_id', $branchId)
                    ->orWhere('to_branch_id', $branchId);
    }

    public function scopeFromBranch($query, $branchId)
    {
        return $query->where('from_branch_id', $branchId);
    }

    public function scopeToBranch($query, $branchId)
    {
        return $query->where('to_branch_id', $branchId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInTransit($query)
    {
        return $query->where('status', 'in_transit');
    }

    // Helper methods
    public function getStatusDisplayAttribute()
    {
        return match($this->status) {
            'pending' => 'Pending',
            'in_transit' => 'In Transit',
            'received' => 'Received',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status)
        };
    }

    public function canBeCancelled()
    {
        return $this->status === 'pending';
    }

    public function canBeSent()
    {
        return $this->status === 'pending';
    }

    public function canBeReceived()
    {
        return $this->status === 'in_transit';
    }

    public function markAsSent($staffId)
    {
        $this->update([
            'status' => 'in_transit',
            'sent_by_staff_id' => $staffId,
            'sent_at' => now()
        ]);

        // Reduce stock from source branch and create movement records
        foreach ($this->items as $item) {
            $inventory = BranchProductInventory::where('branch_id', $this->from_branch_id)
                                               ->where('product_id', $item->product_id)
                                               ->where('product_variant_id', $item->product_variant_id)
                                               ->first();
            
            if ($inventory) {
                $inventory->adjustStock(-$item->sent_quantity, 'transfer_out');
                
                // Update sent quantity on item
                $item->update(['sent_quantity' => $item->requested_quantity]);
            }
        }
    }

    public function markAsReceived($staffId, $receivedQuantities = [])
    {
        $this->update([
            'status' => 'received',
            'received_by_staff_id' => $staffId,
            'received_at' => now()
        ]);

        // Add stock to destination branch and create movement records
        foreach ($this->items as $item) {
            $receivedQty = $receivedQuantities[$item->id] ?? $item->sent_quantity;
            
            $inventory = BranchProductInventory::firstOrCreate([
                'branch_id' => $this->to_branch_id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
            ], [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'reorder_level' => 10,
                'is_available' => true
            ]);
            
            $inventory->adjustStock($receivedQty, 'transfer_in');
            
            // Update received quantity on item
            $item->update(['received_quantity' => $receivedQty]);
        }
    }

    public function getTotalItemsCountAttribute()
    {
        return $this->items->sum('requested_quantity');
    }

    public function getTotalSentCountAttribute()
    {
        return $this->items->sum('sent_quantity');
    }

    public function getTotalReceivedCountAttribute()
    {
        return $this->items->sum('received_quantity');
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($transfer) {
            if (!$transfer->transfer_number) {
                $transfer->transfer_number = 'TRF-' . strtoupper(uniqid());
            }
            
            if (!$transfer->requested_at) {
                $transfer->requested_at = now();
            }
        });
    }
}