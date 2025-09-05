<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\User;
use App\Models\Service;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;
use Carbon\Carbon;

class OwnerBookingAnalyticsWidget extends ChartWidget
{
    protected static ?string $heading = 'Booking Analytics & Trends';
    
    protected static ?string $pollingInterval = '60s';
    
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 2,
        'lg' => 2,
        'xl' => 2,
        '2xl' => 2,
    ];

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return ['datasets' => [], 'labels' => []];

        $thisMonth = Carbon::now()->startOfMonth();
        $now = Carbon::now();

        // Get booking data for the last 30 days
        $bookingData = $this->getBookingTrends($tenant->id);
        $conversionData = $this->getConversionRates($tenant->id);

        return [
            'datasets' => [
                [
                    'label' => 'Total Bookings',
                    'data' => $bookingData['total'],
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Completed Bookings',
                    'data' => $bookingData['completed'],
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Cancelled Bookings',
                    'data' => $bookingData['cancelled'],
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.4,
                ],
            ],
            'labels' => $bookingData['labels'],
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
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 20,
                    ],
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Number of Bookings',
                    ],
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Date',
                    ],
                    'ticks' => [
                        'maxRotation' => 45,
                        'minRotation' => 0,
                    ],
                ],
            ],
            'interaction' => [
                'mode' => 'nearest',
                'axis' => 'x',
                'intersect' => false,
            ],
        ];
    }

    private function getBookingTrends(int $branchId): array
    {
        $data = [
            'total' => [],
            'completed' => [],
            'cancelled' => [],
            'labels' => [],
        ];

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $data['labels'][] = $date->format('M j');

            $totalBookings = Booking::where('branch_id', $branchId)
                ->whereDate('appointment_date', $date)
                ->count();

            $completedBookings = Booking::where('branch_id', $branchId)
                ->whereDate('appointment_date', $date)
                ->where('status', 'completed')
                ->count();

            $cancelledBookings = Booking::where('branch_id', $branchId)
                ->whereDate('appointment_date', $date)
                ->where('status', 'cancelled')
                ->count();

            $data['total'][] = $totalBookings;
            $data['completed'][] = $completedBookings;
            $data['cancelled'][] = $cancelledBookings;
        }

        return $data;
    }

    private function getConversionRates(int $branchId): array
    {
        $thisMonth = Carbon::now()->startOfMonth();
        $now = Carbon::now();

        $totalBookings = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$thisMonth->toDateString(), $now->toDateString()])
            ->count();

        $completedBookings = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$thisMonth->toDateString(), $now->toDateString()])
            ->where('status', 'completed')
            ->count();

        $conversionRate = $totalBookings > 0 ? ($completedBookings / $totalBookings) * 100 : 0;

        return [
            'total' => $totalBookings,
            'completed' => $completedBookings,
            'conversion_rate' => round($conversionRate, 2),
        ];
    }

    public function getDescription(): ?string
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return null;

        $conversionData = $this->getConversionRates($tenant->id);
        
        return "Conversion Rate: {$conversionData['conversion_rate']}% | Total Bookings: {$conversionData['total']} | Completed: {$conversionData['completed']}";
    }
}
