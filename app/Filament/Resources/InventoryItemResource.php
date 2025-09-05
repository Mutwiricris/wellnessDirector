<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryItemResource\Pages;
use App\Models\InventoryItem;
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
use Filament\Notifications\Notification;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';
    
    protected static ?string $navigationGroup = 'Inventory & Products';
    
    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Inventory Items';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Hidden::make('branch_id')
                            ->default(fn (): ?int => \Filament\Facades\Filament::getTenant()?->id),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Item Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Massage Oil, Towels, Face Mask'),

                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU/Barcode')
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->placeholder('Optional unique identifier'),

                                Forms\Components\Select::make('category')
                                    ->label('Category')
                                    ->options(InventoryItem::getCategories())
                                    ->required()
                                    ->native(false),

                                Forms\Components\Select::make('type')
                                    ->label('Type')
                                    ->options(InventoryItem::getTypes())
                                    ->required()
                                    ->native(false)
                                    ->helperText('Consumable items are used up during services'),

                                Forms\Components\Select::make('unit')
                                    ->label('Unit of Measurement')
                                    ->options(InventoryItem::getUnits())
                                    ->required()
                                    ->native(false),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active Item')
                                    ->default(true)
                                    ->helperText('Inactive items won\'t appear in selection lists'),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Optional description or usage notes'),
                    ]),

                Forms\Components\Section::make('Pricing & Stock')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('cost_price')
                                    ->label('Cost Price (KES)')
                                    ->required()
                                    ->numeric()
                                    ->prefix('KES')
                                    ->minValue(0)
                                    ->step(0.01),

                                Forms\Components\TextInput::make('selling_price')
                                    ->label('Selling Price (KES)')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->helperText('For retail items only'),

                                Forms\Components\TextInput::make('current_stock')
                                    ->label('Current Stock')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),

                                Forms\Components\TextInput::make('minimum_stock')
                                    ->label('Minimum Stock Level')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(10)
                                    ->helperText('Alert when stock falls below this level'),

                                Forms\Components\TextInput::make('maximum_stock')
                                    ->label('Maximum Stock Level')
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Optional maximum stock limit'),

                                Forms\Components\TextInput::make('reorder_level')
                                    ->label('Reorder Level')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(10)
                                    ->helperText('Suggested reorder quantity'),
                            ]),
                    ]),

                Forms\Components\Section::make('Supplier Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('supplier_name')
                                    ->label('Supplier Name')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('supplier_contact')
                                    ->label('Supplier Contact')
                                    ->maxLength(255)
                                    ->tel(),

                                Forms\Components\DatePicker::make('last_restocked')
                                    ->label('Last Restocked Date')
                                    ->maxDate(now()),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Expiry Tracking')
                    ->schema([
                        Forms\Components\Toggle::make('track_expiry')
                            ->label('Track Expiry Date')
                            ->reactive()
                            ->helperText('Enable for perishable items'),

                        Forms\Components\DatePicker::make('expiry_date')
                            ->label('Expiry Date')
                            ->visible(fn (Forms\Get $get) => $get('track_expiry'))
                            ->minDate(now())
                            ->helperText('When this batch expires'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): string => 
                        InventoryItem::getCategories()[$state] ?? ucfirst($state)
                    ),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock')
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color(fn (Model $record): string => match (true) {
                        $record->current_stock <= 0 => 'danger',
                        $record->isLowStock() => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (Model $record): string => 
                        $record->current_stock . ' ' . $record->unit
                    ),

                Tables\Columns\TextColumn::make('minimum_stock')
                    ->label('Min Stock')
                    ->sortable()
                    ->formatStateUsing(fn (Model $record): string => 
                        $record->minimum_stock . ' ' . $record->unit
                    ),

                Tables\Columns\TextColumn::make('cost_price')
                    ->label('Cost')
                    ->money('KES')
                    ->sortable(),

                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Selling Price')
                    ->money('KES')
                    ->placeholder('N/A'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('stock_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (Model $record): string => $record->getStockStatus())
                    ->color(fn (string $state): string => match ($state) {
                        'in_stock' => 'success',
                        'low_stock' => 'warning',
                        'out_of_stock' => 'danger',
                        'overstock' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => 
                        str_replace('_', ' ', ucwords($state))
                    ),

                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expires')
                    ->date('M j, Y')
                    ->placeholder('N/A')
                    ->color(fn (Model $record): string => 
                        $record->isExpiringSoon() ? 'warning' : 'gray'
                    ),

                Tables\Columns\TextColumn::make('last_restocked')
                    ->label('Last Restocked')
                    ->date('M j, Y')
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(InventoryItem::getCategories())
                    ->multiple(),

                SelectFilter::make('type')
                    ->options(InventoryItem::getTypes())
                    ->multiple(),

                Filter::make('low_stock')
                    ->label('Low Stock Items')
                    ->query(fn (Builder $query): Builder => $query->lowStock()),

                Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(fn (Builder $query): Builder => $query->where('current_stock', '<=', 0)),

                Filter::make('expiring_soon')
                    ->label('Expiring Soon')
                    ->query(fn (Builder $query): Builder => $query->expiringSoon()),

                Filter::make('inactive')
                    ->label('Inactive Items')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', false)),
            ])
            ->actions([
                Tables\Actions\Action::make('adjust_stock')
                    ->label('Adjust Stock')
                    ->icon('heroicon-o-plus-minus')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('new_quantity')
                            ->label('New Stock Quantity')
                            ->required()
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for Adjustment')
                            ->required()
                            ->placeholder('e.g., Physical count correction, damaged items'),
                    ])
                    ->action(function (Model $record, array $data): void {
                        $record->adjustStock(
                            $data['new_quantity'],
                            $data['reason'],
                            auth()->user()->staff->id ?? null
                        );

                        Notification::make()
                            ->title('Stock Adjusted')
                            ->body("Stock for {$record->name} adjusted to {$data['new_quantity']} {$record->unit}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('add_stock')
                    ->label('Add Stock')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity to Add')
                            ->required()
                            ->numeric()
                            ->minValue(1),
                        Forms\Components\TextInput::make('unit_cost')
                            ->label('Unit Cost (KES)')
                            ->numeric()
                            ->prefix('KES')
                            ->helperText('Leave empty to use current cost price'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('e.g., Purchase order #123, supplier delivery'),
                    ])
                    ->action(function (Model $record, array $data): void {
                        $record->addStock(
                            $data['quantity'],
                            $data['notes'] ?? null,
                            auth()->user()->staff->id ?? null
                        );

                        if (!empty($data['unit_cost'])) {
                            $record->update(['cost_price' => $data['unit_cost']]);
                        }

                        Notification::make()
                            ->title('Stock Added')
                            ->body("{$data['quantity']} {$record->unit} added to {$record->name}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ExportBulkAction::make()
                        ->label('Export Selected'),
                ]),
            ])
            ->defaultSort('name')
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
            'index' => Pages\ListInventoryItems::route('/'),
            'create' => Pages\CreateInventoryItem::route('/create'),
            'view' => Pages\ViewInventoryItem::route('/{record}'),
            'edit' => Pages\EditInventoryItem::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if (!$tenant) return null;
            
            $lowStockCount = static::getModel()::where('branch_id', $tenant->id)
                ->lowStock()
                ->count();
            return $lowStockCount > 0 ? (string) $lowStockCount : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['branch']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'description', 'supplier_name'];
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        return parent::getEloquentQuery()
            ->when($tenant, fn (Builder $query) => $query->where('branch_id', $tenant->id));
    }
}