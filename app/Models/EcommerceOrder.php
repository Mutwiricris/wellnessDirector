<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EcommerceOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'branch_id',
        'customer_id',
        'status',
        'payment_status',
        'fulfillment_status',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'billing_address',
        'shipping_address',
        'customer_email',
        'customer_phone',
        'delivery_method',
        'special_instructions',
        'requested_delivery_date',
        'delivery_time_slot',
        'payment_method',
        'payment_reference',
        'paid_at',
        'tracking_number',
        'tracking_updates',
        'source',
        'meta_data',
        'notes'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'delivery_time_slot' => 'array',
        'tracking_updates' => 'array',
        'meta_data' => 'array',
        'requested_delivery_date' => 'datetime',
        'paid_at' => 'datetime'
    ];

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EcommerceOrderItem::class, 'order_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'order_id');
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

    public function scopeByPaymentStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    // Helper methods
    public function getStatusDisplayAttribute()
    {
        return match($this->status) {
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            default => ucfirst($this->status)
        };
    }

    public function getPaymentStatusDisplayAttribute()
    {
        return match($this->payment_status) {
            'pending' => 'Pending Payment',
            'paid' => 'Paid',
            'partial' => 'Partially Paid',
            'failed' => 'Payment Failed',
            'refunded' => 'Refunded',
            default => ucfirst($this->payment_status)
        };
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    public function canBeRefunded()
    {
        return $this->payment_status === 'paid' && 
               in_array($this->status, ['completed', 'delivered']);
    }

    public function isPaid()
    {
        return $this->payment_status === 'paid';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function getTotalItemsCountAttribute()
    {
        return $this->items->sum('quantity');
    }

    public function getFormattedTotalAttribute()
    {
        return number_format($this->total_amount, 2) . ' ' . $this->currency;
    }

    public function reserveInventory()
    {
        foreach ($this->items as $item) {
            $inventory = BranchProductInventory::where('branch_id', $this->branch_id)
                                               ->where('product_id', $item->product_id)
                                               ->where('product_variant_id', $item->product_variant_id)
                                               ->first();
            
            if ($inventory) {
                $inventory->reserveQuantity($item->quantity);
            }
        }
    }

    public function releaseInventory()
    {
        foreach ($this->items as $item) {
            $inventory = BranchProductInventory::where('branch_id', $this->branch_id)
                                               ->where('product_id', $item->product_id)
                                               ->where('product_variant_id', $item->product_variant_id)
                                               ->first();
            
            if ($inventory) {
                $inventory->releaseQuantity($item->quantity);
            }
        }
    }

    public function fulfillOrder()
    {
        foreach ($this->items as $item) {
            $inventory = BranchProductInventory::where('branch_id', $this->branch_id)
                                               ->where('product_id', $item->product_id)
                                               ->where('product_variant_id', $item->product_variant_id)
                                               ->first();
            
            if ($inventory) {
                // Release reserved quantity and reduce actual stock
                $inventory->releaseQuantity($item->quantity);
                $inventory->adjustStock(-$item->quantity, 'sale');
            }
        }
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($order) {
            if (!$order->order_number) {
                $order->order_number = 'ORD-' . strtoupper(uniqid());
            }
        });
    }
}