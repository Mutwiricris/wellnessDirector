<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages;
use App\Filament\Resources\BranchResource\RelationManagers;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    
    protected static ?string $navigationGroup = 'System Administration';
    
    protected static ?int $navigationSort = 1;
    
    // Since Branch is the tenant model itself, we don't need tenant scoping
    protected static bool $isScopedToTenant = false;
    
    // Override the query to show all branches for directors
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Branch Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\Textarea::make('address')
                            ->required()
                            ->rows(3),
                            
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->required(),
                            
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required(),
                            
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'maintenance' => 'Under Maintenance'
                            ])
                            ->default('active')
                            ->required(),
                            
                        Forms\Components\Select::make('timezone')
                            ->options([
                                'Africa/Nairobi' => 'Nairobi (EAT)',
                                'UTC' => 'UTC'
                            ])
                            ->default('Africa/Nairobi')
                            ->required(),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Working Hours')
                    ->schema([
                        Forms\Components\Repeater::make('working_hours')
                            ->schema([
                                Forms\Components\Select::make('day')
                                    ->options([
                                        'monday' => 'Monday',
                                        'tuesday' => 'Tuesday', 
                                        'wednesday' => 'Wednesday',
                                        'thursday' => 'Thursday',
                                        'friday' => 'Friday',
                                        'saturday' => 'Saturday',
                                        'sunday' => 'Sunday'
                                    ])
                                    ->required(),
                                    
                                Forms\Components\TimePicker::make('open_time')
                                    ->required(),
                                    
                                Forms\Components\TimePicker::make('close_time')
                                    ->required(),
                                    
                                Forms\Components\Toggle::make('is_closed')
                                    ->label('Closed on this day')
                                    ->default(false),
                            ])
                            ->columns(4)
                            ->defaultItems(7)
                            ->reorderable(false)
                            ->collapsible(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('address')
                    ->limit(50)
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->icon('heroicon-m-phone'),
                    
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->icon('heroicon-m-envelope'),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                        'warning' => 'maintenance',
                    ]),
                    
                Tables\Columns\TextColumn::make('bookings_count')
                    ->counts('bookings')
                    ->label('Total Bookings')
                    ->badge()
                    ->color('info'),
                    
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
                        'maintenance' => 'Under Maintenance'
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
