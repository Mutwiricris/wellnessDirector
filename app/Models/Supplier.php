<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'contact_person',
        'tax_number',
        'payment_terms',
        'status',
        'meta_data'
    ];

    protected $casts = [
        'payment_terms' => 'array',
        'meta_data' => 'array'
    ];

    // Relationships
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Helper methods
    public function getTotalPurchaseOrdersAttribute()
    {
        return $this->purchaseOrders()->count();
    }

    public function getTotalPurchaseValueAttribute()
    {
        return $this->purchaseOrders()->sum('total_amount');
    }

    public function getRecentPurchaseOrdersAttribute()
    {
        return $this->purchaseOrders()
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();
    }

    public function getAveragePurchaseValueAttribute()
    {
        $totalOrders = $this->total_purchase_orders;
        
        if ($totalOrders === 0) {
            return 0;
        }
        
        return $this->total_purchase_value / $totalOrders;
    }

    public function hasActiveOrders()
    {
        return $this->purchaseOrders()
                    ->whereIn('status', ['draft', 'sent', 'confirmed'])
                    ->exists();
    }
}