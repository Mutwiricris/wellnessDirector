<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PackageSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_package_id',
        'user_id',
        'branch_id',
        'sale_reference',
        'original_price',
        'discount_applied',
        'final_price',
        'payment_status',
        'payment_method',
        'purchased_at',
        'expires_at',
        'status',
        'sold_by_staff_id',
        'notes',
        'gift_recipient_name',
        'gift_recipient_phone',
        'gift_recipient_email',
        'is_gift',
        'redemption_code'
    ];

    protected $casts = [
        'original_price' => 'decimal:2',
        'discount_applied' => 'decimal:2',
        'final_price' => 'decimal:2',
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_gift' => 'boolean'
    ];

    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function soldByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'sold_by_staff_id');
    }

    public function packageBookings(): HasMany
    {
        return $this->hasMany(PackageBooking::class);
    }

    // Generate unique sale reference
    public static function generateSaleReference(): string
    {
        do {
            $reference = 'PS' . date('Ymd') . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        } while (self::where('sale_reference', $reference)->exists());
        
        return $reference;
    }

    // Generate unique redemption code for gifts
    public static function generateRedemptionCode(): string
    {
        do {
            $code = 'GIFT' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        } while (self::where('redemption_code', $code)->exists());
        
        return $code;
    }

    // Check if package sale is active
    public function isActive(): bool
    {
        return $this->status === 'active' 
            && $this->payment_status === 'completed'
            && $this->expires_at > now();
    }

    // Check if package sale has expired
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    // Check if package is fully utilized
    public function isFullyUtilized(): bool
    {
        $totalServices = $this->servicePackage->services->sum('pivot.quantity');
        $usedServices = $this->packageBookings()
            ->where('status', 'completed')
            ->count();
            
        return $usedServices >= $totalServices;
    }

    // Get remaining services count
    public function getRemainingServicesCount(): int
    {
        $totalServices = $this->servicePackage->services->sum('pivot.quantity');
        $usedServices = $this->packageBookings()
            ->where('status', 'completed')
            ->count();
            
        return max(0, $totalServices - $usedServices);
    }

    // Get usage percentage
    public function getUsagePercentage(): float
    {
        $totalServices = $this->servicePackage->services->sum('pivot.quantity');
        if ($totalServices <= 0) return 0;
        
        $usedServices = $this->packageBookings()
            ->where('status', 'completed')
            ->count();
            
        return ($usedServices / $totalServices) * 100;
    }

    // Get days until expiry
    public function getDaysUntilExpiry(): int
    {
        return $this->expires_at->diffInDays(now(), false);
    }

    // Check if customer can book a service from this package
    public function canBookService(int $serviceId): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        // Check if service is part of the package
        $packageService = $this->servicePackage->services()
            ->where('services.id', $serviceId)
            ->first();
            
        if (!$packageService) {
            return false;
        }

        // Check if customer has remaining quota for this service
        $serviceQuantity = $packageService->pivot->quantity;
        $usedQuantity = $this->packageBookings()
            ->where('service_id', $serviceId)
            ->where('status', 'completed')
            ->count();
            
        return $usedQuantity < $serviceQuantity;
    }

    // Mark package as expired
    public function markAsExpired(): void
    {
        if ($this->isExpired() && $this->status === 'active') {
            $this->update(['status' => 'expired']);
        }
    }

    // Get payment status options
    public static function getPaymentStatusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'refunded' => 'Refunded'
        ];
    }

    // Get status options
    public static function getStatusOptions(): array
    {
        return [
            'active' => 'Active',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled',
            'fully_used' => 'Fully Used'
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('payment_status', 'completed')
                    ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('expires_at', '<=', now()->addDays($days))
                    ->where('status', 'active');
    }

    public function scopeByBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByCustomer($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeGifts($query)
    {
        return $query->where('is_gift', true);
    }

    public function scopeCompleted($query)
    {
        return $query->where('payment_status', 'completed');
    }

    // Accessors
    public function getFormattedOriginalPriceAttribute(): string
    {
        return 'KES ' . number_format($this->original_price, 2);
    }

    public function getFormattedFinalPriceAttribute(): string
    {
        return 'KES ' . number_format($this->final_price, 2);
    }

    public function getFormattedDiscountAppliedAttribute(): string
    {
        return 'KES ' . number_format($this->discount_applied, 2);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'expired' => 'danger',
            'cancelled' => 'gray',
            'fully_used' => 'info',
            default => 'gray'
        };
    }

    public function getPaymentStatusColorAttribute(): string
    {
        return match ($this->payment_status) {
            'completed' => 'success',
            'pending' => 'warning',
            'failed' => 'danger',
            'refunded' => 'gray',
            default => 'gray'
        };
    }

    // Boot method to auto-generate references
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($sale) {
            if (empty($sale->sale_reference)) {
                $sale->sale_reference = self::generateSaleReference();
            }
            
            if ($sale->is_gift && empty($sale->redemption_code)) {
                $sale->redemption_code = self::generateRedemptionCode();
            }
        });
    }
}