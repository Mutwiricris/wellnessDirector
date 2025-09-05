<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Booking;
use App\Models\User;
use App\Models\Service;
use App\Models\Staff;
use App\Filament\Widgets\Reports\BranchPerformanceWidget;
use App\Filament\Widgets\Reports\ClientAnalyticsWidget;
use App\Filament\Widgets\Reports\ServiceAnalyticsWidget;
use App\Filament\Widgets\Reports\StaffPerformanceWidget;
use App\Filament\Widgets\Reports\RevenueAnalyticsWidget;
use Filament\Pages\Page;
use Filament\Facades\Filament;

class BranchReports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Branch Reports';

    protected static ?string $navigationGroup = 'Analytics & Reports';
    
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.branch-reports';

    public function getWidgets(): array
    {
        $tenant = Filament::getTenant();
        
        if (!$tenant) {
            return [];
        }

        return [
            BranchPerformanceWidget::class,
            RevenueAnalyticsWidget::class,
            ClientAnalyticsWidget::class,
            ServiceAnalyticsWidget::class,
            StaffPerformanceWidget::class,
        ];
    }

    public function getWidgetData(): array
    {
        $tenant = Filament::getTenant();
        
        if (!$tenant) {
            return [];
        }

        return [
            'branch' => $tenant,
            'period' => 'current_month',
        ];
    }
}