<?php

namespace App\Filament\Widgets\Financial;

use App\Models\Booking;
use App\Models\Expense;
use App\Models\PosTransaction;
use App\Models\Service;
use App\Models\Staff;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use Carbon\Carbon;

class BudgetingForecastWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '300s';
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        
        return [
            // Monthly Budget vs Actual
            Stat::make('Budget Performance', $this->getBudgetPerformance($tenant?->id) . '%')
                ->description($this->getBudgetVarianceDescription($tenant?->id))
                ->descriptionIcon($this->getBudgetPerformance($tenant?->id) >= 100 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($this->getBudgetPerformanceColor($tenant?->id)),

            // Break-even Point Analysis
            Stat::make('Break-even Point', $this->getBreakEvenPoint($tenant?->id) . ' days')
                ->description('Days to cover fixed costs')
                ->descriptionIcon('heroicon-m-scale')
                ->color($this->getBreakEvenPoint($tenant?->id) <= 20 ? 'success' : 'warning'),

            // ROI on Marketing Spend
            Stat::make('Marketing ROI', number_format($this->getMarketingROI($tenant?->id), 1) . 'x')
                ->description('Return on marketing investment')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color($this->getMarketingROI($tenant?->id) >= 3 ? 'success' : 'warning'),

            // Service Capacity Utilization
            Stat::make('Capacity Utilization', number_format($this->getCapacityUtilization($tenant?->id), 1) . '%')
                ->description('Optimal: 75-85%')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($this->getCapacityUtilizationColor($tenant?->id)),

            // Cost per Acquisition
            Stat::make('Customer Acquisition Cost', 'KES ' . number_format($this->getCustomerAcquisitionCost($tenant?->id), 2))
                ->description('Cost to acquire new customer')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color($this->getCustomerAcquisitionCost($tenant?->id) <= 500 ? 'success' : 'warning'),

            // Revenue per Square Foot
            Stat::make('Revenue per Sq Ft', 'KES ' . number_format($this->getRevenuePerSquareFoot($tenant?->id), 2))
                ->description('Space efficiency metric')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('info'),
        ];
    }

    private function getBudgetPerformance(?int $branchId): float
    {
        $currentMonth = now()->startOfMonth();
        $endMonth = now()->endOfMonth();
        
        // Calculate actual revenue
        $actualRevenue = $this->calculateTotalRevenue($branchId, $currentMonth, $endMonth);
        
        // Estimate monthly budget based on historical data (last 3 months average + 10% growth)
        $historicalAverage = $this->getHistoricalAverageRevenue($branchId, 3);
        $budgetedRevenue = $historicalAverage * 1.1; // 10% growth target
        
        return $budgetedRevenue > 0 ? ($actualRevenue / $budgetedRevenue) * 100 : 0;
    }

    private function getBudgetVarianceDescription(?int $branchId): string
    {
        $performance = $this->getBudgetPerformance($branchId);
        $variance = $performance - 100;
        
        if ($variance >= 0) {
            return '+' . number_format($variance, 1) . '% above budget';
        } else {
            return number_format(abs($variance), 1) . '% below budget';
        }
    }

    private function getBudgetPerformanceColor(?int $branchId): string
    {
        $performance = $this->getBudgetPerformance($branchId);
        
        if ($performance >= 100) return 'success';
        if ($performance >= 90) return 'warning';
        return 'danger';
    }

    private function getBreakEvenPoint(?int $branchId): int
    {
        $currentMonth = now()->startOfMonth();
        $endMonth = now()->endOfMonth();
        
        // Calculate fixed costs (rent, salaries, utilities)
        $fixedCosts = Expense::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$currentMonth, $endMonth])
            ->whereIn('category', ['rent', 'staff', 'utilities', 'insurance'])
            ->sum('amount');
        
        // Calculate daily average revenue
        $totalRevenue = $this->calculateTotalRevenue($branchId, $currentMonth, $endMonth);
        $daysInMonth = $currentMonth->daysInMonth;
        $dailyRevenue = $daysInMonth > 0 ? $totalRevenue / $daysInMonth : 0;
        
        // Calculate variable cost ratio (approximately 30% of revenue)
        $variableCostRatio = 0.3;
        $dailyContributionMargin = $dailyRevenue * (1 - $variableCostRatio);
        
        return $dailyContributionMargin > 0 ? intval($fixedCosts / $dailyContributionMargin) : 999;
    }

    private function getMarketingROI(?int $branchId): float
    {
        $currentMonth = now()->startOfMonth();
        $endMonth = now()->endOfMonth();
        
        // Calculate marketing expenses
        $marketingExpenses = Expense::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$currentMonth, $endMonth])
            ->where('category', 'marketing')
            ->sum('amount');
        
        // Calculate revenue from new customers (simplified: 30% of total revenue)
        $totalRevenue = $this->calculateTotalRevenue($branchId, $currentMonth, $endMonth);
        $newCustomerRevenue = $totalRevenue * 0.3;
        
        return $marketingExpenses > 0 ? $newCustomerRevenue / $marketingExpenses : 0;
    }

    private function getCapacityUtilization(?int $branchId): float
    {
        $currentMonth = now()->startOfMonth();
        $endMonth = now()->endOfMonth();
        
        // Calculate working days in month
        $workingDays = $currentMonth->diffInWeekdays($endMonth) + 1;
        
        // Assume 8 hours per day, 6 slots per hour
        $totalAvailableSlots = $workingDays * 8 * 6;
        
        // Get actual bookings
        $actualBookings = Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$currentMonth, $endMonth])
            ->whereIn('status', ['completed', 'confirmed'])
            ->count();
        
        return $totalAvailableSlots > 0 ? ($actualBookings / $totalAvailableSlots) * 100 : 0;
    }

    private function getCapacityUtilizationColor(?int $branchId): string
    {
        $utilization = $this->getCapacityUtilization($branchId);
        
        if ($utilization >= 75 && $utilization <= 85) return 'success';
        if ($utilization >= 65 && $utilization < 95) return 'warning';
        return 'danger';
    }

    private function getCustomerAcquisitionCost(?int $branchId): float
    {
        $currentMonth = now()->startOfMonth();
        $endMonth = now()->endOfMonth();
        
        // Calculate total marketing and sales expenses
        $acquisitionCosts = Expense::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$currentMonth, $endMonth])
            ->whereIn('category', ['marketing', 'professional'])
            ->sum('amount');
        
        // Count new customers (first-time bookings)
        $newCustomers = Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$currentMonth, $endMonth])
            ->whereDoesntHave('client.bookings', function($q) use ($branchId, $currentMonth) {
                $q->when($branchId, fn($query) => $query->where('branch_id', $branchId))
                  ->where('appointment_date', '<', $currentMonth);
            })
            ->distinct('client_id')
            ->count();
        
        return $newCustomers > 0 ? $acquisitionCosts / $newCustomers : 0;
    }

    private function getRevenuePerSquareFoot(?int $branchId): float
    {
        $currentMonth = now()->startOfMonth();
        $endMonth = now()->endOfMonth();
        
        $totalRevenue = $this->calculateTotalRevenue($branchId, $currentMonth, $endMonth);
        
        // Estimate spa size (this should come from branch model in real implementation)
        $estimatedSquareFeet = 1500; // Default estimate
        
        return $estimatedSquareFeet > 0 ? $totalRevenue / $estimatedSquareFeet : 0;
    }

    private function calculateTotalRevenue(?int $branchId, $startDate, $endDate): float
    {
        $serviceRevenue = Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        $productRevenue = PosTransaction::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        return (float) ($serviceRevenue + $productRevenue);
    }

    private function getHistoricalAverageRevenue(?int $branchId, int $months): float
    {
        $totalRevenue = 0;
        
        for ($i = 1; $i <= $months; $i++) {
            $startDate = now()->subMonths($i)->startOfMonth();
            $endDate = now()->subMonths($i)->endOfMonth();
            $totalRevenue += $this->calculateTotalRevenue($branchId, $startDate, $endDate);
        }
        
        return $months > 0 ? $totalRevenue / $months : 0;
    }
}
