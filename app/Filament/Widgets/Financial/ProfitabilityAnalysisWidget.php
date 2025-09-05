<?php

namespace App\Filament\Widgets\Financial;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Expense;
use App\Models\PosTransaction;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;
use Carbon\Carbon;

class ProfitabilityAnalysisWidget extends ChartWidget
{
    protected static ?string $heading = 'Service Profitability Analysis';
    protected static ?string $pollingInterval = '60s';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        $profitabilityData = $this->getServiceProfitability($tenant?->id);

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => array_column($profitabilityData, 'revenue'),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.7)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                ],
                [
                    'label' => 'Costs',
                    'data' => array_column($profitabilityData, 'costs'),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.7)',
                    'borderColor' => 'rgba(239, 68, 68, 1)',
                ],
                [
                    'label' => 'Net Profit',
                    'data' => array_column($profitabilityData, 'profit'),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.7)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                ],
            ],
            'labels' => array_column($profitabilityData, 'service_name'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'title' => [
                    'display' => true,
                    'text' => 'Service Profitability - Current Month',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Amount (KES)',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Services',
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }

    private function getServiceProfitability(?int $branchId): array
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        $services = Service::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('bookings', function($subQ) use ($branchId) {
                    $subQ->where('branch_id', $branchId);
                });
            })
            ->withSum(['bookings as total_revenue' => function($q) use ($branchId, $startDate, $endDate) {
                $q->when($branchId, fn($query) => $query->where('branch_id', $branchId))
                  ->whereBetween('appointment_date', [$startDate, $endDate])
                  ->where('payment_status', 'completed');
            }], 'total_amount')
            ->withCount(['bookings as booking_count' => function($q) use ($branchId, $startDate, $endDate) {
                $q->when($branchId, fn($query) => $query->where('branch_id', $branchId))
                  ->whereBetween('appointment_date', [$startDate, $endDate])
                  ->where('status', 'completed');
            }])
            ->having('total_revenue', '>', 0)
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        $profitabilityData = [];

        foreach ($services as $service) {
            // Calculate estimated costs (staff wages + supplies)
            $avgStaffCost = $this->getAverageStaffCostPerService($service->id, $branchId, $startDate, $endDate);
            $supplyCost = $service->price * 0.15; // Estimate 15% of price for supplies
            $totalCosts = ($avgStaffCost + $supplyCost) * $service->booking_count;
            
            $revenue = $service->total_revenue ?? 0;
            $profit = $revenue - $totalCosts;
            
            $profitabilityData[] = [
                'service_name' => substr($service->name, 0, 15) . (strlen($service->name) > 15 ? '...' : ''),
                'revenue' => $revenue,
                'costs' => $totalCosts,
                'profit' => $profit,
                'margin' => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
            ];
        }

        return $profitabilityData;
    }

    private function getAverageStaffCostPerService(int $serviceId, ?int $branchId, $startDate, $endDate): float
    {
        // Get average staff hourly rate for this service
        $avgHourlyRate = Staff::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereHas('bookings', function($q) use ($serviceId, $startDate, $endDate) {
                $q->where('service_id', $serviceId)
                  ->whereBetween('appointment_date', [$startDate, $endDate]);
            })
            ->avg('hourly_rate');

        // Estimate service duration (default 1 hour if not specified)
        $service = Service::find($serviceId);
        $durationHours = $service ? ($service->duration / 60) : 1; // Convert minutes to hours

        return ($avgHourlyRate ?? 25) * $durationHours; // Default KES 25/hour if no rate set
    }
}
