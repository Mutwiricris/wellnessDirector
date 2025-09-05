<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Client';
    
    protected static ?string $pluralModelLabel = 'Clients';

    protected static ?string $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationGroup = 'Customer Management';
    
    protected static ?int $navigationSort = 1;
    
    // Clients don't have a direct tenant relationship - they're global
    protected static ?string $tenantOwnershipRelationshipName = null;
    
    // Scope to only show clients (user_type = 'user')
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_type', 'user');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Personal Information')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('last_name')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->maxLength(20),
                        Forms\Components\DatePicker::make('date_of_birth')
                            ->native(false),
                        Forms\Components\Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                                'prefer_not_to_say' => 'Prefer not to say'
                            ])
                            ->native(false),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Health & Preferences')
                    ->schema([
                        Forms\Components\Textarea::make('allergies')
                            ->rows(3)
                            ->placeholder('List any allergies or sensitivities'),
                        Forms\Components\KeyValue::make('preferences')
                            ->label('Service Preferences')
                            ->keyLabel('Preference Type')
                            ->valueLabel('Details'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Account Settings')
                    ->schema([
                        Forms\Components\Hidden::make('user_type')
                            ->default('user'),
                        Forms\Components\Hidden::make('name')
                            ->dehydrateStateUsing(function (Forms\Get $get) {
                                return trim($get('first_name') . ' ' . $get('last_name'));
                            }),
                        Forms\Components\Select::make('create_account_status')
                            ->options([
                                'no_creation' => 'No Account',
                                'accepted' => 'Accepted',
                                'active' => 'Active'
                            ])
                            ->default('no_creation')
                            ->native(false),
                        Forms\Components\Toggle::make('marketing_consent')
                            ->label('Marketing Consent')
                            ->default(false),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->helperText('Leave empty to keep current password'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Full Name')
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('gender')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'male' => 'blue',
                        'female' => 'pink',
                        'other' => 'gray',
                        'prefer_not_to_say' => 'gray',
                        default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('date_of_birth')
                    ->date()
                    ->sortable(),
                Tables\Columns\IconColumn::make('marketing_consent')
                    ->boolean()
                    ->label('Marketing'),
                Tables\Columns\TextColumn::make('create_account_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'accepted' => 'warning',
                        'no_creation' => 'gray',
                        default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                        'other' => 'Other',
                        'prefer_not_to_say' => 'Prefer not to say'
                    ]),
                Tables\Filters\SelectFilter::make('create_account_status')
                    ->options([
                        'no_creation' => 'No Account',
                        'accepted' => 'Accepted',
                        'active' => 'Active'
                    ]),
                Tables\Filters\Filter::make('marketing_consent')
                    ->query(fn (Builder $query): Builder => $query->where('marketing_consent', true))
                    ->label('Marketing Consent'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordTitleAttribute('name')
            ->recordUrl(
                fn (User $record): string => UserResource::getUrl('view', ['record' => $record])
            );
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BookingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}