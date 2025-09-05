<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EcommerceOrderResource\Pages;
use App\Models\EcommerceOrder;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Facades\Filament;

class EcommerceOrderResource extends Resource
{
    protected static ?string $model = EcommerceOrder::class;


    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Business Operations';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Orders';

    public static function getEloquentQuery(): Builder
    {
        // For now, return all orders since we removed tenancy
        // Later you can add branch filtering here if needed
        return parent::getEloquentQuery();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Order Information')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->required()
                            ->unique(EcommerceOrder::class, 'order_number', ignoreRecord: true)
                            ->default(fn () => 'ORD-' . strtoupper(uniqid())),
                        
                        Forms\Components\Hidden::make('branch_id')
                            ->default(fn() => \Filament\Facades\Filament::getTenant()?->id),
                        
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->required()
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\TextInput::make('email')->email()->required(),
                                Forms\Components\TextInput::make('phone'),
                            ]),
                        
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'processing' => 'Processing',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                            ])
                            ->default('pending')
                            ->required(),
                        
                        Forms\Components\Select::make('payment_status')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'partial' => 'Partial',
                                'failed' => 'Failed',
                                'refunded' => 'Refunded',
                            ])
                            ->default('pending')
                            ->required(),
                        
                        Forms\Components\Select::make('fulfillment_status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Customer Details')
                    ->schema([
                        Forms\Components\TextInput::make('customer_email')
                            ->email()
                            ->required(),
                        
                        Forms\Components\TextInput::make('customer_phone')
                            ->tel()
                            ->required(),
                        
                        Forms\Components\KeyValue::make('billing_address')
                            ->addable(false)
                            ->deletable(false)
                            ->editableKeys(false)
                            ->default([
                                'name' => '',
                                'address_line_1' => '',
                                'address_line_2' => '',
                                'city' => '',
                                'state' => '',
                                'postal_code' => '',
                                'country' => 'Kenya',
                            ]),
                        
                        Forms\Components\KeyValue::make('shipping_address')
                            ->addable(false)
                            ->deletable(false)
                            ->editableKeys(false)
                            ->default([
                                'name' => '',
                                'address_line_1' => '',
                                'address_line_2' => '',
                                'city' => '',
                                'state' => '',
                                'postal_code' => '',
                                'country' => 'Kenya',
                            ]),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Financial Details')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->numeric()
                            ->prefix('KES')
                            ->required(),
                        
                        Forms\Components\TextInput::make('tax_amount')
                            ->numeric()
                            ->prefix('KES')
                            ->default(0),
                        
                        Forms\Components\TextInput::make('shipping_amount')
                            ->numeric()
                            ->prefix('KES')
                            ->default(0),
                        
                        Forms\Components\TextInput::make('discount_amount')
                            ->numeric()
                            ->prefix('KES')
                            ->default(0),
                        
                        Forms\Components\TextInput::make('total_amount')
                            ->numeric()
                            ->prefix('KES')
                            ->required(),
                        
                        Forms\Components\TextInput::make('currency')
                            ->default('KES')
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Delivery Details')
                    ->schema([
                        Forms\Components\Select::make('delivery_method')
                            ->options([
                                'pickup' => 'Pickup',
                                'delivery' => 'Delivery',
                                'shipping' => 'Shipping',
                            ])
                            ->default('pickup')
                            ->required(),
                        
                        Forms\Components\DateTimePicker::make('requested_delivery_date'),
                        
                        Forms\Components\KeyValue::make('delivery_time_slot')
                            ->addable(false)
                            ->deletable(false)
                            ->editableKeys(false)
                            ->default([
                                'start_time' => '',
                                'end_time' => '',
                            ]),
                        
                        Forms\Components\Textarea::make('special_instructions')
                            ->rows(3),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Payment Information')
                    ->schema([
                        Forms\Components\TextInput::make('payment_method'),
                        
                        Forms\Components\TextInput::make('payment_reference'),
                        
                        Forms\Components\DateTimePicker::make('paid_at'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Tracking & Notes')
                    ->schema([
                        Forms\Components\TextInput::make('tracking_number'),
                        
                        Forms\Components\Select::make('source')
                            ->options([
                                'website' => 'Website',
                                'mobile_app' => 'Mobile App',
                                'phone' => 'Phone',
                                'walk_in' => 'Walk-in',
                            ])
                            ->default('website'),
                        
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                        
                        Forms\Components\KeyValue::make('meta_data')
                            ->label('Additional Data'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('branch.name')
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('customer_email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('status')
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
                
                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'partial' => 'info',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('KES')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('delivery_method')
                    ->badge()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->getStateUsing(fn ($record) => $record->items->sum('quantity'))
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ])
                    ->multiple(),
                
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->multiple(),
                
                Tables\Filters\SelectFilter::make('delivery_method')
                    ->options([
                        'pickup' => 'Pickup',
                        'delivery' => 'Delivery',
                        'shipping' => 'Shipping',
                    ])
                    ->multiple(),
                
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('markPaid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (EcommerceOrder $record) => $record->update([
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                    ]))
                    ->visible(fn (EcommerceOrder $record) => $record->payment_status !== 'paid'),
                
                Tables\Actions\Action::make('fulfill')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (EcommerceOrder $record) {
                        $record->fulfillOrder();
                        $record->update(['status' => 'completed']);
                    })
                    ->visible(fn (EcommerceOrder $record) => 
                        $record->payment_status === 'paid' && 
                        !in_array($record->status, ['completed', 'cancelled', 'refunded'])
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('updateStatus')
                        ->label('Update Status')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->options([
                                    'pending' => 'Pending',
                                    'confirmed' => 'Confirmed',
                                    'processing' => 'Processing',
                                    'shipped' => 'Shipped',
                                    'delivered' => 'Delivered',
                                    'completed' => 'Completed',
                                    'cancelled' => 'Cancelled',
                                ])
                                ->required(),
                        ])
                        ->action(function (array $data, $records) {
                            $records->each(function ($record) use ($data) {
                                $record->update(['status' => $data['status']]);
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEcommerceOrders::route('/'),
            'create' => Pages\CreateEcommerceOrder::route('/create'),
            'view' => Pages\ViewEcommerceOrder::route('/{record}'),
            'edit' => Pages\EditEcommerceOrder::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() > 0 ? 'warning' : 'primary';
    }
}