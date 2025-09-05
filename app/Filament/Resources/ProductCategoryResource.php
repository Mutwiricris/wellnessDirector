<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductCategoryResource\Pages;
use App\Models\ProductCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;


    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Inventory & Products';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Categories';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Category Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                        
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ProductCategory::class, 'slug', ignoreRecord: true),
                        
                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Category')
                            ->options(fn () => ProductCategory::whereNull('parent_id')->pluck('name', 'id'))
                            ->nullable()
                            ->helperText('Leave empty for top-level category'),
                        
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->default('active')
                            ->required(),
                        
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Description & Media')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->rows(4),
                        
                        Forms\Components\FileUpload::make('image')
                            ->image()
                            ->directory('categories')
                            ->helperText('Category thumbnail image'),
                    ]),

                Forms\Components\Section::make('SEO & Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('meta_data')
                            ->label('Custom Fields')
                            ->helperText('Additional metadata for this category'),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder-category.png')),
                
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->parent?->name),
                
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent Category')
                    ->sortable()
                    ->placeholder('Top Level'),
                
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('children_count')
                    ->label('Subcategories')
                    ->counts('children')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
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
                    ]),
                
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent Category')
                    ->options(fn () => ProductCategory::whereNull('parent_id')->pluck('name', 'id'))
                    ->placeholder('All Categories'),
                
                Tables\Filters\Filter::make('top_level')
                    ->query(fn ($query) => $query->whereNull('parent_id'))
                    ->label('Top Level Only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function (ProductCategory $record) {
                        if ($record->children()->exists()) {
                            throw new \Exception('Cannot delete category with subcategories');
                        }
                        if ($record->products()->exists()) {
                            throw new \Exception('Cannot delete category with products');
                        }
                        $record->delete();
                    }),
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
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
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
            'index' => Pages\ListProductCategories::route('/'),
            'create' => Pages\CreateProductCategory::route('/create'),
            'view' => Pages\ViewProductCategory::route('/{record}'),
            'edit' => Pages\EditProductCategory::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}