<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Booking;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;

class RevenueAnalyticsWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue Analytics (Last 6 Months)';
    
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        
        if (!$tenant) {
            return ['datasets' => [], 'labels' => []];
        }

        $months = [];
        $revenueData = [];
        $bookingData = [];
        $labels = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M Y');

            $monthlyRevenue = Booking::where('branch_id', $tenant->id)
                ->whereMonth('appointment_date', $date->month)
                ->whereYear('appointment_date', $date->year)
                ->where('payment_status', 'completed')
                ->sum('total_amount');

            $monthlyBookings = Booking::where('branch_id', $tenant->id)
                ->whereMonth('appointment_date', $date->month)
                ->whereYear('appointment_date', $date->year)
                ->count();

            $revenueData[] = (float) $monthlyRevenue;
            $bookingData[] = $monthlyBookings;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (KES)',
                    'data' => $revenueData,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Bookings Count',
                    'data' => $bookingData,
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (KES)'
                    ]
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Number of Bookings'
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
        ];
    }
}