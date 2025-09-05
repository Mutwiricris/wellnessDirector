<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\PosTransaction;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;
use Carbon\Carbon;

class OwnerTrendAnalysisWidget extends ChartWidget
{
    protected static ?string $heading = 'Business Trends & Growth';
    
    protected static ?string $pollingInterval = '600s';
    
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 'full',
        'lg' => 'full',
        'xl' => 'full',
        '2xl' => 'full',
    ];

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return ['datasets' => [], 'labels' => []];

        $trendData = $this->getTrendData($tenant->id);

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (KES)',
                    'data' => $trendData['revenue'],
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Bookings Count',
                    'data' => $trendData['bookings'],
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
                ],
                [
                    'label' => 'New Customers',
                    'data' => $trendData['customers'],
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $trendData['labels'],
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
                        'font' => [
                            'size' => 12,
                        ],
                    ],
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (KES)',
                    ],
                    'ticks' => [
                        'callback' => 'function(value) { return "KES " + value.toLocaleString(); }',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Count',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Month',
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

    private function getTrendData(int $branchId): array
    {
        $data = [
            'revenue' => [],
            'bookings' => [],
            'customers' => [],
            'labels' => [],
        ];

        // Get data for the last 12 months
        for ($i = 11; $i >= 0; $i--) {
            $startDate = Carbon::now()->subMonths($i)->startOfMonth();
            $endDate = Carbon::now()->subMonths($i)->endOfMonth();
            
            $data['labels'][] = $startDate->format('M Y');

            // Revenue
            $monthlyRevenue = $this->getMonthlyRevenue($branchId, $startDate, $endDate);
            $data['revenue'][] = $monthlyRevenue;

            // Bookings
            $monthlyBookings = Booking::where('branch_id', $branchId)
                ->whereBetween('appointment_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->count();
            $data['bookings'][] = $monthlyBookings;

            // New Customers
            $newCustomers = User::where('user_type', 'user')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereHas('bookings', function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId);
                })
                ->count();
            $data['customers'][] = $newCustomers;
        }

        return $data;
    }

    private function getMonthlyRevenue(int $branchId, Carbon $start, Carbon $end): float
    {
        $bookingRevenue = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        $posRevenue = PosTransaction::where('branch_id', $branchId)
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        return $bookingRevenue + $posRevenue;
    }

    public function getDescription(): ?string
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return null;

        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $currentRevenue = $this->getMonthlyRevenue($tenant->id, $currentMonth, Carbon::now());
        $lastRevenue = $this->getMonthlyRevenue($tenant->id, $lastMonth, $lastMonthEnd);

        $growth = $lastRevenue > 0 ? (($currentRevenue - $lastRevenue) / $lastRevenue) * 100 : 0;
        $growthText = $growth >= 0 ? "↗️ +" . number_format($growth, 1) . "%" : "↘️ " . number_format($growth, 1) . "%";

        return "Month-over-month growth: {$growthText} | 12-month business performance trends";
    }
}
