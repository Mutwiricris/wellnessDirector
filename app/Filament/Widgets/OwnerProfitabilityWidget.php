<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\PosTransaction;
use App\Models\Expense;
use App\Models\StaffCommission;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Carbon\Carbon;

class OwnerProfitabilityWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    
    protected static ?string $pollingInterval = '15s';
    
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 'full',
        'lg' => 'full',
        'xl' => 'full',
        '2xl' => 'full',
    ];

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return [];

        $startDate = $this->filters['startDate'] ?? now()->startOfMonth();
        $endDate = $this->filters['endDate'] ?? now()->endOfMonth();
        
        // Previous period for comparison (same duration as current period)
        $periodDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        $previousStart = Carbon::parse($startDate)->subDays($periodDays + 1);
        $previousEnd = Carbon::parse($startDate)->subDay();

        // Revenue
        $totalRevenue = $this->getTotalRevenue($startDate, $endDate, $tenant->id);
        $previousRevenue = $this->getTotalRevenue($previousStart, $previousEnd, $tenant->id);

        // Expenses
        $totalExpenses = $this->getTotalExpenses($startDate, $endDate, $tenant->id);
        $previousExpenses = $this->getTotalExpenses($previousStart, $previousEnd, $tenant->id);

        // Staff Costs
        $staffCosts = $this->getStaffCosts($startDate, $endDate, $tenant->id);
        $previousStaffCosts = $this->getStaffCosts($previousStart, $previousEnd, $tenant->id);

        // Net Profit
        $netProfit = $totalRevenue - $totalExpenses - $staffCosts;
        $previousProfit = $previousRevenue - $previousExpenses - $previousStaffCosts;

        // Profit Margin
        $profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;
        $previousMargin = $previousRevenue > 0 ? ($previousProfit / $previousRevenue) * 100 : 0;

        // Growth calculations
        $profitGrowth = $previousProfit > 0 
            ? (($netProfit - $previousProfit) / abs($previousProfit)) * 100 
            : 0;

        return [
            Stat::make('Net Profit', 'KES ' . number_format($netProfit, 2))
                ->description($profitGrowth >= 0 
                    ? "↗️ " . number_format(abs($profitGrowth), 1) . "% from previous period" 
                    : "↘️ " . number_format(abs($profitGrowth), 1) . "% from previous period")
                ->descriptionIcon($netProfit >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($netProfit >= 0 ? 'success' : 'danger')
                ->chart($this->getProfitChart($tenant->id)),

            Stat::make('Profit Margin', number_format($profitMargin, 1) . '%')
                ->description($profitMargin >= $previousMargin 
                    ? "↗️ Improved from " . number_format($previousMargin, 1) . "%" 
                    : "↘️ Down from " . number_format($previousMargin, 1) . "%")
                ->descriptionIcon($profitMargin >= 20 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($profitMargin >= 20 ? 'success' : ($profitMargin >= 10 ? 'warning' : 'danger')),

            Stat::make('Total Expenses', 'KES ' . number_format($totalExpenses, 2))
                ->description(number_format(($totalExpenses / max($totalRevenue, 1)) * 100, 1) . '% of revenue')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Staff Costs', 'KES ' . number_format($staffCosts, 2))
                ->description(number_format(($staffCosts / max($totalRevenue, 1)) * 100, 1) . '% of revenue')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }

    private function getTotalRevenue($start, $end, int $branchId): float
    {
        $bookingRevenue = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$start, $end])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        $posRevenue = PosTransaction::where('branch_id', $branchId)
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        return $bookingRevenue + $posRevenue;
    }

    private function getTotalExpenses($start, $end, int $branchId): float
    {
        return Expense::where('branch_id', $branchId)
            ->whereBetween('expense_date', [$start, $end])
            ->where('status', 'approved')
            ->sum('amount');
    }

    private function getStaffCosts($start, $end, int $branchId): float
    {
        return StaffCommission::where('branch_id', $branchId)
            ->whereBetween('earned_date', [$start, $end])
            ->where('approval_status', 'approved')
            ->sum('total_earning');
    }

    private function getProfitChart(int $branchId): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $endDate = $date->copy()->endOfDay();
            
            $revenue = $this->getTotalRevenue($date, $endDate, $branchId);
            $expenses = $this->getTotalExpenses($date, $endDate, $branchId);
            $staffCosts = $this->getStaffCosts($date, $endDate, $branchId);
            
            $profit = $revenue - $expenses - $staffCosts;
            $data[] = $profit;
        }
        return $data;
    }
}
