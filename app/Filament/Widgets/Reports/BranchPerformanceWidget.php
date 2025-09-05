<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Booking;
use App\Models\PosTransaction;
use App\Models\User;
use App\Models\Staff;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;

class BranchPerformanceWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        if (!$tenant) {
            return [];
        }

        // Current month data
        $currentMonth = now();
        $previousMonth = now()->subMonth();

        // Revenue metrics
        $currentRevenue = $this->calculateRevenue($tenant->id, $currentMonth->startOfMonth(), $currentMonth->endOfMonth());
        $previousRevenue = $this->calculateRevenue($tenant->id, $previousMonth->startOfMonth(), $previousMonth->endOfMonth());
        $revenueGrowth = $previousRevenue > 0 ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;

        // Customer metrics
        $currentCustomers = $this->calculateUniqueCustomers($tenant->id, $currentMonth->startOfMonth(), $currentMonth->endOfMonth());
        $previousCustomers = $this->calculateUniqueCustomers($tenant->id, $previousMonth->startOfMonth(), $previousMonth->endOfMonth());
        $customerGrowth = $previousCustomers > 0 ? (($currentCustomers - $previousCustomers) / $previousCustomers) * 100 : 0;

        // Service metrics
        $currentServices = $this->calculateServicesProvided($tenant->id, $currentMonth->startOfMonth(), $currentMonth->endOfMonth());
        $previousServices = $this->calculateServicesProvided($tenant->id, $previousMonth->startOfMonth(), $previousMonth->endOfMonth());
        $serviceGrowth = $previousServices > 0 ? (($currentServices - $previousServices) / $previousServices) * 100 : 0;

        // Staff utilization
        $staffUtilization = $this->calculateStaffUtilization($tenant->id, $currentMonth->startOfMonth(), $currentMonth->endOfMonth());

        // Average booking value
        $avgBookingValue = $currentServices > 0 ? $currentRevenue / $currentServices : 0;

        // Customer retention rate (repeat customers this month)
        $customerRetention = $this->calculateCustomerRetention($tenant->id, $currentMonth->startOfMonth(), $currentMonth->endOfMonth());

        return [
            Stat::make('Monthly Revenue', 'KES ' . number_format($currentRevenue, 0))
                ->description($this->formatGrowth($revenueGrowth) . ' from last month')
                ->descriptionIcon($revenueGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueGrowth >= 0 ? 'success' : 'danger'),

            Stat::make('Unique Customers', number_format($currentCustomers))
                ->description($this->formatGrowth($customerGrowth) . ' from last month')
                ->descriptionIcon($customerGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($customerGrowth >= 0 ? 'success' : 'danger'),

            Stat::make('Services Provided', number_format($currentServices))
                ->description($this->formatGrowth($serviceGrowth) . ' from last month')
                ->descriptionIcon($serviceGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($serviceGrowth >= 0 ? 'success' : 'danger'),

            Stat::make('Staff Utilization', number_format($staffUtilization, 1) . '%')
                ->description('Current capacity utilization')
                ->descriptionIcon('heroicon-m-users')
                ->color($staffUtilization >= 80 ? 'success' : ($staffUtilization >= 60 ? 'warning' : 'danger')),

            Stat::make('Avg Booking Value', 'KES ' . number_format($avgBookingValue, 0))
                ->description('Per service revenue')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Customer Retention', number_format($customerRetention, 1) . '%')
                ->description('Repeat customers this month')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($customerRetention >= 60 ? 'success' : ($customerRetention >= 40 ? 'warning' : 'danger')),
        ];
    }

    private function calculateRevenue(int $branchId, $startDate, $endDate): float
    {
        // Service revenue
        $serviceRevenue = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        // Product revenue
        $productRevenue = PosTransaction::where('branch_id', $branchId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        return (float) ($serviceRevenue + $productRevenue);
    }

    private function calculateUniqueCustomers(int $branchId, $startDate, $endDate): int
    {
        return Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->distinct('client_id')
            ->count();
    }

    private function calculateServicesProvided(int $branchId, $startDate, $endDate): int
    {
        return Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->where('bookings.status', 'completed')
            ->count();
    }

    private function calculateStaffUtilization(int $branchId, $startDate, $endDate): float
    {
        // Get active staff count for this branch
        $activeStaff = Staff::whereHas('branches', function($q) use ($branchId) {
            $q->where('branches.id', $branchId);
        })->where('status', 'active')->count();

        if ($activeStaff === 0) {
            return 0;
        }

        // Calculate total possible working hours (assuming 8 hours per day, 22 working days per month)
        $workingDays = $startDate->diffInDays($endDate);
        $totalPossibleHours = $activeStaff * $workingDays * 8;

        // Calculate actual working hours (based on bookings)
        $actualHours = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->where('bookings.status', 'completed')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->sum('services.duration_minutes') / 60;

        return $totalPossibleHours > 0 ? ($actualHours / $totalPossibleHours) * 100 : 0;
    }

    private function calculateCustomerRetention(int $branchId, $startDate, $endDate): float
    {
        // Get unique customers this month
        $currentCustomers = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->distinct('client_id')
            ->pluck('client_id');

        if ($currentCustomers->isEmpty()) {
            return 0;
        }

        // Calculate previous period (same duration before start date)
        $periodLength = $startDate->diffInDays($endDate);
        $previousStart = $startDate->copy()->subDays($periodLength + 1);
        $previousEnd = $startDate->copy()->subDay();

        // Get customers who also booked in previous period
        $repeatCustomers = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$previousStart, $previousEnd])
            ->whereIn('client_id', $currentCustomers)
            ->distinct('client_id')
            ->count();

        return ($repeatCustomers / $currentCustomers->count()) * 100;
    }

    private function formatGrowth(float $growth): string
    {
        $prefix = $growth >= 0 ? '+' : '';
        return $prefix . number_format($growth, 1) . '%';
    }
}