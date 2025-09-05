<?php

namespace App\Filament\Widgets\Financial;

use App\Models\Expense;
use App\Models\StaffCommission;
use App\Models\PurchaseOrder;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;

class ExpenseAnalyticsWidget extends ChartWidget
{
    protected static ?string $heading = 'Expense Breakdown & Budget Analysis';
    
    protected static ?int $sort = 5;
    
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        $dateRange = $this->getDateRange();
        [$startDate, $endDate] = $dateRange;

        // Get expense categories and amounts
        $expenseData = $this->getExpenseCategoryData($tenant?->id, $startDate, $endDate);

        $labels = [];
        $actualAmounts = [];
        $budgetAmounts = [];
        $colors = [
            'rgba(239, 68, 68, 0.8)',   // Red for Staff Costs
            'rgba(59, 130, 246, 0.8)',  // Blue for Supplies
            'rgba(245, 158, 11, 0.8)',  // Amber for Utilities
            'rgba(34, 197, 94, 0.8)',   // Green for Rent
            'rgba(139, 92, 246, 0.8)',  // Purple for Marketing
            'rgba(236, 72, 153, 0.8)',  // Pink for Equipment
            'rgba(16, 185, 129, 0.8)',  // Emerald for Maintenance
            'rgba(168, 85, 247, 0.8)',  // Violet for Insurance
            'rgba(251, 191, 36, 0.8)',  // Yellow for Professional Services
            'rgba(107, 114, 128, 0.8)', // Gray for Others
        ];

        foreach ($expenseData as $index => $expense) {
            $labels[] = $expense['category'];
            $actualAmounts[] = (float) $expense['actual'];
            $budgetAmounts[] = (float) $expense['budget'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Actual Expenses',
                    'data' => $actualAmounts,
                    'backgroundColor' => array_slice($colors, 0, count($actualAmounts)),
                    'borderColor' => array_map(fn($color) => str_replace('0.8', '1', $color), array_slice($colors, 0, count($actualAmounts))),
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Budget Allocation',
                    'data' => $budgetAmounts,
                    'backgroundColor' => array_map(fn($color) => str_replace('0.8', '0.3', $color), array_slice($colors, 0, count($budgetAmounts))),
                    'borderColor' => array_map(fn($color) => str_replace('0.8', '0.6', $color), array_slice($colors, 0, count($actualAmounts))),
                    'borderWidth' => 1,
                    'borderDash' => [5, 5],
                ],
            ],
            'labels' => $labels,
        ];
    }

    private function getExpenseCategoryData(?int $branchId, $startDate, $endDate): array
    {
        // Define expense categories with their budget allocations (monthly estimates)
        $categories = [
            'Staff Costs' => ['budget' => 800000, 'includes' => ['salaries', 'commissions', 'benefits']],
            'Supplies & Products' => ['budget' => 300000, 'includes' => ['inventory', 'consumables', 'equipment_supplies']],
            'Utilities' => ['budget' => 150000, 'includes' => ['electricity', 'water', 'internet', 'phone']],
            'Rent & Facilities' => ['budget' => 200000, 'includes' => ['rent', 'facility_maintenance', 'cleaning']],
            'Marketing' => ['budget' => 100000, 'includes' => ['advertising', 'promotions', 'social_media']],
            'Equipment' => ['budget' => 75000, 'includes' => ['equipment_purchase', 'equipment_maintenance']],
            'Maintenance' => ['budget' => 50000, 'includes' => ['facility_repairs', 'equipment_repairs']],
            'Insurance' => ['budget' => 25000, 'includes' => ['liability_insurance', 'property_insurance']],
            'Professional Services' => ['budget' => 40000, 'includes' => ['accounting', 'legal', 'consulting']],
            'Other Expenses' => ['budget' => 30000, 'includes' => ['miscellaneous', 'office_supplies']],
        ];

        $results = [];

        foreach ($categories as $categoryName => $config) {
            // Calculate actual expenses for this category
            $actualAmount = 0;

            if ($categoryName === 'Staff Costs') {
                // Include staff commissions
                $commissions = StaffCommission::query()
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->whereBetween('earned_date', [$startDate, $endDate])
                    ->where('approval_status', 'approved')
                    ->sum('commission_amount');

                // Include salary-related expenses
                $salaryExpenses = Expense::query()
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->whereBetween('expense_date', [$startDate, $endDate])
                    ->whereIn('category', ['salaries', 'benefits', 'payroll_taxes'])
                    ->where('status', 'approved')
                    ->sum('amount');

                $actualAmount = $commissions + $salaryExpenses;

            } elseif ($categoryName === 'Supplies & Products') {
                // Include product purchases and inventory costs
                $inventoryExpenses = Expense::query()
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->whereBetween('expense_date', [$startDate, $endDate])
                    ->whereIn('category', ['supplies', 'inventory', 'consumables'])
                    ->where('status', 'approved')
                    ->sum('amount');

                // Include purchase orders
                $purchaseOrderCosts = PurchaseOrder::query()
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', 'completed')
                    ->sum('total_amount') ?? 0;

                $actualAmount = $inventoryExpenses + $purchaseOrderCosts;

            } else {
                // Map category names to expense categories in database
                $dbCategories = $this->mapCategoryToDatabase($categoryName);
                
                $actualAmount = Expense::query()
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->whereBetween('expense_date', [$startDate, $endDate])
                    ->whereIn('category', $dbCategories)
                    ->where('status', 'approved')
                    ->sum('amount');
            }

            // Calculate budget for the period (monthly budgets)
            $daysInPeriod = \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1;
            $periodBudget = ($config['budget'] / 30) * $daysInPeriod; // Daily rate * days

            $variance = $actualAmount - $periodBudget;
            $variancePercentage = $periodBudget > 0 ? ($variance / $periodBudget) * 100 : 0;

            if ($actualAmount > 0 || $periodBudget > 0) {
                $results[] = [
                    'category' => $categoryName,
                    'actual' => $actualAmount,
                    'budget' => $periodBudget,
                    'variance' => $variance,
                    'variance_percentage' => $variancePercentage,
                    'status' => $variance <= 0 ? 'under_budget' : ($variance <= $periodBudget * 0.1 ? 'on_budget' : 'over_budget'),
                ];
            }
        }

        // Sort by actual amount descending
        usort($results, fn($a, $b) => $b['actual'] <=> $a['actual']);

        return $results;
    }

    private function mapCategoryToDatabase(string $categoryName): array
    {
        return match ($categoryName) {
            'Utilities' => ['utilities', 'electricity', 'water', 'internet', 'phone'],
            'Rent & Facilities' => ['rent', 'facility_maintenance', 'cleaning', 'security'],
            'Marketing' => ['marketing', 'advertising', 'promotions', 'social_media'],
            'Equipment' => ['equipment', 'equipment_purchase', 'equipment_lease'],
            'Maintenance' => ['maintenance', 'repairs', 'equipment_maintenance'],
            'Insurance' => ['insurance', 'liability_insurance', 'property_insurance'],
            'Professional Services' => ['professional_services', 'accounting', 'legal', 'consulting'],
            'Other Expenses' => ['miscellaneous', 'office_supplies', 'administrative', 'other'],
            default => [$categoryName],
        };
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
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
                        }',
                        'afterLabel' => 'function(context) {
                            if (context.datasetIndex === 0) {
                                var budget = context.chart.data.datasets[1].data[context.dataIndex];
                                var actual = context.parsed.y;
                                var variance = actual - budget;
                                var status = variance <= 0 ? "Under Budget" : (variance <= budget * 0.1 ? "On Budget" : "Over Budget");
                                return "Variance: KES " + variance.toLocaleString() + " (" + status + ")";
                            }
                            return "";
                        }'
                    ]
                ],
            ],
            'scales' => [
                'x' => [
                    'ticks' => [
                        'maxRotation' => 45,
                        'minRotation' => 0,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Amount (KES)'
                    ],
                    'ticks' => [
                        'callback' => 'function(value) {
                            return "KES " + value.toLocaleString();
                        }'
                    ]
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }

    private function getDateRange(): array
    {
        return [now()->startOfMonth(), now()->endOfMonth()];
    }
}