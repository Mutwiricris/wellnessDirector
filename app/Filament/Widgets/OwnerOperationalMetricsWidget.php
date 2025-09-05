<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Staff;
use App\Models\Service;
use App\Models\StaffSchedule;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use Carbon\Carbon;

class OwnerOperationalMetricsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '120s';
    
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

        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();
        $now = Carbon::now();

        $operationalMetrics = $this->getOperationalMetrics($tenant->id, $today, $thisWeek, $thisMonth, $now);

        return [
            Stat::make('Capacity Utilization', number_format($operationalMetrics['capacity_utilization'], 1) . '%')
                ->description('Current booking capacity usage')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($operationalMetrics['capacity_utilization'] >= 80 ? 'success' : 'warning'),

            Stat::make('Avg Service Time', $operationalMetrics['avg_service_time'] . ' min')
                ->description('Average service duration')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            Stat::make('No-Show Rate', number_format($operationalMetrics['no_show_rate'], 1) . '%')
                ->description('Customers who missed appointments')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color($operationalMetrics['no_show_rate'] <= 10 ? 'success' : 'danger'),

            Stat::make('Cancellation Rate', number_format($operationalMetrics['cancellation_rate'], 1) . '%')
                ->description('Booking cancellation percentage')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($operationalMetrics['cancellation_rate'] <= 15 ? 'success' : 'warning'),

            Stat::make('Peak Hours', $operationalMetrics['peak_hours'])
                ->description('Busiest booking time slot')
                ->descriptionIcon('heroicon-m-fire')
                ->color('primary'),

            Stat::make('Service Efficiency', number_format($operationalMetrics['service_efficiency'], 1) . '%')
                ->description('On-time service completion rate')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($operationalMetrics['service_efficiency'] >= 90 ? 'success' : 'warning'),
        ];
    }

    private function getOperationalMetrics(int $branchId, Carbon $today, Carbon $thisWeek, Carbon $thisMonth, Carbon $now): array
    {
        // Capacity Utilization
        $totalCapacity = $this->calculateTotalCapacity($branchId, $today);
        $bookedCapacity = $this->calculateBookedCapacity($branchId, $today);
        $capacityUtilization = $totalCapacity > 0 ? ($bookedCapacity / $totalCapacity) * 100 : 0;

        // Average Service Time
        $completedBookings = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$thisMonth->toDateString(), $now->toDateString()])
            ->where('status', 'completed')
            ->get();

        $totalServiceMinutes = 0;
        $serviceCount = 0;

        foreach ($completedBookings as $booking) {
            if ($booking->start_time && $booking->end_time) {
                $startTime = Carbon::parse($booking->start_time);
                $endTime = Carbon::parse($booking->end_time);
                $totalServiceMinutes += $startTime->diffInMinutes($endTime);
                $serviceCount++;
            }
        }

        $avgServiceTime = $serviceCount > 0 ? round($totalServiceMinutes / $serviceCount) : 0;

        // No-Show Rate
        $totalBookings = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$thisMonth->toDateString(), $now->toDateString()])
            ->count();

        $noShowBookings = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$thisMonth->toDateString(), $now->toDateString()])
            ->where('status', 'no_show')
            ->count();

        $noShowRate = $totalBookings > 0 ? ($noShowBookings / $totalBookings) * 100 : 0;

        // Cancellation Rate
        $cancelledBookings = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$thisMonth->toDateString(), $now->toDateString()])
            ->where('status', 'cancelled')
            ->count();

        $cancellationRate = $totalBookings > 0 ? ($cancelledBookings / $totalBookings) * 100 : 0;

        // Peak Hours
        $peakHours = $this->findPeakHours($branchId, $thisWeek, $now);

        // Service Efficiency (simplified - based on completed vs total bookings)
        $serviceEfficiency = $totalBookings > 0 ? ($completedBookings->count() / $totalBookings) * 100 : 0;

        return [
            'capacity_utilization' => $capacityUtilization,
            'avg_service_time' => $avgServiceTime,
            'no_show_rate' => $noShowRate,
            'cancellation_rate' => $cancellationRate,
            'peak_hours' => $peakHours,
            'service_efficiency' => $serviceEfficiency,
        ];
    }

    private function calculateTotalCapacity(int $branchId, Carbon $date): int
    {
        $schedules = StaffSchedule::where('branch_id', $branchId)
            ->whereDate('date', $date)
            ->where('is_available', true)
            ->get();

        $totalMinutes = 0;
        foreach ($schedules as $schedule) {
            $startTime = Carbon::parse($schedule->start_time);
            $endTime = Carbon::parse($schedule->end_time);
            
            $workingMinutes = $startTime->diffInMinutes($endTime);
            
            // Subtract break time if exists
            if ($schedule->break_start && $schedule->break_end) {
                $breakStart = Carbon::parse($schedule->break_start);
                $breakEnd = Carbon::parse($schedule->break_end);
                $workingMinutes -= $breakStart->diffInMinutes($breakEnd);
            }
            
            $totalMinutes += $workingMinutes;
        }

        // Convert to 30-minute slots (standard booking unit)
        return intval($totalMinutes / 30);
    }

    private function calculateBookedCapacity(int $branchId, Carbon $date): int
    {
        $bookings = Booking::where('branch_id', $branchId)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
            ->get();

        $totalBookedMinutes = 0;
        foreach ($bookings as $booking) {
            if ($booking->start_time && $booking->end_time) {
                $startTime = Carbon::parse($booking->start_time);
                $endTime = Carbon::parse($booking->end_time);
                $totalBookedMinutes += $startTime->diffInMinutes($endTime);
            }
        }

        // Convert to 30-minute slots
        return intval($totalBookedMinutes / 30);
    }

    private function findPeakHours(int $branchId, Carbon $start, Carbon $end): string
    {
        $hourlyBookings = [];
        
        $bookings = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
            ->get();

        foreach ($bookings as $booking) {
            if ($booking->start_time) {
                $hour = Carbon::parse($booking->start_time)->format('H:00');
                $hourlyBookings[$hour] = ($hourlyBookings[$hour] ?? 0) + 1;
            }
        }

        if (empty($hourlyBookings)) {
            return 'No data';
        }

        $peakHour = array_keys($hourlyBookings, max($hourlyBookings))[0];
        $nextHour = sprintf('%02d:00', intval($peakHour) + 1);
        
        return $peakHour . '-' . $nextHour;
    }
}
