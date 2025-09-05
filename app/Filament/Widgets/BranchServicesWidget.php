<?php

namespace App\Filament\Widgets;

use App\Models\Service;
use App\Models\Booking;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;

class BranchServicesWidget extends ChartWidget
{
    protected static ?string $heading = 'Service Popularity (This Month)';
    
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        
        if (!$tenant) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Get services booked at this branch this month
        $services = Service::whereHas('bookings', function($query) use ($tenant) {
            $query->where('branch_id', $tenant->id)
                ->whereMonth('appointment_date', now()->month)
                ->whereYear('appointment_date', now()->year);
        })->get();

        $labels = [];
        $bookingCounts = [];
        $revenueData = [];
        $colors = [
            'rgba(255, 99, 132, 0.8)',
            'rgba(54, 162, 235, 0.8)', 
            'rgba(255, 205, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)',
        ];

        foreach ($services as $index => $service) {
            $bookingCount = Booking::where('branch_id', $tenant->id)
                ->where('service_id', $service->id)
                ->whereMonth('appointment_date', now()->month)
                ->whereYear('appointment_date', now()->year)
                ->count();

            $revenue = Booking::where('branch_id', $tenant->id)
                ->where('service_id', $service->id)
                ->whereMonth('appointment_date', now()->month)
                ->whereYear('appointment_date', now()->year)
                ->where('payment_status', 'completed')
                ->sum('total_amount');

            if ($bookingCount > 0) {
                $labels[] = $service->name;
                $bookingCounts[] = $bookingCount;
                $revenueData[] = (float) $revenue;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Bookings',
                    'data' => $bookingCounts,
                    'backgroundColor' => array_slice($colors, 0, count($bookingCounts)),
                    'borderColor' => array_map(fn($color) => str_replace('0.8', '1', $color), array_slice($colors, 0, count($bookingCounts))),
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'afterLabel' => 'function(context) { return "Revenue: KES " + context.parsed.toLocaleString(); }'
                    ]
                ]
            ],
        ];
    }
}