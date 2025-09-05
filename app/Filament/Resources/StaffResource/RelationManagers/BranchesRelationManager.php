<?php

namespace App\Filament\Resources\StaffResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BranchesRelationManager extends RelationManager
{
    protected static string $relationship = 'branches';

    protected static ?string $title = 'Branch Assignments';

    protected static ?string $label = 'Branch';

    protected static ?string $pluralLabel = 'Branches';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('address')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('hourly_rate')
                            ->label('Branch-Specific Hourly Rate')
                            ->numeric()
                            ->prefix('KES')
                            ->placeholder('Leave empty to use default rate'),

                        Forms\Components\Toggle::make('is_primary_branch')
                            ->label('Primary Branch')
                            ->helperText('Main working location for this staff member'),

                        Forms\Components\Textarea::make('working_hours')
                            ->label('Working Schedule')
                            ->placeholder('e.g., Mon-Fri: 9AM-5PM, Sat: 9AM-2PM')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Branch-Specific Notes')
                            ->placeholder('Any special arrangements or notes for this branch')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Branch Name')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('address')
                    ->label('Location')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Contact')
                    ->icon('heroicon-o-phone'),

                Tables\Columns\TextColumn::make('pivot.hourly_rate')
                    ->label('Hourly Rate')
                    ->money('KES')
                    ->placeholder('Default rate'),

                Tables\Columns\IconColumn::make('pivot.is_primary_branch')
                    ->label('Primary')
                    ->boolean(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'maintenance' => 'warning',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('pivot.working_hours')
                    ->label('Schedule')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->pivot->working_hours),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'maintenance' => 'Maintenance',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\TextInput::make('hourly_rate')
                            ->label('Branch-Specific Hourly Rate')
                            ->numeric()
                            ->prefix('KES'),
                        Forms\Components\Toggle::make('is_primary_branch')
                            ->label('Primary Branch'),
                        Forms\Components\Textarea::make('working_hours')
                            ->label('Working Schedule'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}