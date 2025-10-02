<?php

namespace App\Filament\Widgets;

use App\Models\Staff;
use App\Models\Booking;
use App\Models\StaffCommission;
use App\Models\StaffSchedule;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Carbon\Carbon;

class OwnerStaffPerformanceWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    
    protected static ?string $pollingInterval = '15s';
    
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 2,
        'lg' => 1,
        'xl' => 1,
        '2xl' => 1,
    ];

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return [];

        $thisMonth = Carbon::now()->startOfMonth();
        $now = Carbon::now();
        $today = Carbon::today();

        // Get staff performance metrics
        $staffMetrics = $this->getStaffMetrics($tenant->id, $thisMonth, $now);
        $todayMetrics = $this->getTodayMetrics($tenant->id, $today);

        return [
            Stat::make('Active Staff', $staffMetrics['active_staff'])
                ->description('Staff members working today')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Avg Utilization', number_format($staffMetrics['avg_utilization'], 1) . '%')
                ->description('Staff booking utilization rate')
                ->descriptionIcon('heroicon-m-clock')
                ->color($staffMetrics['avg_utilization'] >= 70 ? 'success' : 'warning'),

            Stat::make('Top Performer', $staffMetrics['top_performer']['name'])
                ->description('KES ' . number_format($staffMetrics['top_performer']['revenue'], 0) . ' revenue')
                ->descriptionIcon('heroicon-m-star')
                ->color('success'),

            Stat::make('Total Commissions', 'KES ' . number_format($staffMetrics['total_commissions'], 2))
                ->description('Staff earnings this month')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Avg Rating', number_format($staffMetrics['avg_rating'], 1) . '/5.0')
                ->description('Rating system pending implementation')
                ->descriptionIcon('heroicon-m-heart')
                ->color('info'),

            Stat::make('Today\'s Bookings', $todayMetrics['bookings'])
                ->description($todayMetrics['staff_working'] . ' staff working today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),
        ];
    }

    private function getStaffMetrics(int $branchId, Carbon $start, Carbon $end): array
    {
        // Get all staff for this branch
        $staff = Staff::whereHas('branches', function ($query) use ($branchId) {
            $query->where('branch_id', $branchId);
        })->where('status', 'active')->get();

        $activeStaff = $staff->count();
        $totalUtilization = 0;
        $totalRating = 0;
        $ratedBookings = 0;
        $topPerformer = ['name' => 'N/A', 'revenue' => 0];

        foreach ($staff as $staffMember) {
            // Calculate utilization
            $utilization = $this->calculateStaffUtilization($staffMember->id, $branchId, $start, $end);
            $totalUtilization += $utilization;

            // Calculate revenue for top performer
            $revenue = Booking::where('staff_id', $staffMember->id)
                ->where('branch_id', $branchId)
                ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
                ->where('status', 'completed')
                ->sum('total_amount');

            if ($revenue > $topPerformer['revenue']) {
                $topPerformer = [
                    'name' => $staffMember->name,
                    'revenue' => $revenue
                ];
            }

            // Skip rating calculation as rating column doesn't exist
            // This can be implemented later when rating system is added
        }

        $avgUtilization = $activeStaff > 0 ? $totalUtilization / $activeStaff : 0;
        $avgRating = 4.5; // Default rating until rating system is implemented

        // Get total commissions
        $totalCommissions = StaffCommission::where('branch_id', $branchId)
            ->whereBetween('earned_date', [$start->toDateString(), $end->toDateString()])
            ->where('payment_status', 'paid')
            ->sum('total_earning');

        return [
            'active_staff' => $activeStaff,
            'avg_utilization' => $avgUtilization,
            'avg_rating' => $avgRating,
            'top_performer' => $topPerformer,
            'total_commissions' => $totalCommissions,
        ];
    }

    private function getTodayMetrics(int $branchId, Carbon $today): array
    {
        $todayBookings = Booking::where('branch_id', $branchId)
            ->whereDate('appointment_date', $today)
            ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
            ->count();

        $staffWorking = StaffSchedule::where('branch_id', $branchId)
            ->whereDate('date', $today)
            ->where('is_available', true)
            ->distinct('staff_id')
            ->count();

        return [
            'bookings' => $todayBookings,
            'staff_working' => $staffWorking,
        ];
    }

    private function calculateStaffUtilization(int $staffId, int $branchId, Carbon $start, Carbon $end): float
    {
        // Get total available hours
        $schedules = StaffSchedule::where('staff_id', $staffId)
            ->where('branch_id', $branchId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('is_available', true)
            ->get();

        $totalAvailableMinutes = 0;
        foreach ($schedules as $schedule) {
            $startTime = Carbon::parse($schedule->start_time);
            $endTime = Carbon::parse($schedule->end_time);
            
            // Subtract break time if exists
            if ($schedule->break_start && $schedule->break_end) {
                $breakStart = Carbon::parse($schedule->break_start);
                $breakEnd = Carbon::parse($schedule->break_end);
                $breakMinutes = $breakStart->diffInMinutes($breakEnd);
                $totalAvailableMinutes += $startTime->diffInMinutes($endTime) - $breakMinutes;
            } else {
                $totalAvailableMinutes += $startTime->diffInMinutes($endTime);
            }
        }

        // Get total booked minutes
        $bookings = Booking::where('staff_id', $staffId)
            ->where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
            ->get();

        $totalBookedMinutes = 0;
        foreach ($bookings as $booking) {
            $startTime = Carbon::parse($booking->start_time);
            $endTime = Carbon::parse($booking->end_time);
            $totalBookedMinutes += $startTime->diffInMinutes($endTime);
        }

        return $totalAvailableMinutes > 0 ? ($totalBookedMinutes / $totalAvailableMinutes) * 100 : 0;
    }
}
