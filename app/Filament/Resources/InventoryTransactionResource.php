<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryTransactionResource\Pages;
use App\Models\InventoryTransaction;
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

class InventoryTransactionResource extends Resource
{
    protected static ?string $model = InventoryTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    
    protected static ?string $navigationGroup = 'Inventory & Products';
    
    protected static ?int $navigationSort = 31;

    protected static ?string $navigationLabel = 'Stock Transactions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\Hidden::make('branch_id')
                            ->default(fn (): ?int => \Filament\Facades\Filament::getTenant()?->id),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('inventory_item_id')
                                    ->label('Inventory Item')
                                    ->relationship('inventoryItem', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\Select::make('transaction_type')
                                    ->label('Transaction Type')
                                    ->options(InventoryTransaction::getTransactionTypes())
                                    ->required()
                                    ->native(false),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->required()
                                    ->numeric()
                                    ->helperText('Use negative values for stock reductions'),

                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('Unit Cost (KES)')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->minValue(0)
                                    ->step(0.01),

                                Forms\Components\Select::make('staff_id')
                                    ->label('Staff Member')
                                    ->relationship('staff', 'name')
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\DateTimePicker::make('transaction_date')
                                    ->label('Transaction Date')
                                    ->required()
                                    ->default(now())
                                    ->maxDate(now()),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Optional transaction notes or reference'),
                    ]),

                Forms\Components\Section::make('Reference Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('reference_type')
                                    ->label('Reference Type')
                                    ->maxLength(255)
                                    ->placeholder('e.g., booking, purchase, adjustment'),

                                Forms\Components\TextInput::make('reference_id')
                                    ->label('Reference ID')
                                    ->numeric()
                                    ->placeholder('Related record ID'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('transaction_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                        'adjustment' => 'warning',
                        'waste' => 'gray',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => 
                        InventoryTransaction::getTransactionTypes()[$state] ?? ucfirst($state)
                    ),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color(fn (Model $record): string => 
                        $record->quantity >= 0 ? 'success' : 'danger'
                    ),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money('KES')
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Total Value')
                    ->getStateUsing(fn (Model $record): float => $record->getTotalValue())
                    ->money('KES'),

                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Staff')
                    ->searchable()
                    ->placeholder('System'),

                Tables\Columns\TextColumn::make('reference_type')
                    ->label('Reference')
                    ->badge()
                    ->color('info')
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(30)
                    ->placeholder('No notes')
                    ->tooltip(function (Model $record): ?string {
                        return $record->notes;
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('transaction_type')
                    ->options(InventoryTransaction::getTransactionTypes())
                    ->multiple(),

                SelectFilter::make('inventory_item_id')
                    ->label('Item')
                    ->relationship('inventoryItem', 'name')
                    ->searchable()
                    ->preload()
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
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '<=', $date),
                            );
                    }),

                Filter::make('stock_in')
                    ->label('Stock In Only')
                    ->query(fn (Builder $query): Builder => $query->byType('in')),

                Filter::make('stock_out')
                    ->label('Stock Out Only')
                    ->query(fn (Builder $query): Builder => $query->byType('out')),

                Filter::make('adjustments')
                    ->label('Adjustments Only')
                    ->query(fn (Builder $query): Builder => $query->byType('adjustment')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Model $record): bool => 
                        $record->created_at->isAfter(now()->subHours(24))
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make()
                        ->label('Export Selected'),
                ]),
            ])
            ->defaultSort('transaction_date', 'desc')
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
            'index' => Pages\ListInventoryTransactions::route('/'),
            'create' => Pages\CreateInventoryTransaction::route('/create'),
            'view' => Pages\ViewInventoryTransaction::route('/{record}'),
            'edit' => Pages\EditInventoryTransaction::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $todayTransactions = static::getModel()::whereDate('transaction_date', today())->count();
        return $todayTransactions > 0 ? (string) $todayTransactions : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['inventoryItem', 'staff', 'branch']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['notes', 'reference_type', 'inventoryItem.name'];
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        // Allow editing only within 24 hours of creation
        return $record->created_at->isAfter(now()->subHours(24));
    }

    public static function canDelete(Model $record): bool
    {
        // Prevent deletion of transactions older than 24 hours
        return $record->created_at->isAfter(now()->subHours(24));
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        return parent::getEloquentQuery()
            ->when($tenant, fn (Builder $query) => $query->where('branch_id', $tenant->id));
    }
}