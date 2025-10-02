<?php

namespace App\Filament\Widgets\Financial;

use App\Models\Booking;
use App\Models\PosTransaction;
use App\Models\PosTransactionItem;
use App\Models\Service;
use App\Models\Product;
use App\Models\StaffCommission;
use App\Models\StaffSchedule;
use App\Models\Expense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ProfitabilityWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    protected static ?string $pollingInterval = '15s';

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth();
        $endDate = $this->filters['endDate'] ?? now()->endOfMonth();

        // Service Profitability Analysis
        $serviceStats = $this->calculateServiceProfitability($tenant?->id, $startDate, $endDate);
        
        // Product Profitability Analysis
        $productStats = $this->calculateProductProfitability($tenant?->id, $startDate, $endDate);
        
        // Staff Performance ROI
        $staffROI = $this->calculateStaffROI($tenant?->id, $startDate, $endDate);
        
        // Overall Margin Analysis
        $overallMargin = $this->calculateOverallMargin($tenant?->id, $startDate, $endDate);

        return [
            Stat::make('Service Gross Margin', number_format($serviceStats['margin'], 1) . '%')
                ->description('KES ' . number_format($serviceStats['profit'], 0) . ' profit from services')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color($serviceStats['margin'] >= 60 ? 'success' : ($serviceStats['margin'] >= 40 ? 'warning' : 'danger'))
                ->chart($this->getServiceProfitChart($tenant?->id, $startDate, $endDate)),

            Stat::make('Product Gross Margin', number_format($productStats['margin'], 1) . '%')
                ->description('KES ' . number_format($productStats['profit'], 0) . ' profit from products')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color($productStats['margin'] >= 50 ? 'success' : ($productStats['margin'] >= 30 ? 'warning' : 'danger'))
                ->chart($this->getProductProfitChart($tenant?->id, $startDate, $endDate)),

            Stat::make('Staff Revenue per Hour', 'KES ' . number_format($staffROI['revenue_per_hour'], 0))
                ->description('Average: KES ' . number_format($staffROI['avg_commission'], 0) . ' commission per staff')
                ->descriptionIcon('heroicon-m-users')
                ->color('info')
                ->chart($this->getStaffEfficiencyChart($tenant?->id, $startDate, $endDate)),

            Stat::make('Overall Profit Margin', number_format($overallMargin['margin'], 1) . '%')
                ->description('After all expenses and commissions')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($overallMargin['margin'] >= 25 ? 'success' : ($overallMargin['margin'] >= 15 ? 'warning' : 'danger')),

            Stat::make('Cost per Transaction', 'KES ' . number_format($overallMargin['cost_per_transaction'], 0))
                ->description($overallMargin['total_transactions'] . ' total transactions')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

        ];
    }

    private function calculateServiceProfitability(?int $branchId, $startDate, $endDate): array
    {
        // Service Revenue
        $serviceRevenue = Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        // Service Direct Costs (staff commissions + direct service expenses)
        $serviceCommissions = StaffCommission::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('earned_date', [$startDate, $endDate])
            ->where('approval_status', 'approved')
            ->sum('commission_amount');

        // Service-related expenses (supplies)
        $serviceExpenses = Expense::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->where('category', 'supplies')
            ->where('status', 'approved')
            ->sum('amount');

        $serviceCosts = $serviceCommissions + $serviceExpenses;
        $serviceProfit = $serviceRevenue - $serviceCosts;
        $serviceMargin = $serviceRevenue > 0 ? ($serviceProfit / $serviceRevenue) * 100 : 0;

        return [
            'revenue' => (float) $serviceRevenue,
            'costs' => (float) $serviceCosts,
            'profit' => (float) $serviceProfit,
            'margin' => (float) $serviceMargin,
        ];
    }

    private function calculateProductProfitability(?int $branchId, $startDate, $endDate): array
    {
        // Product Revenue from POS
        $productRevenue = PosTransaction::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        // Product Cost of Goods Sold
        $productCOGS = PosTransactionItem::query()
            ->where('item_type', 'product')
            ->whereHas('posTransaction', function($q) use ($branchId, $startDate, $endDate) {
                $q->when($branchId, fn($subQ) => $subQ->where('branch_id', $branchId))
                  ->whereBetween('created_at', [$startDate, $endDate])
                  ->where('payment_status', 'completed');
            })
            ->join('products', 'pos_transaction_items.item_id', '=', 'products.id')
            ->selectRaw('SUM(pos_transaction_items.quantity * products.cost_price) as total_cogs')
            ->value('total_cogs') ?? 0;

        $productProfit = $productRevenue - $productCOGS;
        $productMargin = $productRevenue > 0 ? ($productProfit / $productRevenue) * 100 : 0;

        return [
            'revenue' => (float) $productRevenue,
            'costs' => (float) $productCOGS,
            'profit' => (float) $productProfit,
            'margin' => (float) $productMargin,
        ];
    }

    private function calculateStaffROI(?int $branchId, $startDate, $endDate): array
    {
        // Total staff hours from schedules
        $totalWorkingMinutes = StaffSchedule::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('date', [$startDate, $endDate])
            ->where('is_available', true)
            ->get()
            ->sum('total_working_minutes');

        $estimatedHours = $totalWorkingMinutes / 60;

        // Revenue generated by staff
        $staffRevenue = Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        // Average commission per staff
        $avgCommission = StaffCommission::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('booking', fn($subQ) => $subQ->where('branch_id', $branchId));
            })
            ->whereBetween('earned_date', [$startDate, $endDate])
            ->where('approval_status', 'approved')
            ->avg('commission_amount') ?? 0;

        $revenuePerHour = $estimatedHours > 0 ? $staffRevenue / $estimatedHours : 0;

        return [
            'revenue_per_hour' => (float) $revenuePerHour,
            'avg_commission' => (float) $avgCommission,
            'total_hours' => (float) $estimatedHours,
        ];
    }

    private function calculateOverallMargin(?int $branchId, $startDate, $endDate): array
    {
        // Total Revenue
        $totalRevenue = $this->calculateTotalRevenue($branchId, $startDate, $endDate);
        
        // Total Costs
        $totalExpenses = Expense::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->where('status', 'approved')
            ->sum('amount');

        $totalCommissions = StaffCommission::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('earned_date', [$startDate, $endDate])
            ->where('approval_status', 'approved')
            ->sum('commission_amount');

        $totalCosts = $totalExpenses + $totalCommissions;
        $netProfit = $totalRevenue - $totalCosts;
        $overallMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

        // Transaction metrics
        $totalTransactions = $this->calculateTotalTransactions($branchId, $startDate, $endDate);
        $costPerTransaction = $totalTransactions > 0 ? $totalCosts / $totalTransactions : 0;


        return [
            'margin' => (float) $overallMargin,
            'total_transactions' => (int) $totalTransactions,
            'cost_per_transaction' => (float) $costPerTransaction,
        ];
    }

    private function calculateTotalRevenue(?int $branchId, $startDate, $endDate): float
    {
        // Service Revenue
        $serviceRevenue = Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        // Product Revenue
        $productRevenue = PosTransaction::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        return (float) ($serviceRevenue + $productRevenue);
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

    private function getServiceProfitChart(?int $branchId, $startDate, $endDate): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = \Carbon\Carbon::parse($endDate)->subDays($i);
            $serviceStats = $this->calculateServiceProfitability($branchId, $date, $date);
            $data[] = $serviceStats['margin'];
        }
        return $data;
    }

    private function getProductProfitChart(?int $branchId, $startDate, $endDate): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = \Carbon\Carbon::parse($endDate)->subDays($i);
            $productStats = $this->calculateProductProfitability($branchId, $date, $date);
            $data[] = $productStats['margin'];
        }
        return $data;
    }

    private function getStaffEfficiencyChart(?int $branchId, $startDate, $endDate): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = \Carbon\Carbon::parse($endDate)->subDays($i);
            $staffROI = $this->calculateStaffROI($branchId, $date, $date);
            $data[] = $staffROI['revenue_per_hour'];
        }
        return $data;
    }

}