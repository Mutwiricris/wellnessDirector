<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

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
                Infolists\Components\Section::make('Product Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->size('lg')
                            ->weight('bold'),
                        
                        Infolists\Components\TextEntry::make('sku')
                            ->label('SKU')
                            ->copyable(),
                        
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'inactive' => 'warning',
                                'archived' => 'danger',
                            }),
                        
                        Infolists\Components\TextEntry::make('type')
                            ->badge(),
                        
                        Infolists\Components\IconEntry::make('is_featured')
                            ->boolean()
                            ->label('Featured'),
                        
                        Infolists\Components\TextEntry::make('published_at')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Categories')
                    ->schema([
                        Infolists\Components\TextEntry::make('categories.name')
                            ->badge()
                            ->separator(', '),
                    ])
                    ->visible(fn ($record) => $record->categories->isNotEmpty()),

                Infolists\Components\Section::make('Description')
                    ->schema([
                        Infolists\Components\TextEntry::make('short_description'),
                        Infolists\Components\TextEntry::make('description')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->short_description || $record->description),

                Infolists\Components\Section::make('Pricing')
                    ->schema([
                        Infolists\Components\TextEntry::make('base_price')
                            ->money('KES')
                            ->size('lg')
                            ->weight('bold'),
                        
                        Infolists\Components\TextEntry::make('cost_price')
                            ->money('KES'),
                        
                        Infolists\Components\TextEntry::make('compare_at_price')
                            ->money('KES')
                            ->visible(fn ($record) => $record->compare_at_price),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Inventory & Shipping')
                    ->schema([
                        Infolists\Components\IconEntry::make('track_inventory')
                            ->boolean()
                            ->label('Track Inventory'),
                        
                        Infolists\Components\IconEntry::make('requires_shipping')
                            ->boolean()
                            ->label('Requires Shipping'),
                        
                        Infolists\Components\TextEntry::make('weight')
                            ->suffix(' kg')
                            ->visible(fn ($record) => $record->weight),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Images')
                    ->schema([
                        Infolists\Components\ImageEntry::make('images')
                            ->hiddenLabel()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->images && count($record->images) > 0),

                Infolists\Components\Section::make('Branch Inventory')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('branchInventory')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('branch.name')
                                    ->label('Branch'),
                                
                                Infolists\Components\TextEntry::make('quantity_on_hand')
                                    ->label('On Hand'),
                                
                                Infolists\Components\TextEntry::make('quantity_reserved')
                                    ->label('Reserved'),
                                
                                Infolists\Components\TextEntry::make('quantity_available')
                                    ->label('Available')
                                    ->color('success'),
                                
                                Infolists\Components\TextEntry::make('reorder_level')
                                    ->label('Reorder Level'),
                                
                                Infolists\Components\IconEntry::make('is_available')
                                    ->boolean()
                                    ->label('Available for Sale'),
                            ])
                            ->columns(6),
                    ])
                    ->visible(fn ($record) => $record->branchInventory->isNotEmpty()),

                Infolists\Components\Section::make('Additional Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('tags')
                            ->badge()
                            ->separator(', ')
                            ->visible(fn ($record) => $record->tags && count($record->tags) > 0),
                        
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                        
                        Infolists\Components\TextEntry::make('updated_at')
                            ->dateTime(),
                    ])
                    ->columns(3)
                    ->collapsed(),
            ]);
    }
}