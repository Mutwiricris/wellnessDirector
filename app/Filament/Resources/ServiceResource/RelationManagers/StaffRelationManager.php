<?php

namespace App\Filament\Resources\ServiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StaffRelationManager extends RelationManager
{
    protected static string $relationship = 'staff';

    protected static ?string $title = 'Qualified Staff';

    protected static ?string $label = 'Staff Member';

    protected static ?string $pluralLabel = 'Staff Members';

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

                        Forms\Components\TextInput::make('email')
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

                        Forms\Components\DatePicker::make('certification_date')
                            ->label('Certification Date'),

                        Forms\Components\Textarea::make('training_notes')
                            ->label('Training & Certification Notes')
                            ->placeholder('Details about training, certifications, and qualifications for this service')
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
                    ->label('Staff Name')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->icon('heroicon-o-phone'),

                Tables\Columns\TextColumn::make('specialties')
                    ->label('Specialties')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->limit(30)
                    ->tooltip(fn ($record) => is_array($record->specialties) ? implode(', ', $record->specialties) : $record->specialties),

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
                        return \App\Models\Booking::where('staff_id', $record->id)
                            ->where('service_id', $this->ownerRecord->id)
                            ->whereMonth('appointment_date', now()->month)
                            ->count();
                    })
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('hourly_rate')
                    ->money('KES'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'on_leave' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray'
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'on_leave' => 'On Leave',
                        'suspended' => 'Suspended',
                    ]),
                    
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
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('status', 'active'))
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
                        Forms\Components\Textarea::make('training_notes')
                            ->label('Training Notes'),
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