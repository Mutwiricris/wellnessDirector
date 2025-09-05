<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class PosPromotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'description',
        'promotion_type',
        'discount_type',
        'discount_value',
        'applicable_services',
        'applicable_categories',
        'time_restrictions',
        'conditions',
        'usage_limit',
        'used_count',
        'starts_at',
        'expires_at',
        'status',
        'auto_apply',
        'priority',
        'created_by_staff_id'
    ];

    protected $casts = [
        'applicable_services' => 'array',
        'applicable_categories' => 'array',
        'time_restrictions' => 'array',
        'conditions' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'discount_value' => 'decimal:2',
        'auto_apply' => 'boolean'
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    // Check if promotion is currently valid
    public function isValid(): bool
    {
        return $this->status === 'active' 
            && $this->starts_at <= now() 
            && $this->expires_at >= now()
            && ($this->usage_limit === null || $this->used_count < $this->usage_limit);
    }

    // Check if promotion is expired
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    // Check if promotion can be applied to specific services
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

        // Check service categories
        if (!empty($this->applicable_categories)) {
            $services = Service::whereIn('id', $serviceIds)->get();
            $serviceCategories = $services->pluck('category')->unique()->toArray();
            return !empty(array_intersect($serviceCategories, $this->applicable_categories));
        }

        return false;
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

    // Check conditions
    public function meetsConditions(float $subtotal, array $cartData = []): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        $conditions = $this->conditions;

        // Check minimum amount
        if (!empty($conditions['min_amount']) && $subtotal < $conditions['min_amount']) {
            return false;
        }

        // Check minimum items
        if (!empty($conditions['min_items'])) {
            $itemCount = array_sum(array_column($cartData, 'quantity'));
            if ($itemCount < $conditions['min_items']) {
                return false;
            }
        }

        // Check specific service requirements
        if (!empty($conditions['required_services'])) {
            $cartServiceIds = array_column($cartData, 'id');
            $hasRequiredServices = !empty(array_intersect($conditions['required_services'], $cartServiceIds));
            if (!$hasRequiredServices) {
                return false;
            }
        }

        return true;
    }

    // Calculate discount amount
    public function calculateDiscountAmount(float $subtotal, array $cartData = []): float
    {
        if (!$this->isValid() || !$this->isValidAtCurrentTime() || !$this->meetsConditions($subtotal, $cartData)) {
            return 0;
        }

        $discountAmount = 0;

        switch ($this->discount_type) {
            case 'percentage':
                $discountAmount = $subtotal * ($this->discount_value / 100);
                break;

            case 'fixed_amount':
                $discountAmount = $this->discount_value;
                break;

            case 'buy_one_get_one':
                // BOGO logic - find eligible services and apply discount
                $discountAmount = $this->calculateBOGODiscount($cartData);
                break;
        }

        // Don't exceed the subtotal
        return min($discountAmount, $subtotal);
    }

    // Calculate BOGO discount
    private function calculateBOGODiscount(array $cartData): float
    {
        $discount = 0;
        $applicableItems = [];

        // Find items that qualify for BOGO
        foreach ($cartData as $item) {
            if ($this->canApplyToServices([$item['id']])) {
                $applicableItems[] = $item;
            }
        }

        // Sort by price (descending) to give free items with lower value
        usort($applicableItems, function($a, $b) {
            return $b['price'] <=> $a['price'];
        });

        // Apply BOGO logic
        for ($i = 0; $i < count($applicableItems); $i += 2) {
            if (isset($applicableItems[$i + 1])) {
                // Give the cheaper one free
                $discount += min($applicableItems[$i]['price'], $applicableItems[$i + 1]['price']);
            }
        }

        return $discount;
    }

    // Apply promotion and track usage
    public function applyPromotion(float $subtotal, array $cartData = []): float
    {
        $discountAmount = $this->calculateDiscountAmount($subtotal, $cartData);

        if ($discountAmount > 0) {
            $this->increment('used_count');
        }

        return $discountAmount;
    }

    // Get eligible promotions for current time and conditions
    public static function getEligiblePromotions(int $branchId, float $subtotal, array $cartData = []): array
    {
        $serviceIds = array_column($cartData, 'id');

        return self::where('branch_id', $branchId)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>=', now())
            ->where(function($query) {
                $query->whereNull('usage_limit')
                      ->orWhereRaw('used_count < usage_limit');
            })
            ->orderBy('priority', 'desc')
            ->orderBy('discount_value', 'desc')
            ->get()
            ->filter(function($promotion) use ($subtotal, $cartData, $serviceIds) {
                return $promotion->isValidAtCurrentTime() 
                    && $promotion->canApplyToServices($serviceIds)
                    && $promotion->meetsConditions($subtotal, $cartData);
            })
            ->values()
            ->toArray();
    }

    // Get auto-apply promotions
    public static function getAutoApplyPromotions(int $branchId, float $subtotal, array $cartData = []): array
    {
        return collect(self::getEligiblePromotions($branchId, $subtotal, $cartData))
            ->where('auto_apply', true)
            ->toArray();
    }

    // Mark promotion as expired (scheduled job)
    public function markAsExpired(): void
    {
        if ($this->isExpired() && $this->status === 'active') {
            $this->update(['status' => 'expired']);
        }
    }

    // Get promotion types
    public static function getPromotionTypes(): array
    {
        return [
            'happy_hour' => 'Happy Hour',
            'seasonal' => 'Seasonal Promotion',
            'package_deal' => 'Package Deal',
            'loyalty_bonus' => 'Loyalty Bonus',
            'referral' => 'Referral Promotion'
        ];
    }

    // Get discount types
    public static function getDiscountTypes(): array
    {
        return [
            'percentage' => 'Percentage (%)',
            'fixed_amount' => 'Fixed Amount (KES)',
            'buy_one_get_one' => 'Buy One Get One'
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

    public function scopeAutoApply($query)
    {
        return $query->where('auto_apply', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('promotion_type', $type);
    }

    // Get formatted discount value
    public function getFormattedDiscountValueAttribute(): string
    {
        switch ($this->discount_type) {
            case 'percentage':
                return $this->discount_value . '%';
            case 'fixed_amount':
                return 'KES ' . number_format($this->discount_value, 2);
            case 'buy_one_get_one':
                return 'BOGO';
            default:
                return $this->discount_value;
        }
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