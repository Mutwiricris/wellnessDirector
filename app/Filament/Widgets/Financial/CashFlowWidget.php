<?php

namespace App\Filament\Widgets\Financial;

use App\Models\Payment;
use App\Models\PosTransaction;
use App\Models\Expense;
use App\Models\StaffCommission;
use App\Models\Booking;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CashFlowWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    
    protected static ?string $heading = 'Cash Flow Analysis - Inflows vs Outflows';
    
    protected static ?int $sort = 3;
    
    protected static ?string $pollingInterval = '15s';
    
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth();
        $endDate = $this->filters['endDate'] ?? now()->endOfMonth();

        // Generate daily cash flow data for the period
        $days = [];
        $inflows = [];
        $outflows = [];
        $netFlow = [];
        $cumulativeFlow = 0;
        $cumulativeData = [];

        $period = \Carbon\Carbon::parse($startDate);
        $endPeriod = \Carbon\Carbon::parse($endDate);

        while ($period <= $endPeriod) {
            $dayStart = $period->copy()->startOfDay();
            $dayEnd = $period->copy()->endOfDay();

            // Calculate daily inflows
            $dailyInflow = $this->calculateDailyInflows($tenant?->id, $dayStart, $dayEnd);
            
            // Calculate daily outflows
            $dailyOutflow = $this->calculateDailyOutflows($tenant?->id, $dayStart, $dayEnd);
            
            $dailyNet = $dailyInflow - $dailyOutflow;
            $cumulativeFlow += $dailyNet;

            $days[] = $period->format('M j');
            $inflows[] = (float) $dailyInflow;
            $outflows[] = (float) $dailyOutflow;
            $netFlow[] = (float) $dailyNet;
            $cumulativeData[] = (float) $cumulativeFlow;

            $period->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Daily Inflows (KES)',
                    'data' => $inflows,
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Daily Outflows (KES)',
                    'data' => $outflows,
                    'borderColor' => 'rgba(239, 68, 68, 1)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Net Cash Flow (KES)',
                    'data' => $netFlow,
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                    'fill' => true,
                ],
                [
                    'label' => 'Cumulative Cash Flow (KES)',
                    'data' => $cumulativeData,
                    'borderColor' => 'rgba(139, 92, 246, 1)',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
                    'borderDash' => [5, 5],
                ],
            ],
            'labels' => $days,
        ];
    }

    private function calculateDailyInflows(?int $branchId, $dayStart, $dayEnd): float
    {
        // Service payments received
        $serviceInflows = Payment::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('booking', fn($subQ) => $subQ->where('branch_id', $branchId));
            })
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->where('status', 'completed')
            ->sum('amount');

        // POS transaction inflows
        $posInflows = PosTransaction::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->where('payment_status', 'completed')
            ->sum('total_amount');

        // Package sales
        $packageInflows = \App\Models\PackageSale::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('purchased_at', [$dayStart, $dayEnd])
            ->where('payment_status', 'completed')
            ->sum('final_price') ?? 0;

        // Gift voucher sales
        $voucherInflows = \App\Models\GiftVoucher::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('purchase_date', [$dayStart, $dayEnd])
            ->where('status', 'active')
            ->sum('original_amount') ?? 0;

        return (float) ($serviceInflows + $posInflows + $packageInflows + $voucherInflows);
    }

    private function calculateDailyOutflows(?int $branchId, $dayStart, $dayEnd): float
    {
        // Operational expenses
        $expenses = Expense::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$dayStart, $dayEnd])
            ->where('status', 'approved')
            ->sum('amount');

        // Staff commissions paid
        $commissions = StaffCommission::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('payment_date', [$dayStart, $dayEnd])
            ->where('payment_status', 'paid')
            ->sum('commission_amount');

        // Refunds issued
        $refunds = Payment::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('booking', fn($subQ) => $subQ->where('branch_id', $branchId));
            })
            ->whereBetween('refunded_at', [$dayStart, $dayEnd])
            ->where('status', 'refunded')
            ->sum('refund_amount');

        // Processing fees (estimated daily)
        $processingFees = $this->calculateDailyProcessingFees($branchId, $dayStart, $dayEnd);

        return (float) ($expenses + $commissions + $refunds + $processingFees);
    }

    private function calculateDailyProcessingFees(?int $branchId, $dayStart, $dayEnd): float
    {
        // Card processing fees (3.5%)
        $cardPayments = Payment::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('booking', fn($subQ) => $subQ->where('branch_id', $branchId));
            })
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->where('payment_method', 'credit_debit_card')
            ->where('status', 'completed')
            ->sum('amount');

        $cardFees = $cardPayments * 0.035;

        // Bank transfer fees (KES 25 per transaction)
        $bankTransferCount = Payment::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('booking', fn($subQ) => $subQ->where('branch_id', $branchId));
            })
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->where('payment_method', 'bank_transfer')
            ->where('status', 'completed')
            ->count();

        $bankFees = $bankTransferCount * 25;

        // Refund processing fees (KES 10 per refund)
        $refundCount = Payment::query()
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('booking', fn($subQ) => $subQ->where('branch_id', $branchId));
            })
            ->whereBetween('refunded_at', [$dayStart, $dayEnd])
            ->where('status', 'refunded')
            ->count();

        $refundFees = $refundCount * 10;

        return (float) ($cardFees + $bankFees + $refundFees);
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
                        'text' => 'Daily Cash Flow (KES)'
                    ],
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.1)',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Cumulative Flow (KES)'
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
                            return label + ": KES " + value.toLocaleString();
                        }'
                    ]
                ],
            ],
            'elements' => [
                'point' => [
                    'radius' => 4,
                    'hoverRadius' => 6,
                ],
            ],
        ];
    }

}