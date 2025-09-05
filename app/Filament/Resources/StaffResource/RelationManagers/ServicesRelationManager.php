<?php

namespace App\Filament\Resources\StaffResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    protected static ?string $title = 'Service Specializations';

    protected static ?string $label = 'Service';

    protected static ?string $pluralLabel = 'Services';

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

                        Forms\Components\TextInput::make('category.name')
                            ->label('Category')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Select::make('proficiency_level')
                            ->label('Proficiency Level')
                            ->options([
                                'beginner' => 'Beginner',
                                'intermediate' => 'Intermediate',
                                'advanced' => 'Advanced',
                                'expert' => 'Expert',
                            ])
                            ->required()
                            ->default('intermediate'),

                        Forms\Components\TextInput::make('certification_date')
                            ->label('Certification Date')
                            ->type('date'),

                        Forms\Components\Textarea::make('specialization_notes')
                            ->label('Specialization Notes')
                            ->placeholder('Training details, certifications, special techniques')
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
                    ->label('Service Name')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state) => $state . ' min'),

                Tables\Columns\TextColumn::make('price')
                    ->money('KES'),

                Tables\Columns\TextColumn::make('pivot.proficiency_level')
                    ->label('Proficiency')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'expert' => 'success',
                        'advanced' => 'info',
                        'intermediate' => 'warning',
                        'beginner' => 'gray',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('this_month_bookings')
                    ->label('This Month')
                    ->getStateUsing(function ($record) {
                        return \App\Models\Booking::where('service_id', $record->id)
                            ->where('staff_id', $this->ownerRecord->id)
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
                        default => 'gray'
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
                    
                Tables\Filters\SelectFilter::make('proficiency_level')
                    ->options([
                        'beginner' => 'Beginner',
                        'intermediate' => 'Intermediate',
                        'advanced' => 'Advanced',
                        'expert' => 'Expert',
                    ])
                    ->attribute('pivot.proficiency_level'),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Select::make('proficiency_level')
                            ->label('Proficiency Level')
                            ->options([
                                'beginner' => 'Beginner',
                                'intermediate' => 'Intermediate',
                                'advanced' => 'Advanced',
                                'expert' => 'Expert',
                            ])
                            ->required()
                            ->default('intermediate'),
                        Forms\Components\Textarea::make('specialization_notes')
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