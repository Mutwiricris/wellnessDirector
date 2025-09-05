<?php

namespace App\Filament\Widgets\Financial;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PosTransaction;
use App\Models\Staff;
use App\Models\Client;
use App\Models\Service;
use App\Models\PackageSale;
use App\Models\GiftVoucher;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use Carbon\Carbon;

class AdvancedKPIWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        $dateRange = $this->getDateRange();
        [$startDate, $endDate] = $dateRange;

        return [
            // Revenue per Working Hour (Key Spa KPI)
            Stat::make('Revenue per Working Hour', 'KES ' . number_format($this->getRevenuePerWorkingHour($tenant?->id, $startDate, $endDate), 2))
                ->description('Productivity efficiency metric')
                ->descriptionIcon('heroicon-m-clock')
                ->color('success'),

            // Average Revenue per Client Visit
            Stat::make('Avg Revenue per Visit', 'KES ' . number_format($this->getAverageRevenuePerVisit($tenant?->id, $startDate, $endDate), 2))
                ->description($this->getTotalVisits($tenant?->id, $startDate, $endDate) . ' total visits')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            // Client Retention Rate
            Stat::make('Client Retention Rate', number_format($this->getClientRetentionRate($tenant?->id, $startDate, $endDate), 1) . '%')
                ->description('Repeat client percentage')
                ->descriptionIcon('heroicon-m-heart')
                ->color($this->getClientRetentionRate($tenant?->id, $startDate, $endDate) >= 70 ? 'success' : 'warning'),

            // Service Utilization Rate
            Stat::make('Service Utilization', number_format($this->getServiceUtilizationRate($tenant?->id, $startDate, $endDate), 1) . '%')
                ->description('Booking capacity efficiency')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($this->getServiceUtilizationRate($tenant?->id, $startDate, $endDate) >= 75 ? 'success' : 'warning'),

            // Gift Card Redemption Rate
            Stat::make('Gift Card Redemption', number_format($this->getGiftCardRedemptionRate($tenant?->id), 1) . '%')
                ->description('Gift voucher performance')
                ->descriptionIcon('heroicon-m-gift')
                ->color('info'),

            // Package Sales Conversion
            Stat::make('Package Conversion Rate', number_format($this->getPackageConversionRate($tenant?->id, $startDate, $endDate), 1) . '%')
                ->description('Service package sales rate')
                ->descriptionIcon('heroicon-m-cube')
                ->color($this->getPackageConversionRate($tenant?->id, $startDate, $endDate) >= 15 ? 'success' : 'warning'),
        ];
    }

    private function getRevenuePerWorkingHour(?int $branchId, $startDate, $endDate): float
    {
        $totalRevenue = $this->calculateTotalRevenue($branchId, $startDate, $endDate);
        
        // Calculate total working hours (assuming 8 hours per day, adjustable)
        $workingDays = Carbon::parse($startDate)->diffInWeekdays(Carbon::parse($endDate)) + 1;
        $totalWorkingHours = $workingDays * 8; // 8 hours per day
        
        return $totalWorkingHours > 0 ? $totalRevenue / $totalWorkingHours : 0;
    }

    private function getAverageRevenuePerVisit(?int $branchId, $startDate, $endDate): float
    {
        $totalRevenue = $this->calculateTotalRevenue($branchId, $startDate, $endDate);
        $totalVisits = $this->getTotalVisits($branchId, $startDate, $endDate);
        
        return $totalVisits > 0 ? $totalRevenue / $totalVisits : 0;
    }

    private function getTotalVisits(?int $branchId, $startDate, $endDate): int
    {
        return Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->count();
    }

    private function getClientRetentionRate(?int $branchId, $startDate, $endDate): float
    {
        // Get clients who had appointments in the period
        $clientsInPeriod = Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->distinct('client_id')
            ->count('client_id');

        // Get clients who had previous appointments (repeat clients)
        $repeatClients = Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->whereHas('client', function($q) use ($branchId, $startDate) {
                $q->whereHas('bookings', function($subQ) use ($branchId, $startDate) {
                    $subQ->when($branchId, fn($query) => $query->where('branch_id', $branchId))
                         ->where('appointment_date', '<', $startDate);
                });
            })
            ->distinct('client_id')
            ->count('client_id');

        return $clientsInPeriod > 0 ? ($repeatClients / $clientsInPeriod) * 100 : 0;
    }

    private function getServiceUtilizationRate(?int $branchId, $startDate, $endDate): float
    {
        // Total possible booking slots (assuming 10 slots per day)
        $workingDays = Carbon::parse($startDate)->diffInWeekdays(Carbon::parse($endDate)) + 1;
        $totalPossibleSlots = $workingDays * 10; // 10 slots per day
        
        $actualBookings = Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->whereIn('status', ['completed', 'confirmed'])
            ->count();

        return $totalPossibleSlots > 0 ? ($actualBookings / $totalPossibleSlots) * 100 : 0;
    }

    private function getGiftCardRedemptionRate(?int $branchId): float
    {
        $totalGiftCards = GiftVoucher::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('status', '!=', 'cancelled')
            ->count();

        $redeemedGiftCards = GiftVoucher::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereIn('status', ['redeemed'])
            ->orWhere('remaining_amount', '<', 'original_amount')
            ->count();

        return $totalGiftCards > 0 ? ($redeemedGiftCards / $totalGiftCards) * 100 : 0;
    }

    private function getPackageConversionRate(?int $branchId, $startDate, $endDate): float
    {
        $totalClients = Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->distinct('client_id')
            ->count('client_id');

        $packagePurchases = PackageSale::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('purchased_at', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->distinct('user_id')
            ->count('user_id');

        return $totalClients > 0 ? ($packagePurchases / $totalClients) * 100 : 0;
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

    private function getDateRange(): array
    {
        return [now()->startOfMonth(), now()->endOfMonth()];
    }
}
