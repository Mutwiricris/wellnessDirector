<?php

namespace App\Filament\Widgets\Financial;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PosTransaction;
use App\Models\Expense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FinancialOverviewWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    protected static ?string $pollingInterval = '15s';

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth();
        $endDate = $this->filters['endDate'] ?? now()->endOfMonth();

        // Total Revenue Calculation
        $totalRevenue = $this->calculateTotalRevenue($tenant?->id, $startDate, $endDate);
        $previousRevenue = $this->calculateTotalRevenue($tenant?->id, 
            $this->getPreviousPeriodStart($startDate, $endDate), 
            $this->getPreviousPeriodEnd($startDate, $endDate)
        );
        $revenueChange = $this->calculatePercentageChange($totalRevenue, $previousRevenue);

        // Total Expenses Calculation
        $totalExpenses = $this->calculateTotalExpenses($tenant?->id, $startDate, $endDate);
        $previousExpenses = $this->calculateTotalExpenses($tenant?->id,
            $this->getPreviousPeriodStart($startDate, $endDate),
            $this->getPreviousPeriodEnd($startDate, $endDate)
        );
        $expenseChange = $this->calculatePercentageChange($totalExpenses, $previousExpenses);

        // Net Profit Calculation
        $netProfit = $totalRevenue - $totalExpenses;
        $previousNetProfit = $previousRevenue - $previousExpenses;
        $profitChange = $this->calculatePercentageChange($netProfit, $previousNetProfit);

        // Profit Margin
        $profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

        // Cash Flow (Revenue - Expenses + Outstanding Receivables)
        $outstandingReceivables = $this->calculateOutstandingReceivables($tenant?->id);
        $cashFlow = $netProfit + $outstandingReceivables;

        // Average Transaction Value
        $totalTransactions = $this->calculateTotalTransactions($tenant?->id, $startDate, $endDate);
        $avgTransactionValue = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        return [
            Stat::make('Total Revenue', 'KES ' . number_format($totalRevenue, 2))
                ->description($this->formatChangeDescription($revenueChange, 'vs previous period'))
                ->descriptionIcon($revenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueChange >= 0 ? 'success' : 'danger')
                ->chart($this->getRevenueChart($tenant?->id, $startDate, $endDate)),

            Stat::make('Total Expenses', 'KES ' . number_format($totalExpenses, 2))
                ->description($this->formatChangeDescription($expenseChange, 'vs previous period'))
                ->descriptionIcon($expenseChange <= 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
                ->color($expenseChange <= 0 ? 'success' : 'warning')
                ->chart($this->getExpenseChart($tenant?->id, $startDate, $endDate)),

            Stat::make('Net Profit', 'KES ' . number_format($netProfit, 2))
                ->description($this->formatChangeDescription($profitChange, 'vs previous period'))
                ->descriptionIcon($profitChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($netProfit >= 0 ? 'success' : 'danger')
                ->chart($this->getProfitChart($tenant?->id, $startDate, $endDate)),

            Stat::make('Profit Margin', number_format($profitMargin, 1) . '%')
                ->description('Current profit margin')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($profitMargin >= 30 ? 'success' : ($profitMargin >= 20 ? 'warning' : 'danger')),

            Stat::make('Cash Flow', 'KES ' . number_format($cashFlow, 2))
                ->description('Including receivables')
                ->descriptionIcon($cashFlow >= 0 ? 'heroicon-m-banknotes' : 'heroicon-m-exclamation-triangle')
                ->color($cashFlow >= 0 ? 'success' : 'danger'),

            Stat::make('Avg Transaction Value', 'KES ' . number_format($avgTransactionValue, 2))
                ->description($totalTransactions . ' total transactions')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),
        ];
    }

    private function calculateTotalRevenue(?int $branchId, $startDate, $endDate): float
    {
        // Service Revenue from Bookings
        $serviceRevenue = Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        // Product Revenue from POS Transactions
        $productRevenue = PosTransaction::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        return (float) ($serviceRevenue + $productRevenue);
    }

    private function calculateTotalExpenses(?int $branchId, $startDate, $endDate): float
    {
        return (float) Expense::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->where('status', 'approved')
            ->sum('amount');
    }

    private function calculateOutstandingReceivables(?int $branchId): float
    {
        return (float) Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('payment_status', 'pending')
            ->sum('total_amount');
    }

    private function calculateTotalTransactions(?int $branchId, $startDate, $endDate): int
    {
        $bookingTransactions = Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->count();

        $posTransactions = PosTransaction::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->count();

        return $bookingTransactions + $posTransactions;
    }

    private function calculatePercentageChange(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return (($current - $previous) / $previous) * 100;
    }

    private function formatChangeDescription(float $change, string $suffix): string
    {
        $prefix = $change >= 0 ? '+' : '';
        return $prefix . number_format($change, 1) . '% ' . $suffix;
    }

    private function getPreviousPeriodStart($startDate, $endDate)
    {
        $diff = \Carbon\Carbon::parse($endDate)->diffInDays(\Carbon\Carbon::parse($startDate));
        return \Carbon\Carbon::parse($startDate)->subDays($diff + 1);
    }

    private function getPreviousPeriodEnd($startDate, $endDate)
    {
        return \Carbon\Carbon::parse($startDate)->subDay();
    }

    private function getRevenueChart(?int $branchId, $startDate, $endDate): array
    {
        // Generate last 7 days revenue data for mini chart
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = \Carbon\Carbon::parse($endDate)->subDays($i);
            $revenue = $this->calculateTotalRevenue($branchId, $date, $date);
            $data[] = $revenue;
        }
        return $data;
    }

    private function getExpenseChart(?int $branchId, $startDate, $endDate): array
    {
        // Generate last 7 days expense data for mini chart
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = \Carbon\Carbon::parse($endDate)->subDays($i);
            $expense = $this->calculateTotalExpenses($branchId, $date, $date);
            $data[] = $expense;
        }
        return $data;
    }

    private function getProfitChart(?int $branchId, $startDate, $endDate): array
    {
        // Generate last 7 days profit data for mini chart
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = \Carbon\Carbon::parse($endDate)->subDays($i);
            $revenue = $this->calculateTotalRevenue($branchId, $date, $date);
            $expense = $this->calculateTotalExpenses($branchId, $date, $date);
            $data[] = $revenue - $expense;
        }
        return $data;
    }

}