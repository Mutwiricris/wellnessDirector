<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Booking;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;

class ClientAnalyticsWidget extends ChartWidget
{
    protected static ?string $heading = 'Client Behavior & Retention Analysis';
    
    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        
        if (!$tenant) {
            return ['datasets' => [], 'labels' => []];
        }

        // Get client segmentation data
        $clientData = $this->getClientSegmentationData($tenant->id);

        return [
            'datasets' => [
                [
                    'label' => 'Client Distribution',
                    'data' => array_values($clientData),
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.8)',   // Green for New Clients
                        'rgba(59, 130, 246, 0.8)',  // Blue for Regular Clients
                        'rgba(245, 158, 11, 0.8)',  // Amber for VIP Clients
                        'rgba(239, 68, 68, 0.8)',   // Red for Inactive Clients
                    ],
                    'borderColor' => [
                        'rgba(34, 197, 94, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(239, 68, 68, 1)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => array_keys($clientData),
        ];
    }

    private function getClientSegmentationData(int $branchId): array
    {
        $currentDate = now();
        $threeMonthsAgo = $currentDate->copy()->subMonths(3);
        $sixMonthsAgo = $currentDate->copy()->subMonths(6);

        // New Clients (first booking within last 3 months)
        $newClients = User::where('user_type', 'client')
            ->whereHas('bookings', function($q) use ($branchId, $threeMonthsAgo, $currentDate) {
                $q->where('branch_id', $branchId)
                  ->whereBetween('appointment_date', [$threeMonthsAgo, $currentDate]);
            })
            ->whereDoesntHave('bookings', function($q) use ($branchId, $threeMonthsAgo) {
                $q->where('branch_id', $branchId)
                  ->where('appointment_date', '<', $threeMonthsAgo);
            })
            ->count();

        // Regular Clients (2+ bookings in last 6 months)
        $regularClients = User::where('user_type', 'client')
            ->whereHas('bookings', function($q) use ($branchId, $sixMonthsAgo, $currentDate) {
                $q->where('branch_id', $branchId)
                  ->whereBetween('appointment_date', [$sixMonthsAgo, $currentDate]);
            }, '>=', 2)
            ->whereHas('bookings', function($q) use ($branchId, $threeMonthsAgo) {
                $q->where('branch_id', $branchId)
                  ->where('appointment_date', '<', $threeMonthsAgo);
            })
            ->count();

        // VIP Clients (5+ bookings in last 6 months OR high spending)
        $vipClients = User::where('user_type', 'client')
            ->where(function($query) use ($branchId, $sixMonthsAgo, $currentDate) {
                // High frequency clients
                $query->whereHas('bookings', function($q) use ($branchId, $sixMonthsAgo, $currentDate) {
                    $q->where('branch_id', $branchId)
                      ->whereBetween('appointment_date', [$sixMonthsAgo, $currentDate]);
                }, '>=', 5);
            })
            ->orWhere(function($query) use ($branchId, $sixMonthsAgo, $currentDate) {
                // High spending clients (>50k in 6 months)
                $query->whereRaw('(
                    SELECT SUM(total_amount) 
                    FROM bookings 
                    WHERE bookings.client_id = users.id 
                    AND bookings.branch_id = ? 
                    AND bookings.appointment_date BETWEEN ? AND ? 
                    AND bookings.payment_status = "completed"
                ) > 50000', [$branchId, $sixMonthsAgo, $currentDate]);
            })
            ->count();

        // Inactive Clients (had bookings before but none in last 3 months)
        $inactiveClients = User::where('user_type', 'client')
            ->whereHas('bookings', function($q) use ($branchId, $threeMonthsAgo) {
                $q->where('branch_id', $branchId)
                  ->where('appointment_date', '<', $threeMonthsAgo);
            })
            ->whereDoesntHave('bookings', function($q) use ($branchId, $threeMonthsAgo, $currentDate) {
                $q->where('branch_id', $branchId)
                  ->whereBetween('appointment_date', [$threeMonthsAgo, $currentDate]);
            })
            ->count();

        return [
            'New Clients (' . $newClients . ')' => $newClients,
            'Regular Clients (' . $regularClients . ')' => $regularClients,
            'VIP Clients (' . $vipClients . ')' => $vipClients,
            'Inactive Clients (' . $inactiveClients . ')' => $inactiveClients,
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
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 20,
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            var label = context.label || "";
                            var value = context.parsed;
                            var total = context.dataset.data.reduce((a, b) => a + b, 0);
                            var percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return label + ": " + value + " clients (" + percentage + "%)";
                        }'
                    ]
                ]
            ],
            'maintainAspectRatio' => false,
            'cutout' => '50%',
        ];
    }
}