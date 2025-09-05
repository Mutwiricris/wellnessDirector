<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Booking;
use App\Models\Service;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;

class ServiceAnalyticsWidget extends ChartWidget
{
    protected static ?string $heading = 'Service Performance & Popularity Analysis';
    
    protected static ?int $sort = 4;
    
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        
        if (!$tenant) {
            return ['datasets' => [], 'labels' => []];
        }

        // Get service performance data
        $serviceData = $this->getServicePerformanceData($tenant->id);

        return [
            'datasets' => [
                [
                    'label' => 'Bookings Count',
                    'data' => array_column($serviceData, 'bookings'),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 2,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Revenue (KES)',
                    'data' => array_column($serviceData, 'revenue'),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'borderWidth' => 2,
                    'type' => 'line',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => array_column($serviceData, 'name'),
        ];
    }

    private function getServicePerformanceData(int $branchId): array
    {
        $currentMonth = now();
        $startDate = $currentMonth->copy()->startOfMonth();
        $endDate = $currentMonth->copy()->endOfMonth();

        // Get services available at this branch
        $services = Service::whereHas('branches', function($q) use ($branchId) {
            $q->where('branches.id', $branchId);
        })->get();

        $serviceData = [];

        foreach ($services as $service) {
            // Count bookings for this service
            $bookingsCount = Booking::where('branch_id', $branchId)
                ->where('service_id', $service->id)
                ->whereBetween('appointment_date', [$startDate, $endDate])
                ->count();

            // Calculate revenue for this service
            $revenue = Booking::where('branch_id', $branchId)
                ->where('service_id', $service->id)
                ->whereBetween('appointment_date', [$startDate, $endDate])
                ->where('payment_status', 'completed')
                ->sum('total_amount');

            // Calculate service popularity (bookings per day)
            $daysInPeriod = $startDate->diffInDays($endDate) + 1;
            $popularityScore = $daysInPeriod > 0 ? $bookingsCount / $daysInPeriod : 0;

            // Calculate completion rate
            $totalBookings = Booking::where('branch_id', $branchId)
                ->where('service_id', $service->id)
                ->whereBetween('appointment_date', [$startDate, $endDate])
                ->count();

            $completedBookings = Booking::where('branch_id', $branchId)
                ->where('service_id', $service->id)
                ->whereBetween('appointment_date', [$startDate, $endDate])
                ->where('bookings.status', 'completed')
                ->count();

            $completionRate = $totalBookings > 0 ? ($completedBookings / $totalBookings) * 100 : 0;

            if ($bookingsCount > 0 || $revenue > 0) {
                $serviceData[] = [
                    'name' => $service->name,
                    'bookings' => $bookingsCount,
                    'revenue' => (float) $revenue,
                    'popularity_score' => round($popularityScore, 2),
                    'completion_rate' => round($completionRate, 1),
                    'avg_price' => $bookingsCount > 0 ? round($revenue / $bookingsCount, 0) : 0,
                ];
            }
        }

        // Sort by revenue descending
        usort($serviceData, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

        // Limit to top 10 services for chart readability
        return array_slice($serviceData, 0, 10);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 20,
                    ],
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                    'callbacks' => [
                        'label' => 'function(context) {
                            var label = context.dataset.label || "";
                            var value = context.parsed.y;
                            if (label === "Revenue (KES)") {
                                return label + ": KES " + value.toLocaleString();
                            }
                            return label + ": " + value;
                        }'
                    ]
                ]
            ],
            'scales' => [
                'x' => [
                    'ticks' => [
                        'maxRotation' => 45,
                        'minRotation' => 0,
                    ],
                ],
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Number of Bookings'
                    ],
                    'beginAtZero' => true,
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (KES)'
                    ],
                    'beginAtZero' => true,
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                    'ticks' => [
                        'callback' => 'function(value) {
                            return "KES " + value.toLocaleString();
                        }'
                    ]
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}