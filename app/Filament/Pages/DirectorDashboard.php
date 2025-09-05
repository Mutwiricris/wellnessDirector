<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Booking;
use App\Models\User;
use App\Models\Service;
use App\Filament\Widgets\DirectorStatsWidget;
use App\Filament\Widgets\BranchOverviewWidget;
use App\Filament\Widgets\BranchKPIWidget;
use App\Filament\Widgets\BranchOperationsWidget;
use App\Filament\Widgets\BranchFinancialWidget;
use App\Filament\Widgets\BranchStaffWidget;
use App\Filament\Widgets\BranchServicesWidget;
// Enhanced Owner Dashboard Widgets
use App\Filament\Widgets\OwnerRevenueOverviewWidget;
use App\Filament\Widgets\OwnerProfitabilityWidget;
use App\Filament\Widgets\OwnerStaffPerformanceWidget;
use App\Filament\Widgets\OwnerCustomerInsightsWidget;
use App\Filament\Widgets\OwnerOperationalMetricsWidget;
use App\Filament\Widgets\OwnerInventoryWidget;
use App\Filament\Widgets\OwnerBookingAnalyticsWidget;
use App\Filament\Widgets\OwnerExpenseAnalyticsWidget;
use App\Filament\Widgets\OwnerRevenueStreamsWidget;
use App\Filament\Widgets\OwnerTrendAnalysisWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\ChartWidget;

class DirectorDashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    
    protected static ?string $navigationLabel = 'Dashboard';
    
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $title = 'Spa Owner Command Center';
    
    protected static ?string $subNavigation = null;
    
    protected int | string | array $columnSpan = 'full';

    public function getWidgets(): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        if ($tenant) {
            // Essential business insights dashboard
            return [
                // Row 1: Core Financial Performance
                OwnerRevenueOverviewWidget::class,
                OwnerProfitabilityWidget::class,
                
                // Row 2: Key Operations & Trends
                OwnerBookingAnalyticsWidget::class,
                OwnerTrendAnalysisWidget::class,
                
                // Row 3: Staff & Customer Performance
                OwnerStaffPerformanceWidget::class,
                OwnerOperationalMetricsWidget::class,
            ];
        } else {
            // Cross-branch overview dashboard
            return [
                DirectorStatsWidget::class,
                BranchOverviewWidget::class,
            ];
        }
    }
    
    public function getTitle(): string
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        if ($tenant) {
            return "Owner Dashboard - {$tenant->name}";
        }
        
        return 'Spa Owner Command Center';
    }
    
    public function getSubheading(): ?string
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        if ($tenant) {
            return "Complete business intelligence for {$tenant->name} operations";
        }
        
        return 'Select a branch to view detailed analytics and insights';
    }
    
    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'sm' => 1,
            'md' => 2,
            'lg' => 2,
            'xl' => 2,
            '2xl' => 2,
        ];
    }
    
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export_report')
                ->label('Export Business Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    $this->exportBusinessReport();
                }),
                
            \Filament\Actions\Action::make('schedule_report')
                ->label('Schedule Reports')
                ->icon('heroicon-o-clock')
                ->color('info')
                ->action(function () {
                    $this->scheduleReports();
                }),
                
            \Filament\Actions\Action::make('alerts')
                ->label('Business Alerts')
                ->icon('heroicon-o-bell-alert')
                ->color('warning')
                ->action(function () {
                    $this->configureAlerts();
                }),
        ];
    }
    
    protected function exportBusinessReport(): void
    {
        // Implementation for comprehensive business report export
        $this->notify('success', 'Business report export initiated. You will receive an email when ready.');
    }
    
    protected function scheduleReports(): void
    {
        // Implementation for scheduling automated reports
        $this->notify('success', 'Report scheduling configured.');
    }
    
    protected function configureAlerts(): void
    {
        // Implementation for business alerts configuration
        $this->notify('info', 'Business alert settings updated.');
    }
}
