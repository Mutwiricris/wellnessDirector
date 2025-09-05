<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Booking extends Model
{
    protected $fillable = [
        'booking_reference',
        'branch_id',
        'service_id',
        'client_id',
        'staff_id',
        'appointment_date',
        'start_time',
        'end_time',
        'status',
        'notes',
        'total_amount',
        'payment_status',
        'payment_method',
        'mpesa_transaction_id',
        'cancellation_reason',
        'cancelled_at',
        'confirmed_at',
        'service_started_at',
        'service_completed_at'
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'total_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'service_started_at' => 'datetime',
        'service_completed_at' => 'datetime',
    ];
    
    // Handle time fields manually to avoid casting issues
    protected $dates = [
        'appointment_date',
        'cancelled_at',
        'confirmed_at',
        'service_started_at',
        'service_completed_at',
        'created_at',
        'updated_at'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($booking) {
            if (!$booking->booking_reference) {
                $booking->booking_reference = $booking->generateBookingReference();
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', now()->toDateString())
                    ->whereIn('status', ['pending', 'confirmed']);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('appointment_date', $date);
    }

    public function scopeForStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['cancelled', 'no_show']);
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) && 
               $this->appointment_date >= now()->addHours(24)->toDateString();
    }

    public function canBeStarted(): bool
    {
        return $this->status === 'confirmed';
        // Allow starting confirmed bookings regardless of date
        // Payment validation removed - allow starting with payment warning
    }

    public function canBeCompleted(): bool
    {
        return $this->status === 'in_progress' &&
               $this->hasValidPayment();
    }
    
    public function canBeConfirmed(): bool
    {
        return $this->status === 'pending' && 
               $this->hasValidPayment();
    }
    
    public function hasValidPayment(): bool
    {
        return $this->payment_status === 'completed' || 
               ($this->payment && $this->payment->isCompleted());
    }
    
    public function requiresPayment(): bool
    {
        return $this->payment_status !== 'completed' && 
               (!$this->payment || !$this->payment->isCompleted());
    }
    
    public function getPaymentStatusMessage(): string
    {
        if ($this->hasValidPayment()) {
            return 'Payment completed';
        }
        
        if ($this->payment && $this->payment->isPending()) {
            return 'Payment pending verification';
        }
        
        if ($this->payment && $this->payment->isFailed()) {
            return 'Payment failed - requires retry';
        }
        
        return 'Payment required to proceed';
    }

    public function isUpcoming(): bool
    {
        return $this->appointment_date > now()->toDateString() && 
               in_array($this->status, ['pending', 'confirmed']);
    }

    public function isToday(): bool
    {
        return $this->appointment_date->isToday();
    }

    public function updateStatusWithPayment(string $status): void
    {
        $oldStatus = $this->status;
        $this->update(['status' => $status]);
        
        // Auto-update payment status based on booking status
        if ($this->payment) {
            if ($status === 'completed' && $this->payment->isPending()) {
                $this->payment->markAsCompleted();
                $this->update(['payment_status' => 'completed']);
            } elseif ($status === 'cancelled' && $this->payment->isPending()) {
                $this->payment->markAsFailed();
                $this->update(['payment_status' => 'failed']);
            }
        } else {
            // If no payment record exists, create one for completed services
            if ($status === 'completed') {
                $this->createPaymentRecord();
            }
        }
        
        // Set timestamp fields based on status changes
        $this->updateStatusTimestamps($status, $oldStatus);
    }

    private function generateBookingReference(): string
    {
        do {
            $reference = 'SPA' . strtoupper(Str::random(6));
        } while (static::where('booking_reference', $reference)->exists());
        
        return $reference;
    }

    public function getFormattedTimeSlotAttribute(): string
    {
        return $this->start_time . ' - ' . $this->end_time;
    }

    public function getDurationInMinutesAttribute(): int
    {
        $start = \Carbon\Carbon::createFromFormat('H:i', $this->start_time);
        $end = \Carbon\Carbon::createFromFormat('H:i', $this->end_time);
        return $start->diffInMinutes($end);
    }
    
    private function createPaymentRecord(): void
    {
        Payment::create([
            'booking_id' => $this->id,
            'branch_id' => $this->branch_id,
            'amount' => $this->total_amount,
            'payment_method' => $this->payment_method ?? 'cash',
            'status' => 'completed',
            'processed_at' => now()
        ]);
        
        $this->update(['payment_status' => 'completed']);
    }
    
    private function updateStatusTimestamps(string $newStatus, string $oldStatus): void
    {
        $updates = [];
        
        if ($newStatus === 'confirmed' && $oldStatus !== 'confirmed') {
            $updates['confirmed_at'] = now();
        }
        
        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            $updates['cancelled_at'] = now();
        }
        
        if (!empty($updates)) {
            $this->update($updates);
        }
    }
    
    public function getStatusColorAttribute(): string
    {
        $colors = [
            'pending' => 'yellow',
            'confirmed' => 'blue',
            'in_progress' => 'purple',
            'completed' => 'green',
            'cancelled' => 'red',
            'no_show' => 'gray'
        ];
        
        return $colors[$this->status] ?? 'gray';
    }
    
    public function getPaymentStatusColorAttribute(): string
    {
        $colors = [
            'pending' => 'yellow',
            'completed' => 'green',
            'failed' => 'red',
            'refunded' => 'gray'
        ];
        
        return $colors[$this->payment_status] ?? 'gray';
    }
    
    public function getFormattedStatusAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }
    
    public function getFormattedPaymentStatusAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->payment_status));
    }
    
    public function canEditPayment(): bool
    {
        return in_array($this->status, ['confirmed', 'in_progress', 'completed']) && 
               $this->payment_status !== 'refunded';
    }
    
    // public function requiresPayment(): bool
    // {
    //     return $this->status === 'completed' && $this->payment_status === 'pending';
    // }
    
    // public function hasValidPayment(): bool
    // {
    //     return $this->payment_status === 'completed' && $this->payment;
    // }
    
    // public function canBeConfirmed(): bool
    // {
    //     return $this->status === 'pending' && $this->hasValidPayment();
    // }
    
    // public function canBeCompletedWithPayment(): bool
    // {
    //     return $this->status === 'in_progress' && $this->hasValidPayment();
    // }
    
    public function needsPaymentBeforeConfirmation(): bool
    {
        return $this->status === 'pending' && !$this->hasValidPayment();
    }
    
    public function needsPaymentBeforeCompletion(): bool
    {
        return $this->status === 'in_progress' && !$this->hasValidPayment();
    }

    /**
     * Start the service for this booking
     */
    public function startService(): bool
    {
        if (!$this->canBeStarted()) {
            return false;
        }

        $this->update([
            'status' => 'in_progress',
            'service_started_at' => now(),
        ]);

        return true;
    }

    /**
     * Complete the service for this booking
     */
    public function completeService(): bool
    {
        if (!$this->canBeCompleted()) {
            return false;
        }

        $this->update([
            'status' => 'completed',
            'service_completed_at' => now(),
        ]);

        return true;
    }

    /**
     * Get the service duration in minutes
     */
    public function getServiceDuration(): ?int
    {
        if (!$this->service_started_at || !$this->service_completed_at) {
            return null;
        }

        $minutes = $this->service_started_at->diffInMinutes($this->service_completed_at);
        // If less than 1 minute, return 1 to show some duration
        return max(1, $minutes);
    }

    /**
     * Get booking status options for forms
     */
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
}
