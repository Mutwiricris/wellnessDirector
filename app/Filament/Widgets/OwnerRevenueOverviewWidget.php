<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\PosTransaction;
use App\Models\PackageSale;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Carbon\Carbon;

class OwnerRevenueOverviewWidget extends BaseWidget
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

        // Use global date filters
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth();
        $endDate = $this->filters['endDate'] ?? now()->endOfMonth();
        
        // Calculate previous period for comparison
        $periodDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        $previousStart = Carbon::parse($startDate)->subDays($periodDays + 1);
        $previousEnd = Carbon::parse($startDate)->subDay();

        // Today's Revenue
        $today = Carbon::today();
        $todayRevenue = $this->getTotalRevenue($today, $today->copy()->endOfDay(), $tenant->id);
        
        // Current Period Revenue
        $currentRevenue = $this->getTotalRevenue(Carbon::parse($startDate), Carbon::parse($endDate), $tenant->id);
        
        // Previous Period Revenue for comparison
        $previousRevenue = $this->getTotalRevenue($previousStart, $previousEnd, $tenant->id);
        
        // Calculate growth percentage
        $growthPercentage = $previousRevenue > 0 
            ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 1)
            : 0;

        // Average daily revenue for current period
        $periodDays = max(1, $periodDays);
        $avgDailyRevenue = $currentRevenue / $periodDays;

        // Revenue breakdown for current period
        $serviceRevenue = $this->getServiceRevenue(Carbon::parse($startDate), Carbon::parse($endDate), $tenant->id);
        $productRevenue = $this->getProductRevenue(Carbon::parse($startDate), Carbon::parse($endDate), $tenant->id);
        $packageRevenue = $this->getPackageRevenue(Carbon::parse($startDate), Carbon::parse($endDate), $tenant->id);

        return [
            Stat::make('Today\'s Revenue', 'KES ' . number_format($todayRevenue, 2))
                ->description('Revenue generated today')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart($this->getRevenueChart($tenant->id, 7)), // Last 7 days

            Stat::make('Monthly Revenue', 'KES ' . number_format($currentRevenue, 2))
                ->description($growthPercentage >= 0 
                    ? "↗️ " . number_format(abs($growthPercentage), 1) . "% from previous period" 
                    : "↘️ " . number_format(abs($growthPercentage), 1) . "% from previous period")
                ->descriptionIcon($growthPercentage >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($growthPercentage >= 0 ? 'success' : 'danger'),

            Stat::make('Avg Daily Revenue', 'KES ' . number_format($avgDailyRevenue, 2))
                ->description('Average per day this period')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make('Service Revenue', 'KES ' . number_format($serviceRevenue, 2))
                ->description(number_format(($serviceRevenue / max($currentRevenue, 1)) * 100, 1) . '% of total')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('primary'),

            Stat::make('Product Revenue', 'KES ' . number_format($productRevenue, 2))
                ->description(number_format(($productRevenue / max($currentRevenue, 1)) * 100, 1) . '% of total')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('warning'),

            Stat::make('Package Revenue', 'KES ' . number_format($packageRevenue, 2))
                ->description(number_format(($packageRevenue / max($currentRevenue, 1)) * 100, 1) . '% of total')
                ->descriptionIcon('heroicon-m-gift')
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

        $packageRevenue = PackageSale::where('branch_id', $branchId)
            ->whereBetween('purchased_at', [$start, $end])
            ->where('payment_status', 'completed')
            ->sum('final_price');

        return $bookingRevenue + $posRevenue + $packageRevenue;
    }

    private function getServiceRevenue(Carbon $start, Carbon $end, int $branchId): float
    {
        return Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
            ->where('payment_status', 'completed')
            ->sum('total_amount');
    }

    private function getProductRevenue(Carbon $start, Carbon $end, int $branchId): float
    {
        return PosTransaction::where('branch_id', $branchId)
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'completed')
            ->where('transaction_type', 'sale')
            ->sum('total_amount');
    }

    private function getPackageRevenue(Carbon $start, Carbon $end, int $branchId): float
    {
        return PackageSale::where('branch_id', $branchId)
            ->whereBetween('purchased_at', [$start, $end])
            ->where('payment_status', 'completed')
            ->sum('final_price');
    }

    private function getRevenueChart(int $branchId, int $days = 7): array
    {
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $revenue = $this->getTotalRevenue($date, $date->copy()->endOfDay(), $branchId);
            $data[] = $revenue;
        }
        return $data;
    }
}
