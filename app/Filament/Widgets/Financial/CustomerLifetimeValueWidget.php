<?php

namespace App\Filament\Widgets\Financial;

use App\Models\Booking;
use App\Models\PosTransaction;
use App\Models\Client;
use App\Models\PackageSale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use Carbon\Carbon;

class CustomerLifetimeValueWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        
        return [
            // Customer Lifetime Value (CLV)
            Stat::make('Avg Customer Lifetime Value', 'KES ' . number_format($this->getAverageCustomerLifetimeValue($tenant?->id), 2))
                ->description('Total revenue per customer')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            // Average Customer Lifespan
            Stat::make('Avg Customer Lifespan', number_format($this->getAverageCustomerLifespan($tenant?->id), 0) . ' days')
                ->description('Days from first to last visit')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),

            // Customer Acquisition Cost (CAC)
            Stat::make('Customer Acquisition Cost', 'KES ' . number_format($this->getCustomerAcquisitionCost($tenant?->id), 2))
                ->description('Marketing cost per new customer')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('warning'),

            // Monthly Recurring Revenue (MRR)
            Stat::make('Monthly Recurring Revenue', 'KES ' . number_format($this->getMonthlyRecurringRevenue($tenant?->id), 2))
                ->description('Predictable monthly income')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            // Customer Churn Rate
            Stat::make('Customer Churn Rate', number_format($this->getCustomerChurnRate($tenant?->id), 1) . '%')
                ->description('Customers lost this month')
                ->descriptionIcon('heroicon-m-arrow-right-on-rectangle')
                ->color($this->getCustomerChurnRate($tenant?->id) <= 5 ? 'success' : 'danger'),

            // Revenue per Customer Segment
            Stat::make('VIP Customer Revenue', 'KES ' . number_format($this->getVIPCustomerRevenue($tenant?->id), 2))
                ->description('Top 20% customer contribution')
                ->descriptionIcon('heroicon-m-star')
                ->color('success'),
        ];
    }

    private function getAverageCustomerLifetimeValue(?int $branchId): float
    {
        $customers = Client::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('bookings', function($subQ) use ($branchId) {
                    $subQ->where('branch_id', $branchId);
                });
            })
            ->withSum('bookings as total_booking_revenue', 'total_amount')
            ->get();

        if ($customers->isEmpty()) return 0;

        $totalRevenue = $customers->sum('total_booking_revenue');
        return $totalRevenue / $customers->count();
    }

    private function getAverageCustomerLifespan(?int $branchId): float
    {
        $customers = Client::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('bookings', function($subQ) use ($branchId) {
                    $subQ->where('branch_id', $branchId);
                });
            })
            ->with(['bookings' => function($q) use ($branchId) {
                $q->when($branchId, fn($query) => $query->where('branch_id', $branchId))
                  ->orderBy('appointment_date');
            }])
            ->get();

        $totalLifespanDays = 0;
        $customersWithMultipleVisits = 0;

        foreach ($customers as $customer) {
            if ($customer->bookings->count() > 1) {
                $firstVisit = $customer->bookings->first()->appointment_date;
                $lastVisit = $customer->bookings->last()->appointment_date;
                $totalLifespanDays += Carbon::parse($firstVisit)->diffInDays(Carbon::parse($lastVisit));
                $customersWithMultipleVisits++;
            }
        }

        return $customersWithMultipleVisits > 0 ? $totalLifespanDays / $customersWithMultipleVisits : 0;
    }

    private function getCustomerAcquisitionCost(?int $branchId): float
    {
        // Estimate based on marketing expenses (assuming 30% of total expenses are marketing)
        $currentMonth = now()->startOfMonth();
        $endMonth = now()->endOfMonth();
        
        $marketingExpenses = \App\Models\Expense::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$currentMonth, $endMonth])
            ->where('category', 'marketing')
            ->sum('amount');

        $newCustomers = Client::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('bookings', function($subQ) use ($branchId, $currentMonth, $endMonth) {
                    $subQ->where('branch_id', $branchId)
                         ->whereBetween('appointment_date', [$currentMonth, $endMonth]);
                });
            })
            ->whereHas('bookings', function($q) use ($branchId, $currentMonth) {
                $q->when($branchId, fn($query) => $query->where('branch_id', $branchId))
                  ->where('appointment_date', '>=', $currentMonth)
                  ->whereNotExists(function($subQ) use ($branchId, $currentMonth) {
                      $subQ->select(\DB::raw(1))
                           ->from('bookings as b2')
                           ->whereColumn('b2.client_id', 'bookings.client_id')
                           ->when($branchId, fn($query) => $query->where('b2.branch_id', $branchId))
                           ->where('b2.appointment_date', '<', $currentMonth);
                  });
            })
            ->count();

        return $newCustomers > 0 ? $marketingExpenses / $newCustomers : 0;
    }

    private function getMonthlyRecurringRevenue(?int $branchId): float
    {
        // Calculate based on active package sales and recurring bookings
        $packageRevenue = PackageSale::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('status', 'active')
            ->where('payment_status', 'completed')
            ->sum('final_price');

        // Estimate monthly recurring from regular customers (customers with 3+ bookings in last 3 months)
        $recurringCustomers = Client::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('bookings', function($subQ) use ($branchId) {
                    $subQ->where('branch_id', $branchId)
                         ->where('appointment_date', '>=', now()->subMonths(3))
                         ->groupBy('client_id')
                         ->havingRaw('COUNT(*) >= 3');
                });
            })
            ->withAvg('bookings as avg_booking_value', 'total_amount')
            ->get();

        $estimatedRecurringRevenue = $recurringCustomers->sum('avg_booking_value');

        return ($packageRevenue / 12) + $estimatedRecurringRevenue; // Annualized packages + monthly recurring
    }

    private function getCustomerChurnRate(?int $branchId): float
    {
        $currentMonth = now()->startOfMonth();
        $previousMonth = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();

        // Customers who had bookings in previous month
        $customersLastMonth = Client::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('bookings', function($subQ) use ($branchId) {
                    $subQ->where('branch_id', $branchId);
                });
            })
            ->whereHas('bookings', function($q) use ($branchId, $previousMonth, $previousMonthEnd) {
                $q->when($branchId, fn($query) => $query->where('branch_id', $branchId))
                  ->whereBetween('appointment_date', [$previousMonth, $previousMonthEnd]);
            })
            ->count();

        // Customers from last month who didn't book this month
        $churnedCustomers = Client::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('bookings', function($subQ) use ($branchId) {
                    $subQ->where('branch_id', $branchId);
                });
            })
            ->whereHas('bookings', function($q) use ($branchId, $previousMonth, $previousMonthEnd) {
                $q->when($branchId, fn($query) => $query->where('branch_id', $branchId))
                  ->whereBetween('appointment_date', [$previousMonth, $previousMonthEnd]);
            })
            ->whereDoesntHave('bookings', function($q) use ($branchId, $currentMonth) {
                $q->when($branchId, fn($query) => $query->where('branch_id', $branchId))
                  ->where('appointment_date', '>=', $currentMonth);
            })
            ->count();

        return $customersLastMonth > 0 ? ($churnedCustomers / $customersLastMonth) * 100 : 0;
    }

    private function getVIPCustomerRevenue(?int $branchId): float
    {
        // Get top 20% of customers by revenue
        $customers = Client::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('bookings', function($subQ) use ($branchId) {
                    $subQ->where('branch_id', $branchId);
                });
            })
            ->withSum(['bookings as total_revenue' => function($q) use ($branchId) {
                $q->when($branchId, fn($query) => $query->where('branch_id', $branchId))
                  ->where('payment_status', 'completed');
            }], 'total_amount')
            ->orderByDesc('total_revenue')
            ->get();

        $topCustomersCount = max(1, intval($customers->count() * 0.2)); // Top 20%
        $topCustomers = $customers->take($topCustomersCount);

        return $topCustomers->sum('total_revenue');
    }
}
