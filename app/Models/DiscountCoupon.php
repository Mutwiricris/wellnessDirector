<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class DiscountCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'coupon_code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'minimum_order_amount',
        'maximum_discount_amount',
        'usage_limit',
        'usage_limit_per_customer',
        'used_count',
        'starts_at',
        'expires_at',
        'status',
        'applicable_services',
        'applicable_categories',
        'customer_restrictions',
        'time_restrictions',
        'stackable',
        'created_by_staff_id'
    ];

    protected $casts = [
        'applicable_services' => 'array',
        'applicable_categories' => 'array',
        'customer_restrictions' => 'array',
        'time_restrictions' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'discount_value' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'maximum_discount_amount' => 'decimal:2',
        'stackable' => 'boolean'
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    // Generate unique coupon code
    public static function generateCouponCode(string $prefix = 'SAVE'): string
    {
        do {
            $code = $prefix . rand(100, 999) . strtoupper(substr(md5(uniqid()), 0, 4));
        } while (self::where('coupon_code', $code)->exists());
        
        return $code;
    }

    // Check if coupon is currently valid
    public function isValid(): bool
    {
        return $this->status === 'active' 
            && $this->starts_at <= now() 
            && $this->expires_at >= now()
            && ($this->usage_limit === null || $this->used_count < $this->usage_limit);
    }

    // Check if coupon is expired
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    // Check if coupon has reached usage limit
    public function hasReachedUsageLimit(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }

    // Check if coupon can be applied to specific services
    public function canApplyToServices(array $serviceIds): bool
    {
        // If no service restrictions, can apply to all
        if (empty($this->applicable_services) && empty($this->applicable_categories)) {
            return true;
        }

        // Check specific service IDs
        if (!empty($this->applicable_services)) {
            return !empty(array_intersect($serviceIds, $this->applicable_services));
        }

        // Check service categories (would need to fetch service categories)
        if (!empty($this->applicable_categories)) {
            $services = Service::whereIn('id', $serviceIds)->get();
            $serviceCategories = $services->pluck('category')->unique()->toArray();
            return !empty(array_intersect($serviceCategories, $this->applicable_categories));
        }

        return false;
    }

    // Check if customer can use this coupon
    public function canBeUsedByCustomer(?int $customerId = null): bool
    {
        if (!$customerId) {
            return true; // Walk-in customers can use general coupons
        }

        // Check customer-specific usage limit
        $customerUsageCount = CouponUsage::where('discount_coupon_id', $this->id)
            ->where('customer_id', $customerId)
            ->count();

        return $customerUsageCount < $this->usage_limit_per_customer;
    }

    // Check time restrictions
    public function isValidAtCurrentTime(): bool
    {
        if (empty($this->time_restrictions)) {
            return true;
        }

        $now = now();
        $restrictions = $this->time_restrictions;

        // Check day of week restrictions
        if (!empty($restrictions['days_of_week'])) {
            $currentDay = strtolower($now->format('l')); // monday, tuesday, etc.
            if (!in_array($currentDay, $restrictions['days_of_week'])) {
                return false;
            }
        }

        // Check hour restrictions
        if (!empty($restrictions['hours'])) {
            $currentHour = $now->format('H:i');
            $startTime = $restrictions['hours']['start'] ?? '00:00';
            $endTime = $restrictions['hours']['end'] ?? '23:59';
            
            if ($currentHour < $startTime || $currentHour > $endTime) {
                return false;
            }
        }

        return true;
    }

    // Calculate discount amount for given subtotal
    public function calculateDiscountAmount(float $subtotal): float
    {
        if ($subtotal < $this->minimum_order_amount) {
            return 0;
        }

        $discountAmount = 0;

        if ($this->discount_type === 'percentage') {
            $discountAmount = $subtotal * ($this->discount_value / 100);
        } else {
            $discountAmount = $this->discount_value;
        }

        // Apply maximum discount limit if set
        if ($this->maximum_discount_amount !== null) {
            $discountAmount = min($discountAmount, $this->maximum_discount_amount);
        }

        // Don't exceed the subtotal
        return min($discountAmount, $subtotal);
    }

    // Apply coupon and track usage
    public function applyCoupon(float $subtotal, ?int $customerId = null, ?int $transactionId = null): float
    {
        if (!$this->isValid() || !$this->isValidAtCurrentTime()) {
            throw new \Exception('Coupon is not valid for use at this time');
        }

        if (!$this->canBeUsedByCustomer($customerId)) {
            throw new \Exception('Customer has exceeded usage limit for this coupon');
        }

        $discountAmount = $this->calculateDiscountAmount($subtotal);

        if ($discountAmount <= 0) {
            throw new \Exception('Order does not meet minimum amount requirement');
        }

        // Track coupon usage
        CouponUsage::create([
            'discount_coupon_id' => $this->id,
            'customer_id' => $customerId,
            'pos_transaction_id' => $transactionId,
            'discount_amount' => $discountAmount,
            'used_at' => now()
        ]);

        // Increment usage count
        $this->increment('used_count');

        return $discountAmount;
    }

    // Mark coupon as expired (scheduled job)
    public function markAsExpired(): void
    {
        if ($this->isExpired() && $this->status === 'active') {
            $this->update(['status' => 'expired']);
        }
    }

    // Get discount types
    public static function getDiscountTypes(): array
    {
        return [
            'percentage' => 'Percentage (%)',
            'fixed_amount' => 'Fixed Amount (KES)'
        ];
    }

    // Get status options
    public static function getStatusOptions(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'expired' => 'Expired'
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('starts_at', '<=', now())
                    ->where('expires_at', '>=', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeByBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')
                    ->where(function($q) {
                        $q->whereNull('usage_limit')
                          ->orWhereRaw('used_count < usage_limit');
                    });
    }

    // Get formatted discount value
    public function getFormattedDiscountValueAttribute(): string
    {
        if ($this->discount_type === 'percentage') {
            return $this->discount_value . '%';
        }
        return 'KES ' . number_format($this->discount_value, 2);
    }

    // Get usage percentage
    public function getUsagePercentageAttribute(): float
    {
        if ($this->usage_limit === null) return 0;
        return ($this->used_count / $this->usage_limit) * 100;
    }

    // Get remaining uses
    public function getRemainingUsesAttribute(): ?int
    {
        if ($this->usage_limit === null) return null;
        return max(0, $this->usage_limit - $this->used_count);
    }

    // Get days until expiry
    public function getDaysUntilExpiryAttribute(): int
    {
        return $this->expires_at->diffInDays(now(), false);
    }
}