<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Facades\Filament;

class QuickActions extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'Quick Actions';

    protected static ?string $navigationGroup = 'Dashboard';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.quick-actions';

    protected function getViewData(): array
    {
        $tenant = Filament::getTenant();
        
        if (!$tenant) {
            return [];
        }

        return [
            'branch' => $tenant,
            'quick_stats' => $this->getQuickStats(),
        ];
    }

    private function getQuickStats(): array
    {
        $tenant = Filament::getTenant();
        
        if (!$tenant) {
            return [];
        }

        // Today's quick stats
        $today = today();
        
        return [
            'todays_bookings' => \App\Models\Booking::where('branch_id', $tenant->id)
                ->whereDate('appointment_date', $today)
                ->count(),
            'pending_bookings' => \App\Models\Booking::where('branch_id', $tenant->id)
                ->whereDate('appointment_date', $today)
                ->where('status', 'pending')
                ->count(),
            'todays_revenue' => \App\Models\Booking::where('branch_id', $tenant->id)
                ->whereDate('appointment_date', $today)
                ->where('payment_status', 'completed')
                ->sum('total_amount'),
            'active_staff' => \App\Models\Staff::whereHas('branches', function($q) use ($tenant) {
                $q->where('branches.id', $tenant->id);
            })->where('status', 'active')->count(),
        ];
    }
}