<?php

namespace App\Filament\Resources\ServicePackageResource\Pages;

use App\Filament\Resources\ServicePackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListServicePackages extends ListRecords
{
    protected static string $resource = ServicePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Packages'),
            
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active'))
                ->badge(fn () => $this->getModel()::where('status', 'active')->count()),
            
            'popular' => Tab::make('Popular')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('popular', true))
                ->badge(fn () => $this->getModel()::where('popular', true)->count())
                ->badgeColor('success'),
            
            'featured' => Tab::make('Featured')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('featured', true))
                ->badge(fn () => $this->getModel()::where('featured', true)->count())
                ->badgeColor('info'),
            
            'couples' => Tab::make('Couples')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_couple_package', true))
                ->badge(fn () => $this->getModel()::where('is_couple_package', true)->count())
                ->badgeColor('warning'),
            
            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
                ->badge(fn () => $this->getModel()::where('status', 'draft')->count())
                ->badgeColor('gray'),
            
            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'inactive'))
                ->badge(fn () => $this->getModel()::where('status', 'inactive')->count())
                ->badgeColor('danger'),
        ];
    }
}