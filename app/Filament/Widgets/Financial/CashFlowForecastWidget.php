<?php

namespace App\Filament\Widgets\Financial;

use App\Models\Booking;
use App\Models\Expense;
use App\Models\PackageSale;
use App\Models\GiftVoucher;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;
use Carbon\Carbon;

class CashFlowForecastWidget extends ChartWidget
{
    protected static ?string $heading = 'Cash Flow Forecast (Next 6 Months)';
    protected static ?string $pollingInterval = '120s';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        $forecastData = $this->generateCashFlowForecast($tenant?->id);

        return [
            'datasets' => [
                [
                    'label' => 'Projected Revenue',
                    'data' => array_column($forecastData, 'projected_revenue'),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.3)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Projected Expenses',
                    'data' => array_column($forecastData, 'projected_expenses'),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.3)',
                    'borderColor' => 'rgba(239, 68, 68, 1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Net Cash Flow',
                    'data' => array_column($forecastData, 'net_cash_flow'),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.3)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'type' => 'line',
                    'fill' => false,
                ],
            ],
            'labels' => array_column($forecastData, 'month'),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'title' => [
                    'display' => true,
                    'text' => 'Financial Forecast & Planning',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Amount (KES)',
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }

    private function generateCashFlowForecast(?int $branchId): array
    {
        $forecastData = [];
        $currentDate = now();

        // Get historical data for trend analysis
        $historicalRevenue = $this->getHistoricalMonthlyRevenue($branchId, 6);
        $historicalExpenses = $this->getHistoricalMonthlyExpenses($branchId, 6);

        // Calculate growth trends
        $revenueGrowthRate = $this->calculateGrowthRate($historicalRevenue);
        $expenseGrowthRate = $this->calculateGrowthRate($historicalExpenses);

        $lastMonthRevenue = end($historicalRevenue);
        $lastMonthExpenses = end($historicalExpenses);

        for ($i = 1; $i <= 6; $i++) {
            $forecastMonth = $currentDate->copy()->addMonths($i);
            
            // Project revenue with growth rate and seasonality
            $seasonalityFactor = $this->getSeasonalityFactor($forecastMonth->month);
            $projectedRevenue = $lastMonthRevenue * (1 + $revenueGrowthRate) * $seasonalityFactor;
            
            // Project expenses with growth rate
            $projectedExpenses = $lastMonthExpenses * (1 + $expenseGrowthRate);
            
            // Add confirmed future bookings
            $confirmedBookings = $this->getConfirmedFutureBookings($branchId, $forecastMonth);
            $projectedRevenue += $confirmedBookings;
            
            // Add recurring expenses
            $recurringExpenses = $this->getRecurringExpenses($branchId);
            $projectedExpenses += $recurringExpenses;

            $netCashFlow = $projectedRevenue - $projectedExpenses;

            $forecastData[] = [
                'month' => $forecastMonth->format('M Y'),
                'projected_revenue' => round($projectedRevenue, 2),
                'projected_expenses' => round($projectedExpenses, 2),
                'net_cash_flow' => round($netCashFlow, 2),
            ];

            // Update for next iteration
            $lastMonthRevenue = $projectedRevenue;
            $lastMonthExpenses = $projectedExpenses;
        }

        return $forecastData;
    }

    private function getHistoricalMonthlyRevenue(?int $branchId, int $months): array
    {
        $revenue = [];
        for ($i = $months; $i >= 1; $i--) {
            $startDate = now()->subMonths($i)->startOfMonth();
            $endDate = now()->subMonths($i)->endOfMonth();
            
            $monthlyRevenue = Booking::query()
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->whereBetween('appointment_date', [$startDate, $endDate])
                ->where('payment_status', 'completed')
                ->sum('total_amount');
            
            $revenue[] = (float) $monthlyRevenue;
        }
        return $revenue;
    }

    private function getHistoricalMonthlyExpenses(?int $branchId, int $months): array
    {
        $expenses = [];
        for ($i = $months; $i >= 1; $i--) {
            $startDate = now()->subMonths($i)->startOfMonth();
            $endDate = now()->subMonths($i)->endOfMonth();
            
            $monthlyExpenses = Expense::query()
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->where('status', 'approved')
                ->sum('amount');
            
            $expenses[] = (float) $monthlyExpenses;
        }
        return $expenses;
    }

    private function calculateGrowthRate(array $data): float
    {
        if (count($data) < 2) return 0;
        
        $totalGrowth = 0;
        $periods = 0;
        
        for ($i = 1; $i < count($data); $i++) {
            if ($data[$i-1] > 0) {
                $growth = ($data[$i] - $data[$i-1]) / $data[$i-1];
                $totalGrowth += $growth;
                $periods++;
            }
        }
        
        return $periods > 0 ? $totalGrowth / $periods : 0;
    }

    private function getSeasonalityFactor(int $month): float
    {
        // Spa/wellness seasonality factors (higher in certain months)
        $seasonalityMap = [
            1 => 1.1,  // January (New Year resolutions)
            2 => 1.15, // February (Valentine's Day)
            3 => 1.05, // March
            4 => 1.0,  // April
            5 => 1.2,  // May (Mother's Day, spring)
            6 => 1.1,  // June (wedding season)
            7 => 1.0,  // July
            8 => 1.0,  // August
            9 => 1.05, // September
            10 => 1.1, // October
            11 => 1.15, // November (holiday prep)
            12 => 1.25, // December (holidays, gifts)
        ];
        
        return $seasonalityMap[$month] ?? 1.0;
    }

    private function getConfirmedFutureBookings(?int $branchId, Carbon $month): float
    {
        return (float) Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$month->startOfMonth(), $month->endOfMonth()])
            ->whereIn('status', ['confirmed', 'pending'])
            ->sum('total_amount');
    }

    private function getRecurringExpenses(?int $branchId): float
    {
        return (float) Expense::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('is_recurring', true)
            ->where('status', 'approved')
            ->sum('amount');
    }
}
