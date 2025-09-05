<?php

namespace App\Filament\Widgets;

use App\Models\Branch;
use App\Models\Booking;
use App\Models\User;
use App\Models\Staff;
use App\Models\Service;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;

class BranchKPIWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        
        if (!$tenant) {
            return [];
        }

        // Today's metrics
        $todayRevenue = Booking::where('branch_id', $tenant->id)
            ->whereDate('appointment_date', today())
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        $todayBookings = Booking::where('branch_id', $tenant->id)
            ->whereDate('appointment_date', today())
            ->count();

        $todayPendingBookings = Booking::where('branch_id', $tenant->id)
            ->whereDate('appointment_date', today())
            ->where('status', 'pending')
            ->count();

        // This month's metrics
        $monthlyRevenue = Booking::where('branch_id', $tenant->id)
            ->whereMonth('appointment_date', now()->month)
            ->whereYear('appointment_date', now()->year)
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        $monthlyBookings = Booking::where('branch_id', $tenant->id)
            ->whereMonth('appointment_date', now()->month)
            ->whereYear('appointment_date', now()->year)
            ->count();

        // Staff metrics
        $activeStaff = Staff::whereHas('branches', function($query) use ($tenant) {
            $query->where('branch_id', $tenant->id);
        })->where('status', 'active')->count();

        $staffUtilization = $this->calculateStaffUtilization($tenant->id);

        // Customer metrics
        $totalCustomers = Booking::where('branch_id', $tenant->id)
            ->distinct('client_id')
            ->count('client_id');

        $newCustomersThisMonth = Booking::where('branch_id', $tenant->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereDoesntHave('client.bookings', function($query) use ($tenant) {
                $query->where('branch_id', $tenant->id)
                    ->where('created_at', '<', now()->startOfMonth());
            })
            ->distinct('client_id')
            ->count('client_id');

        return [
            Stat::make('Today\'s Revenue', 'KES ' . number_format($todayRevenue, 2))
                ->description('Daily revenue target: KES 15,000')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($todayRevenue > 15000 ? 'success' : ($todayRevenue > 10000 ? 'warning' : 'danger'))
                ->chart($this->getRevenueChart($tenant->id, 7)),

            Stat::make('Today\'s Bookings', $todayBookings)
                ->description($todayPendingBookings . ' pending confirmations')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($todayPendingBookings > 0 ? 'warning' : 'success'),

            Stat::make('Monthly Revenue', 'KES ' . number_format($monthlyRevenue, 2))
                ->description($monthlyBookings . ' bookings this month')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),

            Stat::make('Staff Utilization', $staffUtilization . '%')
                ->description($activeStaff . ' active staff members')
                ->descriptionIcon('heroicon-m-users')
                ->color($staffUtilization > 80 ? 'success' : ($staffUtilization > 60 ? 'warning' : 'danger')),

            Stat::make('Total Customers', number_format($totalCustomers))
                ->description($newCustomersThisMonth . ' new this month')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('Branch Performance', $this->getBranchRating($tenant->id))
                ->description('Based on all metrics')
                ->descriptionIcon('heroicon-m-star')
                ->color($this->getBranchRatingColor($tenant->id)),
        ];
    }

    private function calculateStaffUtilization($branchId): int
    {
        $totalStaff = Staff::whereHas('branches', function($query) use ($branchId) {
            $query->where('branch_id', $branchId);
        })->where('status', 'active')->count();

        if ($totalStaff === 0) return 0;

        $staffWithBookingsToday = Booking::where('branch_id', $branchId)
            ->whereDate('appointment_date', today())
            ->whereNotNull('staff_id')
            ->distinct('staff_id')
            ->count('staff_id');

        return $totalStaff > 0 ? round(($staffWithBookingsToday / $totalStaff) * 100) : 0;
    }

    private function getRevenueChart($branchId, $days): array
    {
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $revenue = Booking::where('branch_id', $branchId)
                ->whereDate('appointment_date', $date)
                ->where('payment_status', 'completed')
                ->sum('total_amount');
            $data[] = (int) $revenue;
        }
        return $data;
    }

    private function getBranchRating($branchId): string
    {
        // Simple rating based on key metrics
        $revenue = Booking::where('branch_id', $branchId)
            ->whereMonth('appointment_date', now()->month)
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        $utilization = $this->calculateStaffUtilization($branchId);
        
        $score = 0;
        if ($revenue > 50000) $score += 2;
        elseif ($revenue > 30000) $score += 1;
        
        if ($utilization > 80) $score += 2;
        elseif ($utilization > 60) $score += 1;

        return match($score) {
            4 => 'Excellent ⭐⭐⭐⭐⭐',
            3 => 'Very Good ⭐⭐⭐⭐',
            2 => 'Good ⭐⭐⭐',
            1 => 'Fair ⭐⭐',
            default => 'Needs Improvement ⭐'
        };
    }

    private function getBranchRatingColor($branchId): string
    {
        $rating = $this->getBranchRating($branchId);
        return match(true) {
            str_contains($rating, 'Excellent') => 'success',
            str_contains($rating, 'Very Good') => 'info',
            str_contains($rating, 'Good') => 'primary',
            str_contains($rating, 'Fair') => 'warning',
            default => 'danger'
        };
    }
}