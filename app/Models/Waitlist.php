<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Waitlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'client_id',
        'service_id',
        'staff_id',
        'preferred_date',
        'preferred_start_time',
        'preferred_end_time',
        'alternative_dates',
        'alternative_staff',
        'status',
        'priority_score',
        'priority_level',
        'notification_method',
        'auto_book',
        'max_wait_hours',
        'notified_at',
        'responded_at',
        'expires_at',
        'response',
        'notes',
        'discount_offered',
        'discount_type',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'preferred_start_time' => 'datetime:H:i',
        'preferred_end_time' => 'datetime:H:i',
        'alternative_dates' => 'array',
        'alternative_staff' => 'array',
        'notified_at' => 'datetime',
        'responded_at' => 'datetime',
        'expires_at' => 'datetime',
        'auto_book' => 'boolean',
        'discount_offered' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'notified']);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())
                    ->where('status', '!=', 'expired');
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority_level', ['high', 'vip'])
                    ->orderBy('priority_score', 'desc');
    }

    public function scopeReadyForNotification($query)
    {
        return $query->where('status', 'pending')
                    ->where(function($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Accessors & Mutators
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getTimeWaitingAttribute(): string
    {
        return $this->created_at->diffForHumans(null, true);
    }

    public function getFormattedPreferredTimeAttribute(): string
    {
        if (!$this->preferred_start_time || !$this->preferred_end_time) {
            return 'Any time';
        }
        
        return $this->preferred_start_time->format('H:i') . ' - ' . $this->preferred_end_time->format('H:i');
    }

    public function getPriorityLabelAttribute(): string
    {
        $labels = [
            'low' => 'Low Priority',
            'medium' => 'Medium Priority', 
            'high' => 'High Priority',
            'vip' => 'VIP Customer'
        ];
        
        return $labels[$this->priority_level] ?? 'Medium Priority';
    }

    public function getPriorityColorAttribute(): string
    {
        $colors = [
            'low' => 'gray',
            'medium' => 'blue',
            'high' => 'orange', 
            'vip' => 'purple'
        ];
        
        return $colors[$this->priority_level] ?? 'blue';
    }

    /**
     * Business Logic Methods
     */
    public function calculatePriorityScore(): int
    {
        $score = 0;
        
        // Base score by priority level
        $levelScores = [
            'low' => 10,
            'medium' => 20,
            'high' => 40,
            'vip' => 80
        ];
        $score += $levelScores[$this->priority_level] ?? 20;
        
        // Add score based on how long they've been waiting
        $hoursWaiting = $this->created_at->diffInHours(now());
        $score += min($hoursWaiting, 48); // Max 48 points for time waiting
        
        // Add score for client loyalty (if we have booking history)
        $clientBookings = Booking::where('client_id', $this->client_id)
                                ->where('branch_id', $this->branch_id)
                                ->count();
        $score += min($clientBookings * 5, 30); // Max 30 points for loyalty
        
        // Add score if they're flexible with staff
        if (is_array($this->alternative_staff) && count($this->alternative_staff) > 1) {
            $score += 10;
        }
        
        // Add score if they're flexible with dates
        if (is_array($this->alternative_dates) && count($this->alternative_dates) > 0) {
            $score += 10;
        }
        
        return $score;
    }

    public function updatePriorityScore(): void
    {
        $this->update(['priority_score' => $this->calculatePriorityScore()]);
    }

    public function markAsNotified(string $method = null): void
    {
        $this->update([
            'status' => 'notified',
            'notified_at' => now(),
            'notification_method' => $method ?? $this->notification_method,
            'expires_at' => now()->addHours(2) // 2 hours to respond
        ]);
    }

    public function markAsConverted(Booking $booking): void
    {
        $this->update([
            'status' => 'converted',
            'responded_at' => now(),
            'response' => 'accepted',
            'notes' => "Converted to booking #{$booking->booking_reference}"
        ]);
    }

    public function markAsDeclined(string $reason = null): void
    {
        $this->update([
            'status' => 'declined',
            'responded_at' => now(),
            'response' => 'declined',
            'notes' => $reason
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => 'expired',
            'response' => 'no_response'
        ]);
    }

    public function extendExpiry(int $hours = 2): void
    {
        $this->update([
            'expires_at' => now()->addHours($hours)
        ]);
    }

    /**
     * Check if this waitlist entry matches available slot criteria
     */
    public function matchesSlot(Carbon $date, Carbon $startTime, Carbon $endTime, ?int $staffId = null): bool
    {
        // Check date match
        if (!$this->preferred_date->isSameDay($date)) {
            // Check alternative dates
            $alternativeDates = $this->alternative_dates ?? [];
            $dateMatch = false;
            foreach ($alternativeDates as $altDate) {
                if (Carbon::parse($altDate)->isSameDay($date)) {
                    $dateMatch = true;
                    break;
                }
            }
            if (!$dateMatch) {
                return false;
            }
        }

        // Check time overlap
        $preferredStart = $this->preferred_start_time ? 
            Carbon::parse($this->preferred_date->format('Y-m-d') . ' ' . $this->preferred_start_time->format('H:i')) : 
            null;
        $preferredEnd = $this->preferred_end_time ? 
            Carbon::parse($this->preferred_date->format('Y-m-d') . ' ' . $this->preferred_end_time->format('H:i')) : 
            null;

        if ($preferredStart && $preferredEnd) {
            // Check if times overlap
            if ($startTime->gte($preferredEnd) || $endTime->lte($preferredStart)) {
                return false;
            }
        }

        // Check staff match
        if ($staffId && $this->staff_id && $this->staff_id !== $staffId) {
            // Check alternative staff
            $alternativeStaff = $this->alternative_staff ?? [];
            if (!in_array($staffId, $alternativeStaff)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get service price safely
     */
    public function getServicePrice(): float
    {
        return $this->service ? $this->service->price : 0.00;
    }

    /**
     * Get service duration safely
     */
    public function getServiceDuration(): int
    {
        return $this->service ? $this->service->duration_minutes : 0;
    }

    /**
     * Get notification message for available slot
     */
    public function getNotificationMessage(Carbon $availableDate, Carbon $startTime, Carbon $endTime): string
    {
        $serviceName = $this->service ? $this->service->name : 'your requested service';
        $staffName = $this->staff ? $this->staff->name : 'our staff';
        $branchName = $this->branch->name;
        
        $message = "Good news! A slot is now available for {$serviceName} ";
        $message .= "with {$staffName} at {$branchName} ";
        $message .= "on {$availableDate->format('M j, Y')} ";
        $message .= "from {$startTime->format('g:i A')} to {$endTime->format('g:i A')}. ";
        
        if ($this->discount_offered) {
            $discountText = $this->discount_type === 'percentage' 
                ? $this->discount_offered . '% off'
                : 'KES ' . number_format($this->discount_offered, 2) . ' off';
            $message .= "Special offer: {$discountText}! ";
        }
        
        $message .= "Reply within 2 hours to secure this appointment.";
        
        return $message;
    }
}