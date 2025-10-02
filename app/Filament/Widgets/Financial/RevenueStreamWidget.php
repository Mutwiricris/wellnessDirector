<?php

namespace App\Filament\Widgets\Financial;

use App\Models\Booking;
use App\Models\PosTransaction;
use App\Models\PackageSale;
use App\Models\GiftVoucher;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class RevenueStreamWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    protected static ?string $heading = 'Revenue Stream Analysis';
    
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '15s';
    
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth();
        $endDate = $this->filters['endDate'] ?? now()->endOfMonth();

        // Service Revenue (Bookings)
        $serviceRevenue = Booking::query()
            ->when($tenant, fn($q) => $q->where('branch_id', $tenant->id))
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        // Product Revenue (POS)
        $productRevenue = PosTransaction::query()
            ->when($tenant, fn($q) => $q->where('branch_id', $tenant->id))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        // Package Revenue
        $packageRevenue = PackageSale::query()
            ->when($tenant, fn($q) => $q->where('branch_id', $tenant->id))
            ->whereBetween('purchased_at', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->sum('final_price');

        // Gift Voucher Revenue
        $voucherRevenue = GiftVoucher::query()
            ->when($tenant, fn($q) => $q->where('branch_id', $tenant->id))
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->where('status', 'active')
            ->sum('original_amount');

        $totalRevenue = $serviceRevenue + $productRevenue + $packageRevenue + $voucherRevenue;

        // Calculate percentages
        $servicePercentage = $totalRevenue > 0 ? ($serviceRevenue / $totalRevenue) * 100 : 0;
        $productPercentage = $totalRevenue > 0 ? ($productRevenue / $totalRevenue) * 100 : 0;
        $packagePercentage = $totalRevenue > 0 ? ($packageRevenue / $totalRevenue) * 100 : 0;
        $voucherPercentage = $totalRevenue > 0 ? ($voucherRevenue / $totalRevenue) * 100 : 0;

        return [
            'datasets' => [
                [
                    'label' => 'Revenue Stream Distribution',
                    'data' => [
                        (float) $serviceRevenue,
                        (float) $productRevenue,
                        (float) $packageRevenue,
                        (float) $voucherRevenue,
                    ],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',   // Blue for Services
                        'rgba(16, 185, 129, 0.8)',   // Green for Products
                        'rgba(245, 158, 11, 0.8)',   // Amber for Packages
                        'rgba(139, 92, 246, 0.8)',   // Purple for Vouchers
                    ],
                    'borderColor' => [
                        'rgba(59, 130, 246, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(139, 92, 246, 1)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => [
                'Services (KES ' . number_format($serviceRevenue, 0) . ' - ' . number_format($servicePercentage, 1) . '%)',
                'Products (KES ' . number_format($productRevenue, 0) . ' - ' . number_format($productPercentage, 1) . '%)',
                'Packages (KES ' . number_format($packageRevenue, 0) . ' - ' . number_format($packagePercentage, 1) . '%)',
                'Vouchers (KES ' . number_format($voucherRevenue, 0) . ' - ' . number_format($voucherPercentage, 1) . '%)',
            ],
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
                            var percentage = ((value / total) * 100).toFixed(1);
                            return label + ": KES " + value.toLocaleString() + " (" + percentage + "%)";
                        }'
                    ]
                ]
            ],
            'maintainAspectRatio' => false,
            'cutout' => '50%',
        ];
    }

}