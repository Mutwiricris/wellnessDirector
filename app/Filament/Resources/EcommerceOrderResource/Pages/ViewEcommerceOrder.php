<?php

namespace App\Filament\Resources\EcommerceOrderResource\Pages;

use App\Filament\Resources\EcommerceOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewEcommerceOrder extends ViewRecord
{
    protected static string $resource = EcommerceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('markPaid')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->action(fn () => $this->record->update([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]))
                ->visible(fn () => $this->record->payment_status !== 'paid'),
            
            Actions\Action::make('fulfill')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->fulfillOrder();
                    $this->record->update(['status' => 'completed']);
                })
                ->visible(fn () => 
                    $this->record->payment_status === 'paid' && 
                    !in_array($this->record->status, ['completed', 'cancelled', 'refunded'])
                ),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Order Summary')
                    ->schema([
                        Infolists\Components\TextEntry::make('order_number')
                            ->size('lg')
                            ->weight('bold')
                            ->copyable(),
                        
                        Infolists\Components\TextEntry::make('branch.name')
                            ->label('Branch'),
                        
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'confirmed' => 'info',
                                'processing' => 'info',
                                'shipped' => 'primary',
                                'delivered' => 'success',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                'refunded' => 'gray',
                            }),
                        
                        Infolists\Components\TextEntry::make('payment_status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'paid' => 'success',
                                'partial' => 'info',
                                'failed' => 'danger',
                                'refunded' => 'gray',
                            }),
                        
                        Infolists\Components\TextEntry::make('total_amount')
                            ->money('KES')
                            ->size('lg')
                            ->weight('bold'),
                        
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Customer Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('customer.name')
                            ->label('Customer Name'),
                        
                        Infolists\Components\TextEntry::make('customer_email')
                            ->copyable(),
                        
                        Infolists\Components\TextEntry::make('customer_phone')
                            ->copyable(),
                        
                        Infolists\Components\KeyValueEntry::make('billing_address')
                            ->label('Billing Address'),
                        
                        Infolists\Components\KeyValueEntry::make('shipping_address')
                            ->label('Shipping Address'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Order Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('display_name')
                                    ->label('Product'),
                                
                                Infolists\Components\TextEntry::make('sku'),
                                
                                Infolists\Components\TextEntry::make('quantity')
                                    ->numeric(),
                                
                                Infolists\Components\TextEntry::make('unit_price')
                                    ->money('KES'),
                                
                                Infolists\Components\TextEntry::make('total_price')
                                    ->money('KES')
                                    ->weight('bold'),
                                
                                Infolists\Components\TextEntry::make('special_instructions')
                                    ->placeholder('None')
                                    ->columnSpanFull(),
                            ])
                            ->columns(5),
                    ]),

                Infolists\Components\Section::make('Financial Breakdown')
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal')
                            ->money('KES'),
                        
                        Infolists\Components\TextEntry::make('tax_amount')
                            ->money('KES')
                            ->visible(fn ($record) => $record->tax_amount > 0),
                        
                        Infolists\Components\TextEntry::make('shipping_amount')
                            ->money('KES')
                            ->visible(fn ($record) => $record->shipping_amount > 0),
                        
                        Infolists\Components\TextEntry::make('discount_amount')
                            ->money('KES')
                            ->visible(fn ($record) => $record->discount_amount > 0),
                        
                        Infolists\Components\TextEntry::make('total_amount')
                            ->money('KES')
                            ->size('lg')
                            ->weight('bold'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Delivery Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('delivery_method')
                            ->badge(),
                        
                        Infolists\Components\TextEntry::make('requested_delivery_date')
                            ->dateTime()
                            ->visible(fn ($record) => $record->requested_delivery_date),
                        
                        Infolists\Components\KeyValueEntry::make('delivery_time_slot')
                            ->visible(fn ($record) => $record->delivery_time_slot),
                        
                        Infolists\Components\TextEntry::make('tracking_number')
                            ->copyable()
                            ->visible(fn ($record) => $record->tracking_number),
                        
                        Infolists\Components\TextEntry::make('special_instructions')
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->special_instructions),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Payment Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('payment_method')
                            ->visible(fn ($record) => $record->payment_method),
                        
                        Infolists\Components\TextEntry::make('payment_reference')
                            ->copyable()
                            ->visible(fn ($record) => $record->payment_reference),
                        
                        Infolists\Components\TextEntry::make('paid_at')
                            ->dateTime()
                            ->visible(fn ($record) => $record->paid_at),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record->payment_method || $record->payment_reference || $record->paid_at),

                Infolists\Components\Section::make('Additional Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('source')
                            ->badge(),
                        
                        Infolists\Components\TextEntry::make('notes')
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->notes),
                        
                        Infolists\Components\KeyValueEntry::make('meta_data')
                            ->visible(fn ($record) => $record->meta_data && count($record->meta_data) > 0),
                    ])
                    ->collapsed(),
            ]);
    }
}