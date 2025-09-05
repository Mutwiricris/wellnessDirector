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
use Carbon\Carbon;

class OwnerProfitabilityWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';
    
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

        $thisMonth = Carbon::now()->startOfMonth();
        $now = Carbon::now();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // Revenue
        $totalRevenue = $this->getTotalRevenue($thisMonth, $now, $tenant->id);
        $lastMonthRevenue = $this->getTotalRevenue($lastMonth, $lastMonthEnd, $tenant->id);

        // Expenses
        $totalExpenses = $this->getTotalExpenses($thisMonth, $now, $tenant->id);
        $lastMonthExpenses = $this->getTotalExpenses($lastMonth, $lastMonthEnd, $tenant->id);

        // Staff Costs
        $staffCosts = $this->getStaffCosts($thisMonth, $now, $tenant->id);

        // Net Profit
        $netProfit = $totalRevenue - $totalExpenses - $staffCosts;
        $lastMonthProfit = $lastMonthRevenue - $lastMonthExpenses;

        // Profit Margin
        $profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;
        $lastMonthMargin = $lastMonthRevenue > 0 ? ($lastMonthProfit / $lastMonthRevenue) * 100 : 0;

        // Growth calculations
        $profitGrowth = $lastMonthProfit > 0 
            ? (($netProfit - $lastMonthProfit) / abs($lastMonthProfit)) * 100 
            : 0;

        return [
            Stat::make('Net Profit', 'KES ' . number_format($netProfit, 2))
                ->description($profitGrowth >= 0 
                    ? "↗️ {$profitGrowth}% from last month" 
                    : "↘️ {$profitGrowth}% from last month")
                ->descriptionIcon($netProfit >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($netProfit >= 0 ? 'success' : 'danger')
                ->chart($this->getProfitChart($tenant->id)),

            Stat::make('Profit Margin', number_format($profitMargin, 1) . '%')
                ->description($profitMargin >= $lastMonthMargin 
                    ? "↗️ Improved from " . number_format($lastMonthMargin, 1) . "%" 
                    : "↘️ Down from " . number_format($lastMonthMargin, 1) . "%")
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

    private function getTotalRevenue(Carbon $start, Carbon $end, int $branchId): float
    {
        $bookingRevenue = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        $posRevenue = PosTransaction::where('branch_id', $branchId)
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        return $bookingRevenue + $posRevenue;
    }

    private function getTotalExpenses(Carbon $start, Carbon $end, int $branchId): float
    {
        return Expense::where('branch_id', $branchId)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'approved')
            ->sum('amount');
    }

    private function getStaffCosts(Carbon $start, Carbon $end, int $branchId): float
    {
        return StaffCommission::where('branch_id', $branchId)
            ->whereBetween('earned_date', [$start->toDateString(), $end->toDateString()])
            ->where('payment_status', 'paid')
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
