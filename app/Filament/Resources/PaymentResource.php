<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Resources\PaymentResource\RelationManagers;
use App\Models\Payment;
use App\Models\Booking;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    
    protected static ?string $navigationGroup = 'Financial Management';
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $recordTitleAttribute = 'transaction_reference';

    public static function getEloquentQuery(): Builder
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        return parent::getEloquentQuery()
            ->where('branch_id', $tenant->id)
            ->with(['booking.client', 'booking.service']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payment Details')
                    ->schema([
                        Forms\Components\Select::make('booking_id')
                            ->label('Booking')
                            ->relationship('booking', 'booking_reference')
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return $record->booking_reference . ' - ' . 
                                       ($record->client->first_name ?? '') . ' ' . 
                                       ($record->client->last_name ?? '') . ' - ' .
                                       ($record->service->name ?? '');
                            })
                            ->searchable(['booking_reference'])
                            ->required()
                            ->preload(),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->label('Amount')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->step(0.01)
                                    ->required(),
                                    
                                Forms\Components\Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'cash' => 'Cash',
                                        'mpesa' => 'M-Pesa',
                                        'card' => 'Credit/Debit Card',
                                        'bank_transfer' => 'Bank Transfer'
                                    ])
                                    ->required()
                                    ->reactive()
                                    ->native(false),
                            ]),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'completed' => 'Completed',
                                        'failed' => 'Failed',
                                        'refunded' => 'Refunded'
                                    ])
                                    ->default('pending')
                                    ->required()
                                    ->native(false),
                                    
                                Forms\Components\DateTimePicker::make('processed_at')
                                    ->label('Processed At')
                                    ->seconds(false),
                            ]),
                    ])->columns(1),
                    
                Forms\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\TextInput::make('transaction_reference')
                            ->label('Transaction Reference')
                            ->unique(Payment::class, 'transaction_reference', ignoreRecord: true)
                            ->maxLength(100),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('mpesa_checkout_request_id')
                                    ->label('M-Pesa Checkout ID')
                                    ->maxLength(100)
                                    ->visible(fn (Forms\Get $get) => $get('payment_method') === 'mpesa'),
                                    
                                Forms\Components\TextInput::make('mpesa_transaction_id')
                                    ->label('M-Pesa Transaction ID')
                                    ->maxLength(100)
                                    ->visible(fn (Forms\Get $get) => $get('payment_method') === 'mpesa'),
                            ]),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('card_last_four')
                                    ->label('Card Last 4 Digits')
                                    ->maxLength(4)
                                    ->visible(fn (Forms\Get $get) => $get('payment_method') === 'card'),
                                    
                                Forms\Components\TextInput::make('card_brand')
                                    ->label('Card Brand')
                                    ->maxLength(50)
                                    ->visible(fn (Forms\Get $get) => $get('payment_method') === 'card'),
                            ]),
                    ])->columns(1),
                    
                Forms\Components\Section::make('Refund Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('refund_amount')
                                    ->label('Refund Amount')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->step(0.01)
                                    ->visible(fn (Forms\Get $get) => $get('status') === 'refunded'),
                                    
                                Forms\Components\DateTimePicker::make('refunded_at')
                                    ->label('Refunded At')
                                    ->seconds(false)
                                    ->visible(fn (Forms\Get $get) => $get('status') === 'refunded'),
                            ]),
                    ])->columns(1),
                    
                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('booking.booking_reference')
                    ->label('Booking')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => route('filament.admin.resources.bookings.view', ['tenant' => \Filament\Facades\Filament::getTenant(), 'record' => $record->booking_id]))
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('booking.client.first_name')
                    ->label('Client')
                    ->formatStateUsing(fn ($record) => 
                        ($record->booking->client->first_name ?? '') . ' ' . 
                        ($record->booking->client->last_name ?? '')
                    )
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('KES')
                    ->sortable()
                    ->weight(FontWeight::Bold),
                    
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->formatStateUsing(fn ($record) => $record->getPaymentMethodDisplayName())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'mpesa' => 'warning',
                        'card' => 'info',
                        'bank_transfer' => 'gray',
                        default => 'gray'
                    }),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($record) => $record->getStatusColor()),
                    
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not processed'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded'
                    ])
                    ->multiple(),
                    
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'cash' => 'Cash',
                        'mpesa' => 'M-Pesa',
                        'card' => 'Credit/Debit Card',
                        'bank_transfer' => 'Bank Transfer'
                    ])
                    ->multiple(),
                    
                Tables\Filters\Filter::make('processed_today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('processed_at', today()))
                    ->label('Processed Today'),
                    
                Tables\Filters\Filter::make('amount_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('min_amount')
                                    ->label('Min Amount')
                                    ->numeric()
                                    ->prefix('KES'),
                                Forms\Components\TextInput::make('max_amount')
                                    ->label('Max Amount')
                                    ->numeric()
                                    ->prefix('KES'),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['min_amount'], fn (Builder $query, $amount) => $query->where('amount', '>=', $amount))
                            ->when($data['max_amount'], fn (Builder $query, $amount) => $query->where('amount', '<=', $amount));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_completed')
                    ->label('Mark Completed')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Payment $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->action(function (Payment $record) {
                        $record->markAsCompleted();
                        
                        // Update booking payment status
                        $record->booking->update(['payment_status' => 'completed']);
                    }),
                    
                Tables\Actions\Action::make('mark_failed')
                    ->label('Mark Failed')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Payment $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->action(function (Payment $record) {
                        $record->markAsFailed();
                        
                        // Update booking payment status
                        $record->booking->update(['payment_status' => 'failed']);
                    }),
                    
                Tables\Actions\Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Payment $record): bool => $record->isCompleted())
                    ->form([
                        Forms\Components\TextInput::make('refund_amount')
                            ->label('Refund Amount')
                            ->numeric()
                            ->prefix('KES')
                            ->step(0.01)
                            ->required()
                            ->default(fn (Payment $record) => $record->amount),
                        Forms\Components\Textarea::make('refund_reason')
                            ->label('Refund Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Payment $record, array $data) {
                        $record->refund($data['refund_amount'], $data['refund_reason']);
                        
                        // Update booking payment status
                        $record->booking->update(['payment_status' => 'refunded']);
                    }),
                    
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function (Payment $payment) {
                                if ($payment->isPending()) {
                                    $payment->markAsCompleted();
                                    $payment->booking->update(['payment_status' => 'completed']);
                                }
                            });
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->groups([
                Tables\Grouping\Group::make('status')
                    ->label('Payment Status')
                    ->collapsible(),
                Tables\Grouping\Group::make('payment_method')
                    ->label('Payment Method')
                    ->collapsible(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Payment Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('transaction_reference')
                                    ->label('Transaction Reference')
                                    ->copyable()
                                    ->weight(FontWeight::Bold),
                                    
                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Amount')
                                    ->money('KES')
                                    ->weight(FontWeight::Bold),
                                    
                                Infolists\Components\TextEntry::make('payment_method')
                                    ->label('Payment Method')
                                    ->formatStateUsing(fn ($record) => $record->getPaymentMethodDisplayName())
                                    ->badge(),
                                    
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($record) => $record->getStatusColor()),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Booking Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('booking.booking_reference')
                            ->label('Booking Reference')
                            ->copyable(),
                            
                        Infolists\Components\TextEntry::make('booking.client.first_name')
                            ->label('Client')
                            ->formatStateUsing(fn ($record) => 
                                ($record->booking->client->first_name ?? '') . ' ' . 
                                ($record->booking->client->last_name ?? '')
                            ),
                            
                        Infolists\Components\TextEntry::make('booking.service.name')
                            ->label('Service'),
                            
                        Infolists\Components\TextEntry::make('booking.appointment_date')
                            ->label('Appointment Date')
                            ->date(),
                    ])->columns(2),
                    
                Infolists\Components\Section::make('Transaction Timeline')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),
                                    
                                Infolists\Components\TextEntry::make('processed_at')
                                    ->label('Processed At')
                                    ->dateTime()
                                    ->placeholder('Not processed'),
                                    
                                Infolists\Components\TextEntry::make('refunded_at')
                                    ->label('Refunded At')
                                    ->dateTime()
                                    ->placeholder('Not refunded')
                                    ->visible(fn ($record) => $record->isRefunded()),
                                    
                                Infolists\Components\TextEntry::make('refund_amount')
                                    ->label('Refund Amount')
                                    ->money('KES')
                                    ->visible(fn ($record) => $record->isRefunded()),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Additional Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                    ])->columns(1),
            ]);
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
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'view' => Pages\ViewPayment::route('/{record}'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            
            if (!$tenant || !auth()->check()) {
                return null;
            }
            
            $count = static::getModel()::where('branch_id', $tenant->id)
                ->where('status', 'pending')
                ->count();
            
            return $count > 0 ? (string) $count : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}