<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Booking;
use App\Models\User;
use App\Models\Staff;
use App\Models\Service;
use Filament\Pages\Page;
use Filament\Facades\Filament;

class SystemOverview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'System Overview';

    protected static ?string $navigationGroup = 'Dashboard';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.system-overview';

    protected function getViewData(): array
    {
        $user = auth()->user();
        
        if (!$user || !$user->isDirector()) {
            return [];
        }

        $branches = $user->getTenants(Filament::getCurrentPanel());
        
        // Cross-branch statistics
        $totalBookings = Booking::whereIn('branch_id', $branches->pluck('id'))->count();
        $todaysBookings = Booking::whereIn('branch_id', $branches->pluck('id'))
            ->whereDate('appointment_date', today())
            ->count();
        $monthlyRevenue = Booking::whereIn('branch_id', $branches->pluck('id'))
            ->whereMonth('appointment_date', now()->month)
            ->where('payment_status', 'completed')
            ->sum('total_amount');
        $totalStaff = Staff::whereHas('branches', function($q) use ($branches) {
            $q->whereIn('branches.id', $branches->pluck('id'));
        })->count();
        $totalServices = Service::whereHas('branches', function($q) use ($branches) {
            $q->whereIn('branches.id', $branches->pluck('id'));
        })->count();
        $totalClients = User::where('user_type', 'client')
            ->whereHas('bookings', function($q) use ($branches) {
                $q->whereIn('branch_id', $branches->pluck('id'));
            })->count();

        return [
            'branches' => $branches,
            'system_stats' => [
                'total_branches' => $branches->count(),
                'total_bookings' => $totalBookings,
                'todays_bookings' => $todaysBookings,
                'monthly_revenue' => $monthlyRevenue,
                'total_staff' => $totalStaff,
                'total_services' => $totalServices,
                'total_clients' => $totalClients,
            ],
            'branch_performance' => $this->getBranchPerformance($branches),
        ];
    }

    private function getBranchPerformance($branches): array
    {
        $performance = [];
        
        foreach ($branches as $branch) {
            $monthlyBookings = Booking::where('branch_id', $branch->id)
                ->whereMonth('appointment_date', now()->month)
                ->count();
            
            $monthlyRevenue = Booking::where('branch_id', $branch->id)
                ->whereMonth('appointment_date', now()->month)
                ->where('payment_status', 'completed')
                ->sum('total_amount');
            
            $performance[] = [
                'branch' => $branch,
                'monthly_bookings' => $monthlyBookings,
                'monthly_revenue' => $monthlyRevenue,
                'staff_count' => Staff::whereHas('branches', function($q) use ($branch) {
                    $q->where('branches.id', $branch->id);
                })->count(),
            ];
        }
        
        return $performance;
    }
}