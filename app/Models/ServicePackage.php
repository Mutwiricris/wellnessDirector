<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ServicePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'description',
        'package_code',
        'package_type',
        'total_price',
        'discount_amount',
        'final_price',
        'validity_days',
        'max_bookings',
        'is_couple_package',
        'requires_consecutive_booking',
        'booking_interval_days',
        'status',
        'image_path',
        'terms_conditions',
        'popular',
        'featured',
        'created_by_staff_id'
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_price' => 'decimal:2',
        'is_couple_package' => 'boolean',
        'requires_consecutive_booking' => 'boolean',
        'popular' => 'boolean',
        'featured' => 'boolean',
        'terms_conditions' => 'array'
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_package_items')
                    ->withPivot(['quantity', 'order', 'is_required', 'notes'])
                    ->withTimestamps()
                    ->orderBy('service_package_items.order');
    }

    public function packageSales(): HasMany
    {
        return $this->hasMany(PackageSale::class);
    }

    public function packageBookings(): HasMany
    {
        return $this->hasMany(PackageBooking::class);
    }

    // Generate unique package code
    public static function generatePackageCode(): string
    {
        do {
            $code = 'PKG' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        } while (self::where('package_code', $code)->exists());
        
        return $code;
    }

    // Check if package is currently active
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // Check if package is available for purchase
    public function isAvailable(): bool
    {
        return $this->isActive() && !$this->isSoldOut();
    }

    // Check if package has sold out (if there's a limit)
    public function isSoldOut(): bool
    {
        // This would be implemented if there's a purchase limit
        return false;
    }

    // Calculate discount percentage
    public function getDiscountPercentage(): float
    {
        if ($this->total_price <= 0) return 0;
        
        return ($this->discount_amount / $this->total_price) * 100;
    }

    // Calculate savings amount
    public function getSavingsAmount(): float
    {
        return $this->total_price - $this->final_price;
    }

    // Get total duration of all services
    public function getTotalDuration(): int
    {
        return $this->services->sum(function ($service) {
            $quantity = $service->pivot->quantity ?? 1;
            return $service->duration_minutes * $quantity;
        });
    }

    // Get package types
    public static function getPackageTypes(): array
    {
        return [
            'wellness' => 'Wellness Package',
            'beauty' => 'Beauty Package',
            'spa' => 'Spa Package',
            'couples' => 'Couples Package',
            'premium' => 'Premium Package',
            'seasonal' => 'Seasonal Package',
            'membership' => 'Membership Package'
        ];
    }

    // Get status options
    public static function getStatusOptions(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'draft' => 'Draft',
            'expired' => 'Expired'
        ];
    }

    // Check if package can be booked by customer
    public function canBeBookedBy(?int $customerId = null): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        // Check if customer has active package bookings that would conflict
        if ($customerId && $this->requires_consecutive_booking) {
            $existingBooking = PackageBooking::where('user_id', $customerId)
                ->where('service_package_id', $this->id)
                ->where('status', 'active')
                ->exists();
                
            if ($existingBooking) {
                return false;
            }
        }

        return true;
    }

    // Get required services (services marked as required in pivot)
    public function getRequiredServices()
    {
        return $this->services()->wherePivot('is_required', true)->get();
    }

    // Get optional services
    public function getOptionalServices()
    {
        return $this->services()->wherePivot('is_required', false)->get();
    }

    // Check if all required services are available at branch
    public function hasRequiredServicesAvailable(): bool
    {
        $requiredServices = $this->getRequiredServices();
        
        foreach ($requiredServices as $service) {
            if (!$service->branches->contains($this->branch_id)) {
                return false;
            }
        }
        
        return true;
    }

    // Calculate estimated completion time
    public function getEstimatedCompletionTime(): int
    {
        $totalDuration = $this->getTotalDuration();
        
        // Add buffer time between services
        $serviceCount = $this->services->count();
        $bufferTime = $serviceCount > 1 ? ($serviceCount - 1) * 15 : 0; // 15 minutes buffer
        
        return $totalDuration + $bufferTime;
    }

    // Get package popularity score
    public function getPopularityScore(): int
    {
        $salesCount = $this->packageSales()->count();
        $completedBookings = $this->packageBookings()->where('status', 'completed')->count();
        
        return $salesCount + ($completedBookings * 2); // Weight completed bookings more
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePopular($query)
    {
        return $query->where('popular', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    public function scopeByBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('package_type', $type);
    }

    public function scopeCouplesPackages($query)
    {
        return $query->where('is_couple_package', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'active');
    }

    // Accessors
    public function getFormattedTotalPriceAttribute(): string
    {
        return 'KES ' . number_format($this->total_price, 2);
    }

    public function getFormattedFinalPriceAttribute(): string
    {
        return 'KES ' . number_format($this->final_price, 2);
    }

    public function getFormattedDiscountAmountAttribute(): string
    {
        return 'KES ' . number_format($this->discount_amount, 2);
    }

    public function getFormattedDiscountPercentageAttribute(): string
    {
        return number_format($this->getDiscountPercentage(), 1) . '%';
    }

    public function getFormattedDurationAttribute(): string
    {
        $minutes = $this->getTotalDuration();
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($hours > 0) {
            return $remainingMinutes > 0 ? "{$hours}h {$remainingMinutes}m" : "{$hours}h";
        }
        
        return "{$minutes}m";
    }

    // Boot method to auto-generate package code
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($package) {
            if (empty($package->package_code)) {
                $package->package_code = self::generatePackageCode();
            }
        });
    }
}