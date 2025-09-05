<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\ProductCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Inventory & Products';

    protected static ?int $navigationSort = 1;

    // Override to filter products by branch inventory
    public static function getEloquentQuery(): Builder
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        return parent::getEloquentQuery()
            ->whereHas('branchInventory', function (Builder $query) use ($tenant) {
                $query->where('branch_id', $tenant->id);
            });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                        
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(Product::class, 'slug', ignoreRecord: true),
                        
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->maxLength(255)
                            ->unique(Product::class, 'sku', ignoreRecord: true),
                        
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'archived' => 'Archived',
                            ])
                            ->default('active')
                            ->required(),
                        
                        Forms\Components\Select::make('type')
                            ->options([
                                'physical' => 'Physical',
                                'digital' => 'Digital',
                                'service' => 'Service',
                            ])
                            ->default('physical')
                            ->required(),
                        
                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured Product'),
                        
                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Publish Date'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Description')
                    ->schema([
                        Forms\Components\Textarea::make('short_description')
                            ->maxLength(500)
                            ->rows(3),
                        
                        Forms\Components\RichEditor::make('description')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Categories')
                    ->schema([
                        Forms\Components\Select::make('categories')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                                Forms\Components\TextInput::make('slug')
                                    ->required(),
                                Forms\Components\Select::make('parent_id')
                                    ->label('Parent Category')
                                    ->options(ProductCategory::whereNull('parent_id')->pluck('name', 'id'))
                                    ->nullable(),
                            ]),
                    ]),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('base_price')
                            ->label('Base Price')
                            ->numeric()
                            ->prefix('KES')
                            ->required(),
                        
                        Forms\Components\TextInput::make('cost_price')
                            ->label('Cost Price')
                            ->numeric()
                            ->prefix('KES')
                            ->required(),
                        
                        Forms\Components\TextInput::make('compare_at_price')
                            ->label('Compare at Price')
                            ->numeric()
                            ->prefix('KES')
                            ->helperText('Original price before discount'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Inventory & Shipping')
                    ->schema([
                        Forms\Components\Toggle::make('track_inventory')
                            ->label('Track Inventory')
                            ->default(true),
                        
                        Forms\Components\Toggle::make('requires_shipping')
                            ->label('Requires Shipping')
                            ->default(true),
                        
                        Forms\Components\TextInput::make('weight')
                            ->numeric()
                            ->suffix('kg'),
                        
                        Forms\Components\Select::make('weight_unit')
                            ->options([
                                'kg' => 'Kilograms',
                                'g' => 'Grams',
                                'lb' => 'Pounds',
                                'oz' => 'Ounces',
                            ])
                            ->default('kg'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Images')
                    ->schema([
                        Forms\Components\FileUpload::make('images')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->directory('products')
                            ->maxFiles(10),
                    ]),

                Forms\Components\Section::make('SEO & Metadata')
                    ->schema([
                        Forms\Components\TagsInput::make('tags')
                            ->separator(','),
                        
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                        
                        Forms\Components\KeyValue::make('meta_data')
                            ->label('Custom Fields'),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('images')
                    ->label('Image')
                    ->circular()
                    ->stacked()
                    ->limit(1)
                    ->getStateUsing(fn ($record) => $record->images[0] ?? null),
                
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('categories.name')
                    ->badge()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('base_price')
                    ->money('KES')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'physical' => 'success',
                        'digital' => 'info',
                        'service' => 'warning',
                    }),
                
                Tables\Columns\IconColumn::make('track_inventory')
                    ->boolean()
                    ->label('Track Inv.'),
                
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured'),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'archived' => 'danger',
                    }),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'archived' => 'Archived',
                    ]),
                
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'physical' => 'Physical',
                        'digital' => 'Digital',
                        'service' => 'Service',
                    ]),
                
                Tables\Filters\Filter::make('is_featured')
                    ->query(fn (Builder $query): Builder => $query->where('is_featured', true))
                    ->label('Featured Only'),
                
                Tables\Filters\SelectFilter::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
                                    'active' => 'Active',
                                    'inactive' => 'Inactive',
                                    'archived' => 'Archived',
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}