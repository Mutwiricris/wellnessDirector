<?php

namespace App\Filament\Widgets;

use App\Models\Branch;
use App\Models\Booking;
use App\Models\User;
use App\Models\Service;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DirectorStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalBranches = Branch::withoutGlobalScopes()->active()->count();
        $totalBookings = Booking::withoutGlobalScopes()->count();
        $todayBookings = Booking::withoutGlobalScopes()->whereDate('appointment_date', today())->count();
        $totalRevenue = Booking::withoutGlobalScopes()->where('payment_status', 'completed')->sum('total_amount');
        $totalCustomers = User::withoutGlobalScopes()->where('user_type', 'user')->count();
        $totalServices = Service::withoutGlobalScopes()->where('status', 'active')->count();

        return [
            Stat::make('Total Branches', $totalBranches)
                ->description('Active spa branches')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('success'),
                
            Stat::make('Today\'s Bookings', $todayBookings)
                ->description('Appointments for today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),
                
            Stat::make('Total Bookings', number_format($totalBookings))
                ->description('All time bookings')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('info'),
                
            Stat::make('Total Revenue', 'KES ' . number_format($totalRevenue, 2))
                ->description('Completed payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
                
            Stat::make('Total Customers', number_format($totalCustomers))
                ->description('Registered customers')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
                
            Stat::make('Active Services', $totalServices)
                ->description('Available services')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('info'),
        ];
    }
}