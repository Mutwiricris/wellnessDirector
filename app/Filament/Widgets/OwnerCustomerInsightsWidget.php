<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Booking;
use App\Models\PosTransaction;
use App\Models\PackageSale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use Carbon\Carbon;

class OwnerCustomerInsightsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '300s';
    
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 2,
        'lg' => 1,
        'xl' => 1,
        '2xl' => 1,
    ];

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return [];

        $thisMonth = Carbon::now()->startOfMonth();
        $now = Carbon::now();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $customerMetrics = $this->getCustomerMetrics($tenant->id, $thisMonth, $now);
        $lastMonthMetrics = $this->getCustomerMetrics($tenant->id, $lastMonth, $lastMonthEnd);

        // Calculate growth
        $newCustomerGrowth = $lastMonthMetrics['new_customers'] > 0 
            ? (($customerMetrics['new_customers'] - $lastMonthMetrics['new_customers']) / $lastMonthMetrics['new_customers']) * 100
            : 0;

        $retentionGrowth = $lastMonthMetrics['retention_rate'] > 0 
            ? $customerMetrics['retention_rate'] - $lastMonthMetrics['retention_rate']
            : 0;

        return [
            Stat::make('Total Customers', number_format($customerMetrics['total_customers']))
                ->description('Active customer base')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('New Customers', $customerMetrics['new_customers'])
                ->description($newCustomerGrowth >= 0 
                    ? "↗️ +" . number_format($newCustomerGrowth, 1) . "% from last month"
                    : "↘️ " . number_format($newCustomerGrowth, 1) . "% from last month")
                ->descriptionIcon('heroicon-m-user-plus')
                ->color($newCustomerGrowth >= 0 ? 'success' : 'warning'),

            Stat::make('Retention Rate', number_format($customerMetrics['retention_rate'], 1) . '%')
                ->description($retentionGrowth >= 0 
                    ? "↗️ +" . number_format($retentionGrowth, 1) . "% from last month"
                    : "↘️ " . number_format($retentionGrowth, 1) . "% from last month")
                ->descriptionIcon('heroicon-m-heart')
                ->color($customerMetrics['retention_rate'] >= 70 ? 'success' : 'warning'),

            Stat::make('Avg Customer Value', 'KES ' . number_format($customerMetrics['avg_customer_value'], 0))
                ->description('Average lifetime value')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Repeat Customers', number_format($customerMetrics['repeat_customer_rate'], 1) . '%')
                ->description('Customers with multiple visits')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($customerMetrics['repeat_customer_rate'] >= 50 ? 'success' : 'warning'),

            Stat::make('Avg Visit Frequency', number_format($customerMetrics['avg_visit_frequency'], 1))
                ->description('Visits per customer per month')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),
        ];
    }

    private function getCustomerMetrics(int $branchId, Carbon $start, Carbon $end): array
    {
        // Total active customers (customers who have made bookings)
        $totalCustomers = User::where('user_type', 'user')
            ->whereHas('bookings', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->count();

        // New customers this period
        $newCustomers = User::where('user_type', 'user')
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('bookings', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->count();

        // Customer retention rate (customers who made repeat visits)
        $customersWithBookings = User::where('user_type', 'user')
            ->whereHas('bookings', function ($query) use ($branchId, $start, $end) {
                $query->where('branch_id', $branchId)
                      ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->get();

        $repeatCustomers = 0;
        $totalVisits = 0;
        $totalRevenue = 0;

        foreach ($customersWithBookings as $customer) {
            $customerBookings = Booking::where('client_id', $customer->id)
                ->where('branch_id', $branchId)
                ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
                ->where('status', 'completed')
                ->count();

            $customerRevenue = Booking::where('client_id', $customer->id)
                ->where('branch_id', $branchId)
                ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
                ->where('payment_status', 'completed')
                ->sum('total_amount');

            // Add POS transaction revenue (using client_id)
            $customerPosRevenue = PosTransaction::where('client_id', $customer->id)
                ->where('branch_id', $branchId)
                ->whereBetween('created_at', [$start, $end])
                ->where('payment_status', 'completed')
                ->sum('total_amount');

            $totalRevenue += $customerRevenue + $customerPosRevenue;
            $totalVisits += $customerBookings;

            if ($customerBookings > 1) {
                $repeatCustomers++;
            }
        }

        $activeCustomersThisPeriod = $customersWithBookings->count();
        $retentionRate = $activeCustomersThisPeriod > 0 ? ($repeatCustomers / $activeCustomersThisPeriod) * 100 : 0;
        $repeatCustomerRate = $totalCustomers > 0 ? ($repeatCustomers / $totalCustomers) * 100 : 0;
        $avgCustomerValue = $activeCustomersThisPeriod > 0 ? $totalRevenue / $activeCustomersThisPeriod : 0;
        $avgVisitFrequency = $activeCustomersThisPeriod > 0 ? $totalVisits / $activeCustomersThisPeriod : 0;

        return [
            'total_customers' => $totalCustomers,
            'new_customers' => $newCustomers,
            'retention_rate' => $retentionRate,
            'repeat_customer_rate' => $repeatCustomerRate,
            'avg_customer_value' => $avgCustomerValue,
            'avg_visit_frequency' => $avgVisitFrequency,
        ];
    }
}
