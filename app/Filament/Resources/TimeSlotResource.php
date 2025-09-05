<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TimeSlotResource\Pages;
use App\Models\TimeSlot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Support\Enums\FontWeight;

class TimeSlotResource extends Resource
{
    protected static ?string $model = TimeSlot::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    
    protected static ?string $navigationGroup = 'Staff & Scheduling';
    
    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Time Slots';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Time Slot Configuration')
                    ->schema([
                        Forms\Components\Hidden::make('branch_id')
                            ->default(fn (): ?int => \Filament\Facades\Filament::getTenant()?->id),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TimePicker::make('start_time')
                                    ->label('Start Time')
                                    ->required()
                                    ->seconds(false)
                                    ->minutesStep(15),

                                Forms\Components\TimePicker::make('end_time')
                                    ->label('End Time')
                                    ->required()
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->after('start_time'),
                            ]),

                        Forms\Components\Select::make('day_of_week')
                            ->label('Day of Week')
                            ->options(TimeSlot::getDaysOfWeek())
                            ->required()
                            ->native(false),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('max_bookings')
                                    ->label('Max Concurrent Bookings')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->required()
                                    ->helperText('How many bookings can be scheduled at the same time'),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Only active time slots will be available for booking'),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->placeholder('Optional notes about this time slot...')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('day_of_week')
                    ->label('Day')
                    ->formatStateUsing(fn (string $state): string => 
                        TimeSlot::getDaysOfWeek()[$state] ?? ucfirst($state)
                    )
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start Time')
                    ->time('g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('End Time')
                    ->time('g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_time_range')
                    ->label('Time Range')
                    ->weight(FontWeight::Medium)
                    ->color('info'),

                Tables\Columns\TextColumn::make('max_bookings')
                    ->label('Max Bookings')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('current_usage')
                    ->label('Usage Today')
                    ->getStateUsing(function ($record): string {
                        $today = now()->format('Y-m-d');
                        $dayOfWeek = strtolower(now()->format('l'));
                        
                        // Only show usage for today's slots
                        if ($record->day_of_week !== $dayOfWeek) {
                            return 'N/A';
                        }
                        
                        $bookingsCount = \App\Models\Booking::where('appointment_date', $today)
                            ->where('branch_id', $record->branch_id)
                            ->where('status', '!=', 'cancelled')
                            ->where(function($query) use ($record, $today) {
                                $slotStart = \Carbon\Carbon::parse($today . ' ' . $record->start_time->format('H:i'));
                                $slotEnd = \Carbon\Carbon::parse($today . ' ' . $record->end_time->format('H:i'));
                                
                                $query->whereRaw('? BETWEEN TIME(start_time) AND TIME(end_time)', [$record->start_time->format('H:i:s')])
                                      ->orWhereRaw('? BETWEEN TIME(start_time) AND TIME(end_time)', [$record->end_time->format('H:i:s')])
                                      ->orWhereRaw('TIME(start_time) BETWEEN ? AND ?', [$record->start_time->format('H:i:s'), $record->end_time->format('H:i:s')]);
                            })
                            ->count();
                            
                        return "{$bookingsCount}/{$record->max_bookings}";
                    })
                    ->badge()
                    ->color(function (string $state): string {
                        if ($state === 'N/A') return 'gray';
                        $parts = explode('/', $state);
                        if (count($parts) !== 2) return 'gray';
                        $current = (int) $parts[0];
                        $max = (int) $parts[1];
                        $percentage = $max > 0 ? ($current / $max) * 100 : 0;
                        return $percentage >= 100 ? 'danger' : ($percentage >= 75 ? 'warning' : 'success');
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('day_of_week')
                    ->label('Day of Week')
                    ->options(TimeSlot::getDaysOfWeek())
                    ->multiple(),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Active',
                        false => 'Inactive',
                    ])
                    ->default(true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(fn ($record) => $record->update(['is_active' => true]));
                        }),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            $records->each(fn ($record) => $record->update(['is_active' => false]));
                        }),
                    Tables\Actions\BulkAction::make('duplicate_to_all_days')
                        ->label('Copy to All Days')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('info')
                        ->action(function ($records) {
                            $tenant = \Filament\Facades\Filament::getTenant();
                            if (!$tenant) return;
                            
                            $allDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                            
                            foreach ($records as $record) {
                                foreach ($allDays as $day) {
                                    if ($day === $record->day_of_week) continue;
                                    
                                    \App\Models\TimeSlot::updateOrCreate([
                                        'branch_id' => $tenant->id,
                                        'start_time' => $record->start_time,
                                        'end_time' => $record->end_time,
                                        'day_of_week' => $day,
                                    ], [
                                        'is_active' => $record->is_active,
                                        'max_bookings' => $record->max_bookings,
                                        'notes' => $record->notes
                                    ]);
                                }
                            }
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Copy Time Slots to All Days')
                        ->modalDescription('This will copy the selected time slots to all other days of the week.')
                        ->modalSubmitActionLabel('Copy to All Days'),
                ]),
            ])
            ->defaultSort('day_of_week')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTimeSlots::route('/'),
            'create' => Pages\CreateTimeSlot::route('/create'),
            'edit' => Pages\EditTimeSlot::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        return parent::getEloquentQuery()
            ->when($tenant, fn (Builder $query) => $query->where('branch_id', $tenant->id));
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if (!$tenant) return null;
            
            $activeCount = static::getModel()::where('branch_id', $tenant->id)
                ->where('is_active', true)
                ->count();
            return $activeCount > 0 ? (string) $activeCount : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
