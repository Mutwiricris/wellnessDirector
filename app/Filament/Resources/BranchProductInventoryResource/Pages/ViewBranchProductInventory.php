<?php

namespace App\Filament\Resources\BranchProductInventoryResource\Pages;

use App\Filament\Resources\BranchProductInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewBranchProductInventory extends ViewRecord
{
    protected static string $resource = BranchProductInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Product & Branch Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('branch.name')
                            ->label('Branch')
                            ->size('lg')
                            ->weight('bold'),
                        
                        Infolists\Components\TextEntry::make('product.name')
                            ->label('Product')
                            ->size('lg')
                            ->weight('bold'),
                        
                        Infolists\Components\TextEntry::make('productVariant.title')
                            ->label('Variant')
                            ->placeholder('Main Product'),
                        
                        Infolists\Components\TextEntry::make('product.sku')
                            ->label('SKU')
                            ->copyable(),
                        
                        Infolists\Components\IconEntry::make('is_available')
                            ->boolean()
                            ->label('Available for Sale'),
                        
                        Infolists\Components\TextEntry::make('last_restocked_at')
                            ->date()
                            ->placeholder('Never restocked'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Inventory Levels')
                    ->schema([
                        Infolists\Components\TextEntry::make('quantity_on_hand')
                            ->label('Quantity on Hand')
                            ->size('lg')
                            ->weight('bold')
                            ->color(fn ($state, $record) => $state <= $record->reorder_level ? 'danger' : 'success'),
                        
                        Infolists\Components\TextEntry::make('quantity_reserved')
                            ->label('Reserved Quantity'),
                        
                        Infolists\Components\TextEntry::make('quantity_available')
                            ->label('Available Quantity')
                            ->getStateUsing(fn ($record) => max(0, $record->quantity_on_hand - $record->quantity_reserved))
                            ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                        
                        Infolists\Components\TextEntry::make('reorder_level')
                            ->label('Reorder Level'),
                        
                        Infolists\Components\TextEntry::make('max_stock_level')
                            ->label('Maximum Stock Level')
                            ->placeholder('No limit set'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Pricing Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('product.base_price')
                            ->label('Base Price')
                            ->money('KES'),
                        
                        Infolists\Components\TextEntry::make('branch_price')
                            ->label('Branch-Specific Price')
                            ->money('KES')
                            ->placeholder('Using base price'),
                        
                        Infolists\Components\TextEntry::make('effective_price')
                            ->label('Effective Price')
                            ->getStateUsing(fn ($record) => $record->branch_price ?? $record->product->base_price)
                            ->money('KES')
                            ->weight('bold'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Stock Movement History')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('stockMovements')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('movement_date')
                                    ->dateTime()
                                    ->label('Date'),
                                
                                Infolists\Components\TextEntry::make('movement_type_display')
                                    ->label('Type')
                                    ->badge(),
                                
                                Infolists\Components\TextEntry::make('quantity')
                                    ->label('Quantity')
                                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                                
                                Infolists\Components\TextEntry::make('quantity_before')
                                    ->label('Before'),
                                
                                Infolists\Components\TextEntry::make('quantity_after')
                                    ->label('After'),
                                
                                Infolists\Components\TextEntry::make('staff.name')
                                    ->label('Staff')
                                    ->placeholder('System'),
                                
                                Infolists\Components\TextEntry::make('notes')
                                    ->columnSpanFull()
                                    ->placeholder('No notes'),
                            ])
                            ->columns(6)
                            ->getStateUsing(fn ($record) => $record->stockMovements()->latest()->limit(10)->get()),
                    ])
                    ->collapsed()
                    ->visible(fn ($record) => $record->stockMovements()->exists()),

                Infolists\Components\Section::make('Stock Status Indicators')
                    ->schema([
                        Infolists\Components\TextEntry::make('stock_status')
                            ->label('Stock Status')
                            ->getStateUsing(function ($record) {
                                if ($record->quantity_on_hand <= 0) {
                                    return 'Out of Stock';
                                } elseif ($record->quantity_on_hand <= $record->reorder_level) {
                                    return 'Low Stock';
                                } else {
                                    return 'In Stock';
                                }
                            })
                            ->badge()
                            ->color(function ($state) {
                                return match($state) {
                                    'Out of Stock' => 'danger',
                                    'Low Stock' => 'warning',
                                    'In Stock' => 'success',
                                };
                            }),
                        
                        Infolists\Components\TextEntry::make('days_of_stock')
                            ->label('Estimated Days of Stock')
                            ->getStateUsing(function ($record) {
                                // This is a simple calculation - could be enhanced with actual sales data
                                $avgDailyUsage = 2; // Placeholder - would calculate from historical data
                                if ($avgDailyUsage > 0) {
                                    return ceil($record->quantity_available / $avgDailyUsage) . ' days';
                                }
                                return 'Unknown';
                            }),
                        
                        Infolists\Components\TextEntry::make('inventory_value')
                            ->label('Inventory Value')
                            ->getStateUsing(fn ($record) => $record->quantity_on_hand * $record->product->cost_price)
                            ->money('KES'),
                    ])
                    ->columns(3),
            ]);
    }
}