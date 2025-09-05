<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchProductInventoryResource\Pages;
use App\Models\BranchProductInventory;
use App\Models\Branch;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BranchProductInventoryResource extends Resource
{
    protected static ?string $model = BranchProductInventory::class;


    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Inventory & Products';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Branch Inventory';

    public static function getEloquentQuery(): Builder
    {
        // For now, return all inventory since we removed tenancy
        // Later you can add branch filtering here if needed
        return parent::getEloquentQuery();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Product & Branch')
                    ->schema([
                        Forms\Components\Hidden::make('branch_id')
                            ->default(fn() => \Filament\Facades\Filament::getTenant()?->id),
                        
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $product = Product::find($state);
                                    if ($product && $product->variants()->exists()) {
                                        $set('show_variants', true);
                                    } else {
                                        $set('show_variants', false);
                                        $set('product_variant_id', null);
                                    }
                                }
                            }),
                        
                        Forms\Components\Select::make('product_variant_id')
                            ->label('Product Variant')
                            ->relationship('productVariant', 'title')
                            ->searchable()
                            ->preload()
                            ->visible(fn (callable $get) => $get('show_variants'))
                            ->options(function (callable $get) {
                                $productId = $get('product_id');
                                if (!$productId) {
                                    return [];
                                }
                                return Product::find($productId)?->variants()->pluck('title', 'id') ?? [];
                            }),
                        
                        Forms\Components\Hidden::make('show_variants'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Inventory Levels')
                    ->schema([
                        Forms\Components\TextInput::make('quantity_on_hand')
                            ->label('Quantity on Hand')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        
                        Forms\Components\TextInput::make('quantity_reserved')
                            ->label('Reserved Quantity')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        
                        Forms\Components\TextInput::make('reorder_level')
                            ->label('Reorder Level')
                            ->numeric()
                            ->default(10)
                            ->required()
                            ->helperText('Alert when stock falls below this level'),
                        
                        Forms\Components\TextInput::make('max_stock_level')
                            ->label('Maximum Stock Level')
                            ->numeric()
                            ->helperText('Optional maximum stock limit'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pricing & Availability')
                    ->schema([
                        Forms\Components\TextInput::make('branch_price')
                            ->label('Branch-Specific Price')
                            ->numeric()
                            ->prefix('KES')
                            ->helperText('Override default product price for this branch'),
                        
                        Forms\Components\Toggle::make('is_available')
                            ->label('Available for Sale')
                            ->default(true),
                        
                        Forms\Components\DatePicker::make('last_restocked_at')
                            ->label('Last Restocked'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('branch.name')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('product.name')
                    ->sortable()
                    ->searchable()
                    ->limit(30),
                
                Tables\Columns\TextColumn::make('productVariant.title')
                    ->label('Variant')
                    ->sortable()
                    ->placeholder('Main Product')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('quantity_on_hand')
                    ->label('On Hand')
                    ->sortable()
                    ->color(fn ($state, $record) => $state <= $record->reorder_level ? 'danger' : 'success'),
                
                Tables\Columns\TextColumn::make('quantity_reserved')
                    ->label('Reserved')
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('quantity_available')
                    ->label('Available')
                    ->getStateUsing(fn ($record) => max(0, $record->quantity_on_hand - $record->quantity_reserved))
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                
                Tables\Columns\TextColumn::make('reorder_level')
                    ->label('Reorder Level')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('branch_price')
                    ->label('Branch Price')
                    ->money('KES')
                    ->placeholder('Default Price')
                    ->toggleable(),
                
                Tables\Columns\IconColumn::make('is_available')
                    ->boolean()
                    ->label('Available'),
                
                Tables\Columns\TextColumn::make('last_restocked_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\Filter::make('low_stock')
                    ->query(fn (Builder $query): Builder => $query->whereRaw('quantity_on_hand <= reorder_level'))
                    ->label('Low Stock Items'),
                
                Tables\Filters\Filter::make('out_of_stock')
                    ->query(fn (Builder $query): Builder => $query->where('quantity_on_hand', '<=', 0))
                    ->label('Out of Stock'),
                
                Tables\Filters\Filter::make('available_only')
                    ->query(fn (Builder $query): Builder => $query->where('is_available', true))
                    ->label('Available Only'),
                
                Tables\Filters\Filter::make('has_reserved')
                    ->query(fn (Builder $query): Builder => $query->where('quantity_reserved', '>', 0))
                    ->label('Has Reserved Stock'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('adjust_stock')
                    ->label('Adjust Stock')
                    ->icon('heroicon-o-arrows-up-down')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('adjustment')
                            ->label('Stock Adjustment')
                            ->numeric()
                            ->required()
                            ->helperText('Positive number to add stock, negative to reduce'),
                        
                        Forms\Components\Select::make('adjustment_type')
                            ->label('Adjustment Type')
                            ->options([
                                'adjustment' => 'Manual Adjustment',
                                'in' => 'Stock In',
                                'out' => 'Stock Out',
                                'waste' => 'Waste/Damage',
                                'return' => 'Return',
                            ])
                            ->default('adjustment')
                            ->required(),
                        
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->helperText('Reason for adjustment'),
                    ])
                    ->action(function (BranchProductInventory $record, array $data) {
                        $record->adjustStock($data['adjustment'], $data['adjustment_type']);
                        
                        // Create stock movement with notes
                        if (!empty($data['notes'])) {
                            $record->stockMovements()->latest()->first()?->update([
                                'notes' => $data['notes']
                            ]);
                        }
                    })
                    ->requiresConfirmation(),
                
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('updateAvailability')
                        ->label('Update Availability')
                        ->icon('heroicon-o-eye')
                        ->form([
                            Forms\Components\Toggle::make('is_available')
                                ->label('Available for Sale')
                                ->required(),
                        ])
                        ->action(function (array $data, $records) {
                            $records->each(function ($record) use ($data) {
                                $record->update(['is_available' => $data['is_available']]);
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('quantity_on_hand', 'asc')
            ->poll('30s'); // Auto-refresh every 30 seconds
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
            'index' => Pages\ListBranchProductInventories::route('/'),
            'create' => Pages\CreateBranchProductInventory::route('/create'),
            'view' => Pages\ViewBranchProductInventory::route('/{record}'),
            'edit' => Pages\EditBranchProductInventory::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereRaw('quantity_on_hand <= reorder_level')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::whereRaw('quantity_on_hand <= reorder_level')->count() > 0 ? 'danger' : 'primary';
    }
}