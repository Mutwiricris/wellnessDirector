<?php

namespace App\Filament\Widgets\Financial;

use App\Models\Payment;
use App\Models\Booking;
use App\Models\PosTransaction;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;
use Carbon\Carbon;

class PaymentTrendsWidget extends ChartWidget
{
    protected static ?string $heading = 'Payment Method Trends & Performance';
    protected static ?string $pollingInterval = '60s';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        $paymentData = $this->getPaymentMethodAnalysis($tenant?->id);

        return [
            'datasets' => [
                [
                    'label' => 'Transaction Volume',
                    'data' => array_column($paymentData, 'volume'),
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.8)',   // Cash - Green
                        'rgba(59, 130, 246, 0.8)',  // M-Pesa - Blue
                        'rgba(168, 85, 247, 0.8)',  // Card - Purple
                        'rgba(245, 158, 11, 0.8)',  // Bank Transfer - Amber
                        'rgba(239, 68, 68, 0.8)',   // Other - Red
                    ],
                    'borderColor' => [
                        'rgba(34, 197, 94, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(168, 85, 247, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(239, 68, 68, 1)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => array_column($paymentData, 'method'),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'title' => [
                    'display' => true,
                    'text' => 'Payment Method Distribution - Current Month',
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '50%',
        ];
    }

    private function getPaymentMethodAnalysis(?int $branchId): array
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        // Get payment method data from bookings
        $bookingPayments = Booking::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('payment_method')
            ->get();

        // Get payment method data from POS transactions
        $posPayments = PosTransaction::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'completed')
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('payment_method')
            ->get();

        // Combine and normalize payment methods
        $paymentMethods = [];
        
        foreach ($bookingPayments as $payment) {
            $method = $this->normalizePaymentMethod($payment->payment_method);
            if (!isset($paymentMethods[$method])) {
                $paymentMethods[$method] = ['count' => 0, 'total' => 0];
            }
            $paymentMethods[$method]['count'] += $payment->count;
            $paymentMethods[$method]['total'] += $payment->total;
        }

        foreach ($posPayments as $payment) {
            $method = $this->normalizePaymentMethod($payment->payment_method);
            if (!isset($paymentMethods[$method])) {
                $paymentMethods[$method] = ['count' => 0, 'total' => 0];
            }
            $paymentMethods[$method]['count'] += $payment->count;
            $paymentMethods[$method]['total'] += $payment->total;
        }

        // Format for chart
        $chartData = [];
        foreach ($paymentMethods as $method => $data) {
            $chartData[] = [
                'method' => $method,
                'volume' => $data['total'],
                'count' => $data['count'],
            ];
        }

        // Sort by volume descending
        usort($chartData, function($a, $b) {
            return $b['volume'] <=> $a['volume'];
        });

        return $chartData;
    }

    private function normalizePaymentMethod(?string $method): string
    {
        if (!$method) return 'Unknown';
        
        $method = strtolower($method);
        
        if (str_contains($method, 'mpesa') || str_contains($method, 'm-pesa')) {
            return 'M-Pesa';
        }
        
        if (str_contains($method, 'cash')) {
            return 'Cash';
        }
        
        if (str_contains($method, 'card') || str_contains($method, 'visa') || str_contains($method, 'mastercard')) {
            return 'Card Payment';
        }
        
        if (str_contains($method, 'bank') || str_contains($method, 'transfer')) {
            return 'Bank Transfer';
        }
        
        return 'Other';
    }
}
