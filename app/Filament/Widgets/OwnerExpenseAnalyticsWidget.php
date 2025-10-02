<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Carbon\Carbon;

class OwnerExpenseAnalyticsWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    
    protected static ?string $pollingInterval = '15s';
    protected static ?string $heading = 'Expense Analytics by Category';
    
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

        $expenseData = $this->getExpenseByCategory($tenant->id, $thisMonth, $now);

        return [
            'datasets' => [
                [
                    'label' => 'Expenses by Category',
                    'data' => array_values($expenseData['amounts']),
                    'backgroundColor' => [
                        'rgba(239, 68, 68, 0.8)',   // Red
                        'rgba(245, 158, 11, 0.8)',  // Amber
                        'rgba(34, 197, 94, 0.8)',   // Green
                        'rgba(59, 130, 246, 0.8)',  // Blue
                        'rgba(168, 85, 247, 0.8)',  // Purple
                        'rgba(236, 72, 153, 0.8)',  // Pink
                        'rgba(14, 165, 233, 0.8)',  // Sky
                        'rgba(99, 102, 241, 0.8)',  // Indigo
                    ],
                    'borderColor' => [
                        'rgba(239, 68, 68, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(34, 197, 94, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(168, 85, 247, 1)',
                        'rgba(236, 72, 153, 1)',
                        'rgba(14, 165, 233, 1)',
                        'rgba(99, 102, 241, 1)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => array_keys($expenseData['amounts']),
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
                            return context.label + ": KES " + context.parsed.toLocaleString();
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

    private function getExpenseByCategory(int $branchId, Carbon $start, Carbon $end): array
    {
        $expenses = Expense::where('branch_id', $branchId)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'approved')
            ->with('category')
            ->get();

        $categoryTotals = [];
        
        foreach ($expenses as $expense) {
            $categoryName = $expense->category ? $expense->category->name : 'Uncategorized';
            $categoryTotals[$categoryName] = ($categoryTotals[$categoryName] ?? 0) + $expense->amount;
        }

        // Sort by amount descending
        arsort($categoryTotals);

        return [
            'amounts' => $categoryTotals,
            'total' => array_sum($categoryTotals),
        ];
    }

    public function getDescription(): ?string
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return null;

        $thisMonth = Carbon::now()->startOfMonth();
        $now = Carbon::now();
        
        $expenseData = $this->getExpenseByCategory($tenant->id, $thisMonth, $now);
        
        return "Total Expenses: KES " . number_format($expenseData['total'], 2) . " | Categories: " . count($expenseData['amounts']);
    }
}
