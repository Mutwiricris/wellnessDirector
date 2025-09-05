<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewProductCategory extends ViewRecord
{
    protected static string $resource = ProductCategoryResource::class;

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
                Infolists\Components\Section::make('Category Information')
                    ->schema([
                        Infolists\Components\ImageEntry::make('image')
                            ->hiddenLabel()
                            ->height(150)
                            ->width(150),
                        
                        Infolists\Components\Group::make([
                            Infolists\Components\TextEntry::make('name')
                                ->size('lg')
                                ->weight('bold'),
                            
                            Infolists\Components\TextEntry::make('slug')
                                ->copyable(),
                            
                            Infolists\Components\TextEntry::make('parent.name')
                                ->label('Parent Category')
                                ->placeholder('Top Level Category'),
                            
                            Infolists\Components\TextEntry::make('status')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'active' => 'success',
                                    'inactive' => 'warning',
                                }),
                        ])->columnSpan(2),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Description')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                    ])
                    ->visible(fn ($record) => $record->description),

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('products_count')
                            ->label('Total Products')
                            ->getStateUsing(fn ($record) => $record->products()->count()),
                        
                        Infolists\Components\TextEntry::make('children_count')
                            ->label('Subcategories')
                            ->getStateUsing(fn ($record) => $record->children()->count()),
                        
                        Infolists\Components\TextEntry::make('sort_order'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Subcategories')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('children')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->url(fn ($record) => ProductCategoryResource::getUrl('view', ['record' => $record])),
                                
                                Infolists\Components\TextEntry::make('products_count')
                                    ->label('Products')
                                    ->getStateUsing(fn ($record) => $record->products()->count()),
                                
                                Infolists\Components\TextEntry::make('status')
                                    ->badge(),
                            ])
                            ->columns(3),
                    ])
                    ->visible(fn ($record) => $record->children->isNotEmpty()),

                Infolists\Components\Section::make('Products in this Category')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('products')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('name'),
                                Infolists\Components\TextEntry::make('sku'),
                                Infolists\Components\TextEntry::make('base_price')
                                    ->money('KES'),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge(),
                            ])
                            ->columns(4),
                    ])
                    ->visible(fn ($record) => $record->products->isNotEmpty()),

                Infolists\Components\Section::make('Additional Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                        
                        Infolists\Components\TextEntry::make('updated_at')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }
}