<?php

namespace App\Filament\Resources\EcommerceOrderResource\Pages;

use App\Filament\Resources\EcommerceOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEcommerceOrders extends ListRecords
{
    protected static string $resource = EcommerceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Orders'),
            
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn () => $this->getModel()::where('status', 'pending')->count())
                ->badgeColor('warning'),
            
            'confirmed' => Tab::make('Confirmed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'confirmed'))
                ->badge(fn () => $this->getModel()::where('status', 'confirmed')->count())
                ->badgeColor('info'),
            
            'processing' => Tab::make('Processing')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'processing'))
                ->badge(fn () => $this->getModel()::where('status', 'processing')->count())
                ->badgeColor('info'),
            
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed'))
                ->badge(fn () => $this->getModel()::where('status', 'completed')->count())
                ->badgeColor('success'),
            
            'unpaid' => Tab::make('Unpaid')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_status', 'pending'))
                ->badge(fn () => $this->getModel()::where('payment_status', 'pending')->count())
                ->badgeColor('danger'),
        ];
    }
}