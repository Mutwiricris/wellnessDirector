<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PackageBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_sale_id',
        'service_package_id',
        'service_id',
        'user_id',
        'branch_id',
        'staff_id',
        'booking_reference',
        'appointment_date',
        'start_time',
        'end_time',
        'status',
        'notes',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'service_order'
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime'
    ];

    public function packageSale(): BelongsTo
    {
        return $this->belongsTo(PackageSale::class);
    }

    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    // Generate unique booking reference
    public static function generateBookingReference(): string
    {
        do {
            $reference = 'PB' . date('Ymd') . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        } while (self::where('booking_reference', $reference)->exists());
        
        return $reference;
    }

    // Check if booking is confirmed
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    // Check if booking is completed
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    // Check if booking is cancelled
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    // Check if booking is in progress
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    // Check if booking can be cancelled
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) 
            && $this->start_time > now();
    }

    // Check if booking can be rescheduled
    public function canBeRescheduled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) 
            && $this->start_time > now()->addHours(24); // 24 hours notice
    }

    // Mark booking as completed
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    // Cancel booking
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason
        ]);
    }

    // Reschedule booking
    public function reschedule(string $newDate, string $newStartTime, string $newEndTime): bool
    {
        if (!$this->canBeRescheduled()) {
            return false;
        }

        // Check for conflicts (this would need to be implemented based on your booking system)
        $hasConflict = self::where('staff_id', $this->staff_id)
            ->where('appointment_date', $newDate)
            ->where('id', '!=', $this->id)
            ->where(function($query) use ($newStartTime, $newEndTime) {
                $query->whereBetween('start_time', [$newStartTime, $newEndTime])
                      ->orWhereBetween('end_time', [$newStartTime, $newEndTime])
                      ->orWhere(function($q) use ($newStartTime, $newEndTime) {
                          $q->where('start_time', '<=', $newStartTime)
                            ->where('end_time', '>=', $newEndTime);
                      });
            })
            ->exists();

        if ($hasConflict) {
            return false;
        }

        $this->update([
            'appointment_date' => $newDate,
            'start_time' => $newStartTime,
            'end_time' => $newEndTime
        ]);

        return true;
    }

    // Get next booking in sequence (for consecutive packages)
    public function getNextBooking(): ?self
    {
        return self::where('package_sale_id', $this->package_sale_id)
            ->where('service_order', '>', $this->service_order)
            ->orderBy('service_order')
            ->first();
    }

    // Get previous booking in sequence
    public function getPreviousBooking(): ?self
    {
        return self::where('package_sale_id', $this->package_sale_id)
            ->where('service_order', '<', $this->service_order)
            ->orderBy('service_order', 'desc')
            ->first();
    }

    // Check if this is the first booking in the package
    public function isFirstBooking(): bool
    {
        return $this->service_order === 1;
    }

    // Check if this is the last booking in the package
    public function isLastBooking(): bool
    {
        $totalBookings = self::where('package_sale_id', $this->package_sale_id)->count();
        return $this->service_order === $totalBookings;
    }

    // Get status options
    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show'
        ];
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('appointment_date', today());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', today())
                    ->where('status', '!=', 'completed');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByStaff($query, int $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    public function scopeByCustomer($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByPackage($query, int $packageId)
    {
        return $query->where('service_package_id', $packageId);
    }

    // Accessors
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'confirmed' => 'info',
            'in_progress' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
            'no_show' => 'gray',
            default => 'gray'
        };
    }

    public function getFormattedDateTimeAttribute(): string
    {
        return $this->appointment_date->format('M j, Y') . ' at ' . $this->start_time->format('g:i A');
    }

    public function getDurationInMinutesAttribute(): int
    {
        return $this->start_time->diffInMinutes($this->end_time);
    }

    // Boot method to auto-generate booking reference
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($booking) {
            if (empty($booking->booking_reference)) {
                $booking->booking_reference = self::generateBookingReference();
            }
        });
    }
}