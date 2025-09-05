<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PosTransactionResource\Pages;
use App\Models\PosTransaction;
use App\Models\Service;
use App\Models\InventoryItem;
use App\Models\Staff;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Model;

class PosTransactionResource extends Resource
{
    protected static ?string $model = PosTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';
    
    protected static ?string $navigationGroup = 'Financial Management';
    
    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'POS Transactions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\Hidden::make('branch_id')
                            ->default(fn (): ?int => \Filament\Facades\Filament::getTenant()?->id),
                            
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('transaction_number')
                                    ->label('Transaction #')
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\Select::make('staff_id')
                                    ->label('Staff Member')
                                    ->relationship('staff', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\Select::make('client_id')
                                    ->label('Customer')
                                    ->relationship('client', 'first_name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('first_name')
                                            ->required(),
                                        Forms\Components\TextInput::make('last_name')
                                            ->required(),
                                        Forms\Components\TextInput::make('phone')
                                            ->tel(),
                                        Forms\Components\TextInput::make('email')
                                            ->email(),
                                    ]),

                                Forms\Components\Select::make('transaction_type')
                                    ->label('Transaction Type')
                                    ->options(PosTransaction::getTransactionTypes())
                                    ->default('service')
                                    ->required()
                                    ->native(false),

                                Forms\Components\Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options(PosTransaction::getPaymentMethods())
                                    ->default('cash')
                                    ->required()
                                    ->native(false),

                                Forms\Components\Select::make('payment_status')
                                    ->label('Payment Status')
                                    ->options(PosTransaction::getPaymentStatuses())
                                    ->default('pending')
                                    ->required()
                                    ->native(false),
                            ]),
                    ]),

                Forms\Components\Section::make('Customer Information')
                    ->schema([
                        Forms\Components\KeyValue::make('customer_info')
                            ->label('Walk-in Customer Details')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->default([
                                'name' => '',
                                'phone' => '',
                                'email' => ''
                            ])
                            ->visible(fn (Forms\Get $get) => !$get('client_id')),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Financial Details')
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal (KES)')
                                    ->required()
                                    ->numeric()
                                    ->prefix('KES')
                                    ->minValue(0)
                                    ->step(0.01),

                                Forms\Components\TextInput::make('discount_amount')
                                    ->label('Discount (KES)')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0),

                                Forms\Components\TextInput::make('tax_amount')
                                    ->label('Tax (KES)')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0),

                                Forms\Components\TextInput::make('tip_amount')
                                    ->label('Tip (KES)')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0),

                                Forms\Components\TextInput::make('total_amount')
                                    ->label('Total Amount (KES)')
                                    ->required()
                                    ->numeric()
                                    ->prefix('KES')
                                    ->minValue(0)
                                    ->step(0.01),
                            ]),
                    ]),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Transaction Notes')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('mpesa_transaction_id')
                                    ->label('M-Pesa Transaction ID')
                                    ->visible(fn (Forms\Get $get) => $get('payment_method') === 'mpesa'),

                                Forms\Components\TextInput::make('receipt_number')
                                    ->label('Receipt Number')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_number')
                    ->label('Transaction #')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Staff')
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->getStateUsing(fn (Model $record): string => $record->getCustomerName())
                    ->searchable(['client.first_name', 'client.last_name']),

                Tables\Columns\TextColumn::make('transaction_type')
                    ->label('Type')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): string => 
                        PosTransaction::getTransactionTypes()[$state] ?? ucfirst($state)
                    ),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('KES')
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('success'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'mpesa' => 'warning',
                        'card' => 'info',
                        'bank_transfer' => 'primary',
                        'mixed' => 'secondary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing' => 'warning',
                        'pending' => 'gray',
                        'failed' => 'danger',
                        'refunded' => 'secondary',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('receipt_sent')
                    ->label('Receipt')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                SelectFilter::make('payment_status')
                    ->options(PosTransaction::getPaymentStatuses())
                    ->multiple(),

                SelectFilter::make('payment_method')
                    ->options(PosTransaction::getPaymentMethods())
                    ->multiple(),

                SelectFilter::make('transaction_type')
                    ->options(PosTransaction::getTransactionTypes())
                    ->multiple(),

                SelectFilter::make('staff_id')
                    ->label('Staff')
                    ->relationship('staff', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),

                Filter::make('amount_range')
                    ->form([
                        Forms\Components\TextInput::make('min_amount')
                            ->label('Min Amount')
                            ->numeric()
                            ->prefix('KES'),
                        Forms\Components\TextInput::make('max_amount')
                            ->label('Max Amount')
                            ->numeric()
                            ->prefix('KES'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('total_amount', '>=', $amount),
                            )
                            ->when(
                                $data['max_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('total_amount', '<=', $amount),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('view_receipt')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Model $record): string => route('pos.receipt.view', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('print_receipt')
                    ->label('Print Receipt')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn (Model $record): string => route('pos.receipt.print', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('download_receipt')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('warning')
                    ->url(fn (Model $record): string => route('pos.receipt.download', $record)),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Model $record): bool => $record->payment_status === 'pending'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make()
                        ->label('Export Selected'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
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
            'index' => Pages\ListPosTransactions::route('/'),
            'create' => Pages\CreatePosTransaction::route('/create'),
            'view' => Pages\ViewPosTransaction::route('/{record}'),
            'edit' => Pages\EditPosTransaction::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if (!$tenant) return null;
            
            $pendingCount = static::getModel()::where('branch_id', $tenant->id)
                ->where('payment_status', 'pending')
                ->count();
            return $pendingCount > 0 ? (string) $pendingCount : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        return parent::getEloquentQuery()
            ->when($tenant, fn (Builder $query) => $query->where('branch_id', $tenant->id));
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['staff', 'client', 'items']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['transaction_number', 'mpesa_transaction_id', 'notes'];
    }
}