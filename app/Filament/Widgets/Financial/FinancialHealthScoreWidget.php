<?php

namespace App\Filament\Widgets\Financial;

use App\Models\Booking;
use App\Models\Expense;
use App\Models\PosTransaction;
use App\Models\Client;
use App\Models\PackageSale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use Carbon\Carbon;

class FinancialHealthScoreWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '120s';
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        
        return [
            // Overall Financial Health Score
            Stat::make('Financial Health Score', $this->getFinancialHealthScore($tenant?->id) . '/100')
                ->description($this->getHealthScoreDescription($this->getFinancialHealthScore($tenant?->id)))
                ->descriptionIcon('heroicon-m-heart')
                ->color($this->getHealthScoreColor($this->getFinancialHealthScore($tenant?->id))),

            // Liquidity Ratio
            Stat::make('Current Liquidity Ratio', number_format($this->getLiquidityRatio($tenant?->id), 2))
                ->description('Cash flow vs monthly expenses')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($this->getLiquidityRatio($tenant?->id) >= 2 ? 'success' : 'warning'),

            // Revenue Diversification Index
            Stat::make('Revenue Diversification', number_format($this->getRevenueDiversification($tenant?->id), 1) . '%')
                ->description('Income stream variety')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($this->getRevenueDiversification($tenant?->id) >= 60 ? 'success' : 'warning'),

            // Expense Control Ratio
            Stat::make('Expense Control Ratio', number_format($this->getExpenseControlRatio($tenant?->id), 1) . '%')
                ->description('Expenses vs revenue')
                ->descriptionIcon('heroicon-m-scale')
                ->color($this->getExpenseControlRatio($tenant?->id) <= 70 ? 'success' : 'danger'),

            // Customer Concentration Risk
            Stat::make('Customer Concentration Risk', $this->getCustomerConcentrationRisk($tenant?->id))
                ->description('Revenue dependency risk')
                ->descriptionIcon('heroicon-m-users')
                ->color($this->getCustomerConcentrationRisk($tenant?->id) === 'Low' ? 'success' : 'warning'),

            // Growth Sustainability Score
            Stat::make('Growth Sustainability', number_format($this->getGrowthSustainabilityScore($tenant?->id), 0) . '%')
                ->description('Long-term growth potential')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($this->getGrowthSustainabilityScore($tenant?->id) >= 70 ? 'success' : 'warning'),
        ];
    }

    private function getFinancialHealthScore(?int $branchId): int
    {
        $scores = [
            'profitability' => $this->getProfitabilityScore($branchId),
            'liquidity' => $this->getLiquidityScore($branchId),
            'efficiency' => $this->getEfficiencyScore($branchId),
            'growth' => $this->getGrowthScore($branchId),
            'stability' => $this->getStabilityScore($branchId),
        ];

        $totalScore = array_sum($scores) / count($scores);
        return min(100, max(0, intval($totalScore)));
    }

    private function getProfitabilityScore(?int $branchId): float
    {
        $currentMonth = now()->startOfMonth();
        $endMonth = now()->endOfMonth();
        
        $revenue = $this->calculateTotalRevenue($branchId, $currentMonth, $endMonth);
        $expenses = $this->calculateTotalExpenses($branchId, $currentMonth, $endMonth);
        
        if ($revenue <= 0) return 0;
        
        $profitMargin = (($revenue - $expenses) / $revenue) * 100;
        
        // Score based on profit margin benchmarks
        if ($profitMargin >= 30) return 100;
        if ($profitMargin >= 20) return 80;
        if ($profitMargin >= 10) return 60;
        if ($profitMargin >= 0) return 40;
        return 0;
    }

    private function getLiquidityScore(?int $branchId): float
    {
        $ratio = $this->getLiquidityRatio($branchId);
        
        if ($ratio >= 3) return 100;
        if ($ratio >= 2) return 80;
        if ($ratio >= 1.5) return 60;
        if ($ratio >= 1) return 40;
        return 20;
    }

    private function getEfficiencyScore(?int $branchId): float
    {
        $currentMonth = now()->startOfMonth();
        $endMonth = now()->endOfMonth();
        
        // Revenue per working hour efficiency
        $workingDays = Carbon::parse($currentMonth)->diffInWeekdays(Carbon::parse($endMonth)) + 1;
        $totalWorkingHours = $workingDays * 8;
        $revenue = $this->calculateTotalRevenue($branchId, $currentMonth, $endMonth);
        
        $revenuePerHour = $totalWorkingHours > 0 ? $revenue / $totalWorkingHours : 0;
        
        // Score based on revenue per hour benchmarks (KES)
        if ($revenuePerHour >= 500) return 100;
        if ($revenuePerHour >= 300) return 80;
        if ($revenuePerHour >= 200) return 60;
        if ($revenuePerHour >= 100) return 40;
        return 20;
    }

    private function getGrowthScore(?int $branchId): float
    {
        $currentMonth = now()->startOfMonth();
        $endMonth = now()->endOfMonth();
        $previousMonth = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();
        
        $currentRevenue = $this->calculateTotalRevenue($branchId, $currentMonth, $endMonth);
        $previousRevenue = $this->calculateTotalRevenue($branchId, $previousMonth, $previousMonthEnd);
        
        if ($previousRevenue <= 0) return 50;
        
        $growthRate = (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
        
        if ($growthRate >= 15) return 100;
        if ($growthRate >= 10) return 80;
        if ($growthRate >= 5) return 60;
        if ($growthRate >= 0) return 40;
        return 20;
    }

    private function getStabilityScore(?int $branchId): float
    {
        // Calculate revenue volatility over last 6 months
        $revenues = [];
        for ($i = 5; $i >= 0; $i--) {
            $startDate = now()->subMonths($i)->startOfMonth();
            $endDate = now()->subMonths($i)->endOfMonth();
            $revenues[] = $this->calculateTotalRevenue($branchId, $startDate, $endDate);
        }
        
        if (count($revenues) < 2) return 50;
        
        $mean = array_sum($revenues) / count($revenues);
        $variance = 0;
        
        foreach ($revenues as $revenue) {
            $variance += pow($revenue - $mean, 2);
        }
        
        $variance /= count($revenues);
        $stdDev = sqrt($variance);
        $coefficientOfVariation = $mean > 0 ? ($stdDev / $mean) * 100 : 100;
        
        // Lower coefficient of variation = higher stability
        if ($coefficientOfVariation <= 10) return 100;
        if ($coefficientOfVariation <= 20) return 80;
        if ($coefficientOfVariation <= 30) return 60;
        if ($coefficientOfVariation <= 50) return 40;
        return 20;
    }

    private function getLiquidityRatio(?int $branchId): float
    {
        $currentMonth = now()->startOfMonth();
        $endMonth = now()->endOfMonth();
        
        $monthlyRevenue = $this->calculateTotalRevenue($branchId, $currentMonth, $endMonth);
        $monthlyExpenses = $this->calculateTotalExpenses($branchId, $currentMonth, $endMonth);
        
        // Assume current cash is 2x monthly net income (simplified)
        $estimatedCash = ($monthlyRevenue - $monthlyExpenses) * 2;
        
        return $monthlyExpenses > 0 ? $estimatedCash / $monthlyExpenses : 0;
    }

    private function getRevenueDiversification(?int $branchId): float
    {
        $currentMonth = now()->startOfMonth();
        $endMonth = now()->endOfMonth();
        
        $serviceRevenue = Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$currentMonth, $endMonth])
            ->where('payment_status', 'completed')
            ->sum('total_amount');
            
        $productRevenue = PosTransaction::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$currentMonth, $endMonth])
            ->where('payment_status', 'completed')
            ->sum('total_amount');
            
        $packageRevenue = PackageSale::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('purchased_at', [$currentMonth, $endMonth])
            ->where('payment_status', 'completed')
            ->sum('final_price');
        
        $totalRevenue = $serviceRevenue + $productRevenue + $packageRevenue;
        
        if ($totalRevenue <= 0) return 0;
        
        // Calculate Herfindahl-Hirschman Index for diversification
        $serviceShare = ($serviceRevenue / $totalRevenue) * 100;
        $productShare = ($productRevenue / $totalRevenue) * 100;
        $packageShare = ($packageRevenue / $totalRevenue) * 100;
        
        $hhi = pow($serviceShare, 2) + pow($productShare, 2) + pow($packageShare, 2);
        
        // Convert HHI to diversification percentage (lower HHI = higher diversification)
        return max(0, 100 - ($hhi / 100));
    }

    private function getExpenseControlRatio(?int $branchId): float
    {
        $currentMonth = now()->startOfMonth();
        $endMonth = now()->endOfMonth();
        
        $revenue = $this->calculateTotalRevenue($branchId, $currentMonth, $endMonth);
        $expenses = $this->calculateTotalExpenses($branchId, $currentMonth, $endMonth);
        
        return $revenue > 0 ? ($expenses / $revenue) * 100 : 100;
    }

    private function getCustomerConcentrationRisk(?int $branchId): string
    {
        $currentMonth = now()->startOfMonth();
        $endMonth = now()->endOfMonth();
        
        $customerRevenues = Client::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('bookings', function($subQ) use ($branchId) {
                    $subQ->where('branch_id', $branchId);
                });
            })
            ->withSum(['bookings as monthly_revenue' => function($q) use ($branchId, $currentMonth, $endMonth) {
                $q->when($branchId, fn($query) => $query->where('branch_id', $branchId))
                  ->whereBetween('appointment_date', [$currentMonth, $endMonth])
                  ->where('payment_status', 'completed');
            }], 'total_amount')
            ->orderByDesc('monthly_revenue')
            ->take(5)
            ->get();
        
        $totalRevenue = $customerRevenues->sum('monthly_revenue');
        $top5Revenue = $customerRevenues->take(5)->sum('monthly_revenue');
        
        if ($totalRevenue <= 0) return 'Unknown';
        
        $concentration = ($top5Revenue / $totalRevenue) * 100;
        
        if ($concentration <= 30) return 'Low';
        if ($concentration <= 50) return 'Medium';
        return 'High';
    }

    private function getGrowthSustainabilityScore(?int $branchId): float
    {
        // Combine multiple factors for sustainability
        $profitabilityScore = $this->getProfitabilityScore($branchId);
        $stabilityScore = $this->getStabilityScore($branchId);
        $diversificationScore = $this->getRevenueDiversification($branchId);
        
        return ($profitabilityScore + $stabilityScore + $diversificationScore) / 3;
    }

    private function getHealthScoreDescription(int $score): string
    {
        if ($score >= 80) return 'Excellent financial health';
        if ($score >= 60) return 'Good financial health';
        if ($score >= 40) return 'Fair financial health';
        if ($score >= 20) return 'Poor financial health';
        return 'Critical financial health';
    }

    private function getHealthScoreColor(int $score): string
    {
        if ($score >= 80) return 'success';
        if ($score >= 60) return 'info';
        if ($score >= 40) return 'warning';
        return 'danger';
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

    private function calculateTotalExpenses(?int $branchId, $startDate, $endDate): float
    {
        return (float) Expense::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->where('status', 'approved')
            ->sum('amount');
    }
}
