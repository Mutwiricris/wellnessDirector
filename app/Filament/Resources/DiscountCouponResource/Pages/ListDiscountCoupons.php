<?php

namespace App\Filament\Resources\DiscountCouponResource\Pages;

use App\Filament\Resources\DiscountCouponResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDiscountCoupons extends ListRecords
{
    protected static string $resource = DiscountCouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Coupons'),
            
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active'))
                ->badge(fn () => $this->getModel()::where('status', 'active')->count()),
            
            'expiring_soon' => Tab::make('Expiring Soon')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('expires_at', '<=', now()->addDays(7))
                                                               ->where('status', 'active'))
                ->badge(fn () => $this->getModel()::where('expires_at', '<=', now()->addDays(7))
                                                  ->where('status', 'active')
                                                  ->count())
                ->badgeColor('warning'),
            
            'expired' => Tab::make('Expired')
                ->modifyQueryUsing(fn (Builder $query) => $query->expired())
                ->badge(fn () => $this->getModel()::expired()->count())
                ->badgeColor('danger'),
            
            'high_usage' => Tab::make('High Usage')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('usage_limit')
                                                               ->whereRaw('(used_count / usage_limit) >= 0.8'))
                ->badge(fn () => $this->getModel()::whereNotNull('usage_limit')
                                                  ->whereRaw('(used_count / usage_limit) >= 0.8')
                                                  ->count())
                ->badgeColor('info'),
            
            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'inactive'))
                ->badge(fn () => $this->getModel()::where('status', 'inactive')->count())
                ->badgeColor('gray'),
        ];
    }
}