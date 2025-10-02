<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\PosTransaction;
use App\Models\PackageSale;
use App\Models\GiftVoucher;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Carbon\Carbon;

class OwnerRevenueStreamsWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    
    protected static ?string $pollingInterval = '15s';
    protected static ?string $heading = 'Revenue Streams Analysis';
    
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 2,
        'lg' => 1,
        'xl' => 1,
        '2xl' => 1,
    ];

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return ['datasets' => [], 'labels' => []];

        $thisMonth = Carbon::now()->startOfMonth();
        $now = Carbon::now();

        $revenueStreams = $this->getRevenueStreams($tenant->id, $thisMonth, $now);

        return [
            'datasets' => [
                [
                    'label' => 'Revenue by Stream',
                    'data' => array_values($revenueStreams['amounts']),
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.8)',   // Services - Green
                        'rgba(59, 130, 246, 0.8)',  // Products - Blue
                        'rgba(245, 158, 11, 0.8)',  // Packages - Amber
                        'rgba(168, 85, 247, 0.8)',  // Gift Vouchers - Purple
                        'rgba(236, 72, 153, 0.8)',  // Other - Pink
                    ],
                    'borderColor' => [
                        'rgba(34, 197, 94, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(168, 85, 247, 1)',
                        'rgba(236, 72, 153, 1)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => array_keys($revenueStreams['amounts']),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 15,
                        'font' => [
                            'size' => 12,
                        ],
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ": KES " + context.parsed.toLocaleString() + " (" + percentage + "%)";
                        }'
                    ],
                ],
            ],
            'layout' => [
                'padding' => [
                    'top' => 20,
                    'bottom' => 20,
                ],
            ],
        ];
    }

    private function getRevenueStreams(int $branchId, Carbon $start, Carbon $end): array
    {
        // Service Revenue (Bookings)
        $serviceRevenue = Booking::where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        // Product Revenue (POS Sales)
        $productRevenue = PosTransaction::where('branch_id', $branchId)
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'completed')
            ->where('transaction_type', 'sale')
            ->sum('total_amount');

        // Package Revenue
        $packageRevenue = PackageSale::where('branch_id', $branchId)
            ->whereBetween('purchased_at', [$start, $end])
            ->where('payment_status', 'completed')
            ->sum('final_price');

        // Gift Voucher Revenue
        $voucherRevenue = GiftVoucher::where('branch_id', $branchId)
            ->whereBetween('purchase_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'active')
            ->sum('original_amount');

        $revenueStreams = [
            'Services' => $serviceRevenue,
            'Products' => $productRevenue,
            'Packages' => $packageRevenue,
            'Gift Vouchers' => $voucherRevenue,
        ];

        // Remove zero values
        $revenueStreams = array_filter($revenueStreams, function($value) {
            return $value > 0;
        });

        // Sort by amount descending
        arsort($revenueStreams);

        return [
            'amounts' => $revenueStreams,
            'total' => array_sum($revenueStreams),
        ];
    }

    public function getDescription(): ?string
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return null;

        $thisMonth = Carbon::now()->startOfMonth();
        $now = Carbon::now();
        
        $revenueStreams = $this->getRevenueStreams($tenant->id, $thisMonth, $now);
        
        return "Total Revenue: KES " . number_format($revenueStreams['total'], 2) . " | Active Streams: " . count($revenueStreams['amounts']);
    }
}
