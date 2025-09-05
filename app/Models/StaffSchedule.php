<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSchedule extends Model
{
    protected $fillable = [
        'staff_id',
        'branch_id',
        'date',
        'start_time',
        'end_time',
        'is_available',
        'break_start',
        'break_end',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'break_start' => 'datetime:H:i',
        'break_end' => 'datetime:H:i',
        'is_available' => 'boolean',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function hasBreak(): bool
    {
        return $this->break_start && $this->break_end;
    }

    public function getWorkingHoursAttribute(): array
    {
        $periods = [];
        
        if ($this->hasBreak()) {
            // Before break
            if ($this->start_time < $this->break_start) {
                $periods[] = [
                    'start' => $this->start_time,
                    'end' => $this->break_start
                ];
            }
            
            // After break
            if ($this->break_end < $this->end_time) {
                $periods[] = [
                    'start' => $this->break_end,
                    'end' => $this->end_time
                ];
            }
        } else {
            // Full day without break
            $periods[] = [
                'start' => $this->start_time,
                'end' => $this->end_time
            ];
        }
        
        return $periods;
    }

    public function getTotalWorkingMinutesAttribute(): int
    {
        $totalMinutes = $this->start_time->diffInMinutes($this->end_time);
        
        if ($this->hasBreak()) {
            $breakMinutes = $this->break_start->diffInMinutes($this->break_end);
            $totalMinutes -= $breakMinutes;
        }
        
        return $totalMinutes;
    }

    public function isTimeWithinSchedule(string $time): bool
    {
        $timeObj = \Carbon\Carbon::createFromFormat('H:i', $time);
        
        foreach ($this->working_hours as $period) {
            if ($timeObj >= $period['start'] && $timeObj <= $period['end']) {
                return true;
            }
        }
        
        return false;
    }
}