<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Branch;
use App\Models\Service;
use App\Models\Staff;

class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Booking History';

    protected static ?string $label = 'Booking';

    protected static ?string $pluralLabel = 'Bookings';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Booking Details')
                    ->schema([
                        Forms\Components\Select::make('branch_id')
                            ->label('Branch')
                            ->options(Branch::active()->pluck('name', 'id'))
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('service_id')
                            ->label('Service')
                            ->options(Service::active()->pluck('name', 'id'))
                            ->required()
                            ->native(false)
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => 
                                $set('staff_id', null)
                            ),
                        Forms\Components\Select::make('staff_id')
                            ->label('Staff Member')
                            ->options(function (callable $get) {
                                $serviceId = $get('service_id');
                                if (!$serviceId) return [];
                                return Staff::whereHas('services', function ($query) use ($serviceId) {
                                    $query->where('service_id', $serviceId);
                                })->active()->pluck('name', 'id');
                            })
                            ->required()
                            ->native(false),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Appointment Schedule')
                    ->schema([
                        Forms\Components\DatePicker::make('appointment_date')
                            ->required()
                            ->native(false)
                            ->minDate(now()),
                        Forms\Components\TimePicker::make('start_time')
                            ->required()
                            ->seconds(false),
                        Forms\Components\TimePicker::make('end_time')
                            ->required()
                            ->seconds(false),
                        Forms\Components\TextInput::make('total_amount')
                            ->numeric()
                            ->prefix('KES')
                            ->step(0.01)
                            ->required(),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Status & Payment')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                                'no_show' => 'No Show'
                            ])
                            ->default('pending')
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('payment_status')
                            ->options([
                                'pending' => 'Pending',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'refunded' => 'Refunded'
                            ])
                            ->default('pending')
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'cash' => 'Cash',
                                'mpesa' => 'M-Pesa',
                                'card' => 'Card',
                                'bank_transfer' => 'Bank Transfer'
                            ])
                            ->native(false),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->placeholder('Any special notes or requirements...'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('booking_reference')
            ->columns([
                Tables\Columns\TextColumn::make('booking_reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service')
                    ->searchable(),
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Staff')
                    ->searchable(),
                Tables\Columns\TextColumn::make('appointment_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('formatted_time_slot')
                    ->label('Time')
                    ->getStateUsing(fn ($record) => 
                        $record->start_time->format('H:i') . ' - ' . $record->end_time->format('H:i')
                    ),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('KES')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'in_progress' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'no_show' => 'gray',
                        default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'no_show' => 'No Show'
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded'
                    ]),
                Tables\Filters\Filter::make('upcoming')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('appointment_date', '>=', now()->toDateString())
                              ->whereIn('status', ['pending', 'confirmed'])
                    )
                    ->label('Upcoming Appointments'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('New Booking'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->canBeCancelled())
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => 'cancelled',
                            'cancellation_reason' => $data['cancellation_reason'],
                            'cancelled_at' => now(),
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('appointment_date', 'desc')
            ->groups([
                Tables\Grouping\Group::make('status')
                    ->label('Status')
                    ->collapsible(),
                Tables\Grouping\Group::make('appointment_date')
                    ->label('Date')
                    ->collapsible(),
            ]);
    }
}