<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;

class BranchFinancialWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue & Payment Analytics (Last 30 Days)';
    
    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        
        if (!$tenant) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Get last 30 days data
        $revenueData = [];
        $bookingData = [];
        $labels = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M j');

            // Revenue for this day
            $dailyRevenue = Booking::where('branch_id', $tenant->id)
                ->whereDate('appointment_date', $date)
                ->where('payment_status', 'completed')
                ->sum('total_amount');
            $revenueData[] = (float) $dailyRevenue;

            // Bookings for this day
            $dailyBookings = Booking::where('branch_id', $tenant->id)
                ->whereDate('appointment_date', $date)
                ->count();
            $bookingData[] = $dailyBookings;
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
                    'label' => 'Bookings',
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
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'title' => [
                    'display' => false,
                ],
            ],
        ];
    }
}