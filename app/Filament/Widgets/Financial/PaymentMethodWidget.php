<?php

namespace App\Filament\Widgets\Financial;

use App\Models\Payment;
use App\Models\PosTransaction;
use App\Models\PosPaymentSplit;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;

class PaymentMethodWidget extends ChartWidget
{
    protected static ?string $heading = 'Payment Method Distribution & Processing Costs';
    
    protected static ?int $sort = 4;
    
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        $dateRange = $this->getDateRange();
        [$startDate, $endDate] = $dateRange;

        // Get payment method distribution and processing costs
        $paymentData = $this->getPaymentMethodData($tenant?->id, $startDate, $endDate);

        $labels = [];
        $amounts = [];
        $processingFees = [];
        $colors = [
            'rgba(34, 197, 94, 0.8)',   // Green for M-Pesa
            'rgba(59, 130, 246, 0.8)',  // Blue for Cash
            'rgba(239, 68, 68, 0.8)',   // Red for Cards
            'rgba(245, 158, 11, 0.8)',  // Amber for Bank Transfer
            'rgba(139, 92, 246, 0.8)',  // Purple for Gift Vouchers
            'rgba(236, 72, 153, 0.8)',  // Pink for Loyalty Points
            'rgba(107, 114, 128, 0.8)', // Gray for Others
        ];

        foreach ($paymentData as $index => $payment) {
            $labels[] = $payment['method'] . ' (KES ' . number_format($payment['amount'], 0) . ')';
            $amounts[] = (float) $payment['amount'];
            $processingFees[] = (float) $payment['processing_fee'];
        }

        $totalAmount = array_sum($amounts);
        $totalFees = array_sum($processingFees);

        return [
            'datasets' => [
                [
                    'label' => 'Payment Amount',
                    'data' => $amounts,
                    'backgroundColor' => array_slice($colors, 0, count($amounts)),
                    'borderColor' => array_map(fn($color) => str_replace('0.8', '1', $color), array_slice($colors, 0, count($amounts))),
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
            'totalAmount' => $totalAmount,
            'totalFees' => $totalFees,
            'feePercentage' => $totalAmount > 0 ? ($totalFees / $totalAmount) * 100 : 0,
        ];
    }

    private function getPaymentMethodData(?int $branchId, $startDate, $endDate): array
    {
        $paymentMethods = [
            'M-Pesa' => ['fee_rate' => 0, 'flat_fee' => 0, 'max_amount' => 70000],
            'Cash' => ['fee_rate' => 0, 'flat_fee' => 0, 'max_amount' => null],
            'Credit/Debit Card' => ['fee_rate' => 3.5, 'flat_fee' => 0, 'max_amount' => null],
            'Bank Transfer' => ['fee_rate' => 0, 'flat_fee' => 25, 'max_amount' => null],
            'Gift Voucher' => ['fee_rate' => 0, 'flat_fee' => 0, 'max_amount' => null],
            'Loyalty Points' => ['fee_rate' => 0, 'flat_fee' => 0, 'max_amount' => null],
        ];

        $results = [];

        foreach ($paymentMethods as $method => $config) {
            // Get booking payments
            $bookingAmount = Payment::query()
                ->when($branchId, function($q) use ($branchId) {
                    $q->whereHas('booking', fn($subQ) => $subQ->where('branch_id', $branchId));
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('payment_method', strtolower(str_replace(['/', ' '], ['_', '_'], $method)))
                ->where('status', 'completed')
                ->sum('amount');

            // Get POS payments (primary method)
            $posAmount = PosTransaction::query()
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('payment_method', strtolower(str_replace(['/', ' '], ['_', '_'], $method)))
                ->where('payment_status', 'completed')
                ->sum('total_amount');

            // Get POS split payments
            $posSplitAmount = PosPaymentSplit::query()
                ->whereHas('transaction', function($q) use ($branchId, $startDate, $endDate) {
                    $q->when($branchId, fn($subQ) => $subQ->where('branch_id', $branchId))
                      ->whereBetween('created_at', [$startDate, $endDate])
                      ->where('payment_status', 'completed');
                })
                ->where('payment_method', strtolower(str_replace(['/', ' '], ['_', '_'], $method)))
                ->sum('amount');

            $totalAmount = $bookingAmount + $posAmount + $posSplitAmount;

            if ($totalAmount > 0) {
                // Calculate processing fees
                $processingFee = 0;
                if ($config['fee_rate'] > 0) {
                    $processingFee = ($totalAmount * $config['fee_rate']) / 100;
                }
                if ($config['flat_fee'] > 0) {
                    // Count number of transactions for flat fee calculation
                    $transactionCount = Payment::query()
                        ->when($branchId, function($q) use ($branchId) {
                            $q->whereHas('booking', fn($subQ) => $subQ->where('branch_id', $branchId));
                        })
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->where('payment_method', strtolower(str_replace(['/', ' '], ['_', '_'], $method)))
                        ->where('status', 'completed')
                        ->count();

                    $transactionCount += PosTransaction::query()
                        ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->where('payment_method', strtolower(str_replace(['/', ' '], ['_', '_'], $method)))
                        ->where('payment_status', 'completed')
                        ->count();

                    $processingFee += $transactionCount * $config['flat_fee'];
                }

                $results[] = [
                    'method' => $method,
                    'amount' => $totalAmount,
                    'processing_fee' => $processingFee,
                    'net_amount' => $totalAmount - $processingFee,
                    'fee_percentage' => $totalAmount > 0 ? ($processingFee / $totalAmount) * 100 : 0,
                ];
            }
        }

        // Sort by amount descending
        usort($results, fn($a, $b) => $b['amount'] <=> $a['amount']);

        return $results;
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
                        'padding' => 15,
                        'font' => [
                            'size' => 12,
                        ],
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            var label = context.label || "";
                            var value = context.parsed;
                            var total = context.dataset.data.reduce((a, b) => a + b, 0);
                            var percentage = ((value / total) * 100).toFixed(1);
                            return label + " (" + percentage + "%)";
                        }'
                    ]
                ],
                'title' => [
                    'display' => true,
                    'text' => 'Processing fees and distribution analysis',
                    'font' => [
                        'size' => 14,
                        'weight' => 'normal',
                    ],
                    'padding' => 20,
                ],
            ],
            'maintainAspectRatio' => false,
            'cutout' => '40%',
        ];
    }

    private function getDateRange(): array
    {
        return [now()->startOfMonth(), now()->endOfMonth()];
    }
}