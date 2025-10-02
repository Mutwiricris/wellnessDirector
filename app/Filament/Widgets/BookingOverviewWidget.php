<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;

class BookingOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        
        if (!$tenant) {
            return [];
        }

        $today = today();

        // Today's bookings
        $todayBookings = Booking::where('branch_id', $tenant->id)
            ->whereDate('appointment_date', $today)
            ->count();

        // Today's confirmed bookings
        $confirmedBookings = Booking::where('branch_id', $tenant->id)
            ->whereDate('appointment_date', $today)
            ->where('status', 'confirmed')
            ->count();

        // Today's completed bookings
        $completedBookings = Booking::where('branch_id', $tenant->id)
            ->whereDate('appointment_date', $today)
            ->where('status', 'completed')
            ->count();

        // Today's revenue
        $todayRevenue = Booking::where('branch_id', $tenant->id)
            ->whereDate('appointment_date', $today)
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        // Pending payments
        $pendingPayments = Booking::where('branch_id', $tenant->id)
            ->whereDate('appointment_date', $today)
            ->where('payment_status', 'pending')
            ->count();

        // Current week vs last week
        $thisWeekStart = now()->startOfWeek();
        $thisWeekEnd = now()->endOfWeek();
        $lastWeekStart = now()->subWeek()->startOfWeek();
        $lastWeekEnd = now()->subWeek()->endOfWeek();

        $thisWeekBookings = Booking::where('branch_id', $tenant->id)
            ->whereBetween('appointment_date', [$thisWeekStart, $thisWeekEnd])
            ->count();

        $lastWeekBookings = Booking::where('branch_id', $tenant->id)
            ->whereBetween('appointment_date', [$lastWeekStart, $lastWeekEnd])
            ->count();

        $weeklyGrowth = $lastWeekBookings > 0 
            ? (($thisWeekBookings - $lastWeekBookings) / $lastWeekBookings) * 100 
            : 0;

        // Data for chart (bookings in the last 7 days)
        $bookingsLast7Days = Booking::where('branch_id', $tenant->id)
            ->whereBetween('appointment_date', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->selectRaw('DATE(appointment_date) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->pluck('count', 'date')
            ->toArray();

        $chartData = array_values(array_pad($bookingsLast7Days, -7, 0));

        return [
            Stat::make('Today\'s Bookings', $todayBookings)
                ->description($confirmedBookings . ' confirmed, ' . $completedBookings . ' completed')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info')
                ->chart($chartData),

            Stat::make('Today\'s Revenue', 'KES ' . number_format($todayRevenue, 0))
                ->description($pendingPayments . ' pending payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Weekly Bookings', $thisWeekBookings)
                ->description(($weeklyGrowth >= 0 ? '+' : '') . number_format($weeklyGrowth, 1) . '% from last week')
                ->descriptionIcon($weeklyGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($weeklyGrowth >= 0 ? 'success' : 'danger'),

            Stat::make('Completion Rate', 
                $todayBookings > 0 ? number_format(($completedBookings / $todayBookings) * 100, 1) . '%' : '0%'
            )
                ->description('Today\'s service completion')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('warning'),
        ];
    }

    protected static bool $isLazy = false;
}