<?php

namespace App\Filament\Resources\BranchProductInventoryResource\Pages;

use App\Filament\Resources\BranchProductInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBranchProductInventories extends ListRecords
{
    protected static string $resource = BranchProductInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Inventory'),
            
            'low_stock' => Tab::make('Low Stock')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereRaw('quantity_on_hand <= reorder_level'))
                ->badge(fn () => $this->getModel()::whereRaw('quantity_on_hand <= reorder_level')->count())
                ->badgeColor('danger'),
            
            'out_of_stock' => Tab::make('Out of Stock')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quantity_on_hand', '<=', 0))
                ->badge(fn () => $this->getModel()::where('quantity_on_hand', '<=', 0)->count())
                ->badgeColor('danger'),
            
            'available' => Tab::make('Available')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_available', true)->where('quantity_on_hand', '>', 0))
                ->badge(fn () => $this->getModel()::where('is_available', true)->where('quantity_on_hand', '>', 0)->count())
                ->badgeColor('success'),
            
            'reserved' => Tab::make('With Reserved Stock')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quantity_reserved', '>', 0))
                ->badge(fn () => $this->getModel()::where('quantity_reserved', '>', 0)->count())
                ->badgeColor('warning'),
        ];
    }
}