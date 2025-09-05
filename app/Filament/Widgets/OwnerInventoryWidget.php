<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\BranchProductInventory;
use App\Models\PosTransaction;
use App\Models\PosTransactionItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use Carbon\Carbon;

class OwnerInventoryWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '300s';
    
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 2,
        'lg' => 1,
        'xl' => 1,
        '2xl' => 1,
    ];

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return [];

        $inventoryMetrics = $this->getInventoryMetrics($tenant->id);
        $salesMetrics = $this->getSalesMetrics($tenant->id);

        return [
            Stat::make('Total Products', number_format($inventoryMetrics['total_products']))
                ->description($inventoryMetrics['active_products'] . ' active products')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Low Stock Items', $inventoryMetrics['low_stock_count'])
                ->description('Items below minimum threshold')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($inventoryMetrics['low_stock_count'] > 0 ? 'danger' : 'success'),

            Stat::make('Out of Stock', $inventoryMetrics['out_of_stock_count'])
                ->description('Items requiring restock')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($inventoryMetrics['out_of_stock_count'] > 0 ? 'danger' : 'success'),

            Stat::make('Inventory Value', 'KES ' . number_format($inventoryMetrics['total_value'], 2))
                ->description('Total stock value at cost')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Top Selling Product', $salesMetrics['top_product']['name'])
                ->description($salesMetrics['top_product']['quantity'] . ' units sold this month')
                ->descriptionIcon('heroicon-m-star')
                ->color('success'),

            Stat::make('Inventory Turnover', number_format($inventoryMetrics['turnover_ratio'], 1) . 'x')
                ->description('Monthly inventory turnover rate')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($inventoryMetrics['turnover_ratio'] >= 2 ? 'success' : 'warning'),
        ];
    }

    private function getInventoryMetrics(int $branchId): array
    {
        // Get products through branch inventory relationship
        $branchInventory = BranchProductInventory::where('branch_id', $branchId)->with('product')->get();
        $products = $branchInventory->pluck('product')->unique('id');
        
        $totalProducts = $products->count();
        $activeProducts = $products->where('status', 'active')->count();
        $lowStockCount = 0;
        $outOfStockCount = 0;
        $totalValue = 0;

        foreach ($branchInventory as $inventory) {
            $currentStock = $inventory->quantity_on_hand ?? 0;
            $minStock = $inventory->reorder_level ?? 5;
            $costPrice = $inventory->product->cost_price ?? 0;

            $totalValue += $currentStock * $costPrice;

            if ($currentStock <= 0) {
                $outOfStockCount++;
            } elseif ($currentStock <= $minStock) {
                $lowStockCount++;
            }
        }

        // Calculate turnover ratio (simplified)
        $thisMonth = Carbon::now()->startOfMonth();
        $now = Carbon::now();
        
        $monthlySales = PosTransactionItem::whereHas('posTransaction', function ($query) use ($branchId, $thisMonth, $now) {
            $query->where('branch_id', $branchId)
                  ->whereBetween('created_at', [$thisMonth, $now])
                  ->where('payment_status', 'completed');
        })->sum('quantity');

        $avgInventory = $branchInventory->avg('quantity_on_hand') ?? 1;
        $turnoverRatio = $avgInventory > 0 ? $monthlySales / $avgInventory : 0;

        return [
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'total_value' => $totalValue,
            'turnover_ratio' => $turnoverRatio,
        ];
    }

    private function getSalesMetrics(int $branchId): array
    {
        $thisMonth = Carbon::now()->startOfMonth();
        $now = Carbon::now();

        // Get top selling item by name (since we don't have product_id)
        $topItem = PosTransactionItem::select('item_name')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->whereHas('posTransaction', function ($query) use ($branchId, $thisMonth, $now) {
                $query->where('branch_id', $branchId)
                      ->whereBetween('created_at', [$thisMonth, $now])
                      ->where('payment_status', 'completed');
            })
            ->where('item_type', 'product')
            ->groupBy('item_name')
            ->orderByDesc('total_quantity')
            ->first();

        if ($topItem) {
            $topProductData = [
                'name' => $topItem->item_name,
                'quantity' => $topItem->total_quantity,
            ];
        } else {
            $topProductData = [
                'name' => 'No sales yet',
                'quantity' => 0,
            ];
        }

        return [
            'top_product' => $topProductData,
        ];
    }
}
