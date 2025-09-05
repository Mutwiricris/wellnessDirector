<?php

namespace App\Filament\Resources\ServiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BranchesRelationManager extends RelationManager
{
    protected static string $relationship = 'branches';

    protected static ?string $title = 'Branch Availability';

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

                        Forms\Components\TextInput::make('custom_price')
                            ->label('Branch-Specific Price')
                            ->numeric()
                            ->prefix('KES')
                            ->placeholder('Leave empty to use default price'),

                        Forms\Components\Toggle::make('is_available')
                            ->label('Available at this Branch')
                            ->default(true),

                        Forms\Components\Textarea::make('branch_notes')
                            ->label('Branch-Specific Notes')
                            ->placeholder('Special instructions, equipment, or arrangements for this branch')
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

                Tables\Columns\TextColumn::make('pivot.custom_price')
                    ->label('Branch Price')
                    ->money('KES')
                    ->placeholder('Default price'),

                Tables\Columns\IconColumn::make('pivot.is_available')
                    ->label('Available')
                    ->boolean(),

                Tables\Columns\TextColumn::make('this_month_bookings')
                    ->label('This Month')
                    ->getStateUsing(function ($record) {
                        return \App\Models\Booking::where('branch_id', $record->id)
                            ->where('service_id', $this->ownerRecord->id)
                            ->whereMonth('appointment_date', now()->month)
                            ->count();
                    })
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'maintenance' => 'warning',
                        default => 'gray'
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'maintenance' => 'Maintenance',
                    ]),
                    
                Tables\Filters\TernaryFilter::make('is_available')
                    ->attribute('pivot.is_available'),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\TextInput::make('custom_price')
                            ->label('Branch-Specific Price')
                            ->numeric()
                            ->prefix('KES'),
                        Forms\Components\Toggle::make('is_available')
                            ->label('Available')
                            ->default(true),
                        Forms\Components\Textarea::make('branch_notes')
                            ->label('Notes'),
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