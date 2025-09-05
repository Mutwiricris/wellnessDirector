<?php

namespace App\Filament\Resources\GiftVoucherResource\Pages;

use App\Filament\Resources\GiftVoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListGiftVouchers extends ListRecords
{
    protected static string $resource = GiftVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Vouchers'),
            
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active'))
                ->badge(fn () => $this->getModel()::where('status', 'active')->count()),
            
            'expiring_soon' => Tab::make('Expiring Soon')
                ->modifyQueryUsing(fn (Builder $query) => $query->expiringSoon())
                ->badge(fn () => $this->getModel()::expiringSoon()->count())
                ->badgeColor('warning'),
            
            'expired' => Tab::make('Expired')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'expired'))
                ->badge(fn () => $this->getModel()::where('status', 'expired')->count())
                ->badgeColor('danger'),
            
            'redeemed' => Tab::make('Redeemed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'redeemed'))
                ->badge(fn () => $this->getModel()::where('status', 'redeemed')->count()),
        ];
    }
}