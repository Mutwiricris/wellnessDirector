<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'specialties',
        'bio',
        'profile_image',
        'experience_years',
        'hourly_rate',
        'status',
        'color'
    ];

    protected $casts = [
        'specialties' => 'array',
        'experience_years' => 'integer',
        'hourly_rate' => 'decimal:2'
    ];

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_staff')
                    ->withPivot('working_hours', 'is_primary_branch')
                    ->withTimestamps();
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'staff_services')
                    ->withPivot('proficiency_level')
                    ->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(StaffSchedule::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getFormattedExperienceAttribute()
    {
        return $this->experience_years . ' year' . ($this->experience_years !== 1 ? 's' : '') . ' experience';
    }
    
    // Performance Metrics
    public function getTotalBookings($startDate = null, $endDate = null)
    {
        $query = $this->bookings();
        
        if ($startDate) {
            $query->where('appointment_date', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('appointment_date', '<=', $endDate);
        }
        
        return $query->count();
    }
    
    public function getCompletedBookings($startDate = null, $endDate = null)
    {
        $query = $this->bookings()->where('status', 'completed');
        
        if ($startDate) {
            $query->where('appointment_date', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('appointment_date', '<=', $endDate);
        }
        
        return $query->count();
    }
    
    public function getCompletionRate($startDate = null, $endDate = null)
    {
        $total = $this->getTotalBookings($startDate, $endDate);
        $completed = $this->getCompletedBookings($startDate, $endDate);
        
        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }
    
    public function getTotalRevenue($startDate = null, $endDate = null)
    {
        $query = $this->bookings()->where('status', 'completed');
        
        if ($startDate) {
            $query->where('appointment_date', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('appointment_date', '<=', $endDate);
        }
        
        return $query->sum('total_amount') ?? 0;
    }
    
    public function getAverageRating()
    {
        // Assuming there's a reviews table/relationship
        return $this->bookings()
            ->whereNotNull('rating')
            ->avg('rating') ?? 0;
    }
    
    public function getUtilizationRate($date = null)
    {
        $date = $date ?? now()->toDateString();
        
        // Get total available hours for the day
        $schedule = $this->schedules()
            ->where('date', $date)
            ->orWhere(function($query) use ($date) {
                $dayOfWeek = strtolower(now()->parse($date)->format('l'));
                $query->where('day_of_week', $dayOfWeek)
                      ->whereNull('date'); // Recurring schedule
            })
            ->first();
            
        if (!$schedule) {
            return 0;
        }
        
        $availableMinutes = $this->calculateMinutesBetween($schedule->start_time, $schedule->end_time);
        
        // Get booked hours for the day
        $bookedMinutes = $this->bookings()
            ->where('appointment_date', $date)
            ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
            ->get()
            ->sum(function($booking) {
                return $this->calculateMinutesBetween($booking->start_time, $booking->end_time);
            });
            
        return $availableMinutes > 0 ? round(($bookedMinutes / $availableMinutes) * 100, 2) : 0;
    }
    
    private function calculateMinutesBetween($startTime, $endTime)
    {
        $start = \Carbon\Carbon::parse($startTime);
        $end = \Carbon\Carbon::parse($endTime);
        
        return $start->diffInMinutes($end);
    }
    
    public function getTopServices($limit = 5)
    {
        return $this->bookings()
            ->select('service_id')
            ->selectRaw('COUNT(*) as booking_count')
            ->with('service')
            ->groupBy('service_id')
            ->orderByDesc('booking_count')
            ->limit($limit)
            ->get();
    }
    
    public function getWorkingBranches()
    {
        return $this->branches()->where('status', 'active')->get();
    }
    
    public function getPrimaryBranch()
    {
        return $this->branches()->wherePivot('is_primary_branch', true)->first();
    }
    
    public function getColorAttribute($value)
    {
        return $value ?? $this->generateColorFromName();
    }
    
    private function generateColorFromName()
    {
        $colors = [
            '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
            '#DDA0DD', '#98D8C8', '#FFB347', '#87CEEB', '#F0E68C'
        ];
        
        $index = crc32($this->name) % count($colors);
        return $colors[abs($index)];
    }
}