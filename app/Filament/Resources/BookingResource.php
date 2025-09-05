<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Filament\Resources\BookingResource\RelationManagers;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Carbon\Carbon;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    
    protected static ?string $navigationGroup = 'Business Operations';
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $recordTitleAttribute = 'booking_reference';
    
    // Scope bookings to only show those for the current branch (tenant)
    public static function getEloquentQuery(): Builder
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        return parent::getEloquentQuery()
            ->where('branch_id', $tenant->id)
            ->with(['client', 'service', 'staff', 'payment']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Client Information')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->label('Client')
                            ->relationship('client', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name . ' ' . $record->last_name . ' (' . $record->email . ')')
                            ->searchable(['first_name', 'last_name', 'email', 'phone'])
                            ->required()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('first_name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('last_name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('email')
                                            ->email()
                                            ->unique(User::class, 'email')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('phone')
                                            ->tel()
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\Select::make('gender')
                                            ->options([
                                                'male' => 'Male',
                                                'female' => 'Female',
                                                'other' => 'Other',
                                                'prefer_not_to_say' => 'Prefer not to say'
                                            ])
                                            ->native(false),
                                        Forms\Components\DatePicker::make('date_of_birth')
                                            ->maxDate(now()),
                                        Forms\Components\Textarea::make('allergies')
                                            ->columnSpanFull()
                                            ->rows(2),
                                    ])
                            ])
                    ])->columns(1),
                    
                Forms\Components\Section::make('Service Details')
                    ->schema([
                        Forms\Components\Select::make('service_id')
                            ->label('Service')
                            ->options(function () {
                                $tenant = \Filament\Facades\Filament::getTenant();
                                return Service::whereHas('branches', function (Builder $query) use ($tenant) {
                                    $query->where('branch_id', $tenant->id);
                                })->pluck('name', 'id');
                            })
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $service = Service::find($state);
                                    if ($service) {
                                        $set('total_amount', $service->price);
                                        // Calculate end time based on service duration
                                        $startTime = request()->get('start_time');
                                        if ($startTime) {
                                            $endTime = Carbon::parse($startTime)->addMinutes($service->duration ?? 60);
                                            $set('end_time', $endTime->format('H:i'));
                                        }
                                    }
                                }
                            })
                            ->native(false),
                            
                        Forms\Components\Select::make('staff_id')
                            ->label('Staff Member')
                            ->options(function (Forms\Get $get) {
                                $tenant = \Filament\Facades\Filament::getTenant();
                                $serviceId = $get('service_id');
                                
                                $query = Staff::whereHas('branches', function (Builder $query) use ($tenant) {
                                    $query->where('branch_id', $tenant->id);
                                });
                                
                                if ($serviceId) {
                                    $query->whereHas('services', function (Builder $query) use ($serviceId) {
                                        $query->where('service_id', $serviceId);
                                    });
                                }
                                
                                return $query->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->native(false),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Appointment Schedule')
                    ->schema([
                        Forms\Components\DatePicker::make('appointment_date')
                            ->required()
                            ->minDate(now())
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Clear time fields when date changes
                                $set('start_time', null);
                                $set('end_time', null);
                            }),
                            
                        Forms\Components\TimePicker::make('start_time')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, Forms\Get $get) {
                                if ($state && $get('service_id')) {
                                    $service = Service::find($get('service_id'));
                                    if ($service) {
                                        $endTime = Carbon::parse($state)->addMinutes($service->duration ?? 60);
                                        $set('end_time', $endTime->format('H:i'));
                                    }
                                }
                            }),
                            
                        Forms\Components\TimePicker::make('end_time')
                            ->required()
                            ->afterOrEqual('start_time'),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Booking Details')
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
                            
                        Forms\Components\TextInput::make('total_amount')
                            ->numeric()
                            ->prefix('KES')
                            ->step(0.01)
                            ->required(),
                            
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                            
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->rows(2)
                            ->columnSpanFull()
                            ->hidden(fn (Forms\Get $get): bool => $get('status') !== 'cancelled'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking_reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('client.first_name')
                    ->label('Client')
                    ->formatStateUsing(fn ($record) => $record->client->first_name . ' ' . $record->client->last_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('service.name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('staff.name')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Unassigned'),
                    
                Tables\Columns\TextColumn::make('appointment_date')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Time')
                    ->formatStateUsing(fn ($record) => $record->start_time . ' - ' . $record->end_time)
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
                    ->label('Payment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray'
                    }),
                    
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('KES')
                    ->sortable(),

                Tables\Columns\TextColumn::make('service_started_at')
                    ->label('Service Started')
                    ->dateTime('M j, g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not started'),

                Tables\Columns\TextColumn::make('service_completed_at')
                    ->label('Service Completed')
                    ->dateTime('M j, g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not completed'),

                Tables\Columns\TextColumn::make('service_duration')
                    ->label('Duration')
                    ->getStateUsing(function (Booking $record): ?string {
                        $duration = $record->getServiceDuration();
                        return $duration ? "{$duration} min" : null;
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('In progress'),
                    
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
                    ])
                    ->multiple(),
                    
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded'
                    ])
                    ->multiple(),
                    
                Tables\Filters\SelectFilter::make('service_id')
                    ->label('Service')
                    ->relationship('service', 'name')
                    ->multiple()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('staff_id')
                    ->label('Staff')
                    ->relationship('staff', 'name')
                    ->multiple()
                    ->preload(),
                    
                Tables\Filters\Filter::make('appointment_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('appointment_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('appointment_date', '<=', $date),
                            );
                    }),
                    
                Tables\Filters\Filter::make('today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('appointment_date', today()))
                    ->label('Today\'s Appointments'),
                    
                Tables\Filters\Filter::make('upcoming')
                    ->query(fn (Builder $query): Builder => $query->where('appointment_date', '>=', today()))
                    ->label('Upcoming'),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm_booking')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->visible(fn (Booking $record): bool => $record->canBeConfirmed())
                    ->requiresConfirmation()
                    ->modalDescription(fn (Booking $record) => $record->getPaymentStatusMessage())
                    ->action(function (Booking $record) {
                        $record->updateStatusWithPayment('confirmed');
                    }),
                    
                Tables\Actions\Action::make('record_payment')
                    ->label('Record Payment')
                    ->icon('heroicon-o-credit-card')
                    ->color('warning')
                    ->visible(fn (Booking $record): bool => !$record->hasValidPayment())
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->label('Amount')
                                    ->numeric()
                                    ->prefix('KES')
                                    ->step(0.01)
                                    ->required(),
                                    
                                Forms\Components\Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'cash' => 'Cash',
                                        'mpesa' => 'M-Pesa',
                                        'card' => 'Credit/Debit Card',
                                        'bank_transfer' => 'Bank Transfer'
                                    ])
                                    ->required()
                                    ->reactive()
                                    ->native(false),
                            ]),
                            
                        Forms\Components\TextInput::make('transaction_reference')
                            ->label('Transaction Reference')
                            ->maxLength(100)
                            ->helperText('For M-Pesa or card payments')
                            ->visible(fn (Forms\Get $get) => in_array($get('payment_method'), ['mpesa', 'card'])),
                            
                        Forms\Components\Select::make('payment_status')
                            ->label('Payment Status')
                            ->options([
                                'completed' => 'Completed',
                                'pending' => 'Pending',
                                'failed' => 'Failed'
                            ])
                            ->default('completed')
                            ->required()
                            ->native(false),
                    ])
                    ->modalHeading('Record Payment')
                    ->modalSubmitActionLabel('Record Payment')
                    ->fillForm(fn (Booking $record): array => [
                        'amount' => $record->total_amount,
                        'payment_method' => $record->payment_method ?? 'cash',
                    ])
                    ->action(function (Booking $record, array $data) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        
                        // Create or update payment record
                        $payment = \App\Models\Payment::updateOrCreate(
                            ['booking_id' => $record->id],
                            [
                                'branch_id' => $tenant->id,
                                'amount' => $data['amount'],
                                'payment_method' => $data['payment_method'],
                                'transaction_reference' => $data['transaction_reference'] ?? null,
                                'status' => $data['payment_status'],
                                'processed_at' => $data['payment_status'] === 'completed' ? now() : null,
                            ]
                        );

                        // Update booking payment information
                        $record->update([
                            'payment_status' => $data['payment_status'],
                            'payment_method' => $data['payment_method'],
                            'total_amount' => $data['amount'],
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Payment Recorded')
                            ->body($data['payment_status'] === 'completed' ? 
                                'Payment confirmed successfully.' : 
                                'Payment recorded as ' . $data['payment_status'] . '.')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('start_service')
                    ->label('Start Service')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => $record->canBeStarted())
                    ->requiresConfirmation()
                    ->modalHeading('Start Service')
                    ->modalDescription(fn (Booking $record) => 
                        "Start the service for {$record->service->name} with {$record->staff->name}?"
                    )
                    ->modalSubmitActionLabel('Start Service')
                    ->action(function (Booking $record) {
                        $success = $record->startService();
                        if ($success) {
                            \Filament\Notifications\Notification::make()
                                ->title('Service Started')
                                ->body("Service has been started at " . now()->format('g:i A'))
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Failed to Start Service')
                                ->body('Service could not be started. Please check the booking status.')
                                ->danger()
                                ->send();
                        }
                    }),
                    
                Tables\Actions\Action::make('complete_service')
                    ->label('Complete Service')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => 
                        ($record->status === 'confirmed' && $record->appointment_date->toDateString() === now()->toDateString()) ||
                        $record->canBeCompleted()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Complete Service')
                    ->modalDescription(function (Booking $record) {
                        $paymentStatus = $record->hasValidPayment() ? 
                            "✅ Payment confirmed" : 
                            "⚠️ Payment required";
                        
                        $statusInfo = $record->status === 'confirmed' ? 
                            "This will mark the service as completed directly." :
                            "This will mark the in-progress service as completed.";
                        
                        return "Mark the service for {$record->service->name} as completed?\n\nPayment Status: {$paymentStatus}\n\n{$statusInfo}";
                    })
                    ->modalSubmitActionLabel('Complete Service')
                    ->action(function (Booking $record) {
                        // Validate payment before completion
                        if (!$record->hasValidPayment()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Payment Required')
                                ->body('Service cannot be completed without confirmed payment.')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // If confirmed, complete directly (skip in_progress)
                        if ($record->status === 'confirmed') {
                            $record->update([
                                'status' => 'completed',
                                'service_started_at' => now()->subMinutes(60), // Assume 1 hour service
                                'service_completed_at' => now(),
                            ]);
                            $success = true;
                        } else {
                            // Normal completion for in_progress bookings
                            $success = $record->completeService();
                        }
                        
                        if ($success) {
                            $duration = $record->getServiceDuration();
                            $durationText = $duration ? " (Duration: {$duration} minutes)" : "";
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Service Completed')
                                ->body("Service completed at " . now()->format('g:i A') . $durationText)
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Failed to Complete Service')
                                ->body('Service could not be completed. Please ensure payment is confirmed.')
                                ->danger()
                                ->send();
                        }
                    }),
                    
                Tables\Actions\Action::make('cancel_booking')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Booking $record): bool => $record->canBeCancelled())
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data) {
                        $record->update([
                            'status' => 'cancelled',
                            'cancellation_reason' => $data['cancellation_reason'],
                            'cancelled_at' => now(),
                        ]);
                    }),
                    
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('confirm_bookings')
                        ->label('Confirm Selected')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function (Booking $booking) {
                                if ($booking->status === 'pending') {
                                    $booking->updateStatusWithPayment('confirmed');
                                }
                            });
                        }),
                        
                    Tables\Actions\BulkAction::make('cancel_bookings')
                        ->label('Cancel Selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->form([
                            Forms\Components\Textarea::make('cancellation_reason')
                                ->label('Cancellation Reason')
                                ->required()
                                ->rows(3),
                        ])
                        ->requiresConfirmation()
                        ->action(function ($records, array $data) {
                            $records->each(function (Booking $booking) use ($data) {
                                if ($booking->canBeCancelled()) {
                                    $booking->update([
                                        'status' => 'cancelled',
                                        'cancellation_reason' => $data['cancellation_reason'],
                                        'cancelled_at' => now(),
                                    ]);
                                }
                            });
                        }),
                ]),
            ])
            ->defaultSort('appointment_date', 'desc')
            ->groups([
                Tables\Grouping\Group::make('appointment_date')
                    ->label('Date')
                    ->date()
                    ->collapsible(),
                Tables\Grouping\Group::make('status')
                    ->label('Status')
                    ->collapsible(),
                Tables\Grouping\Group::make('staff.name')
                    ->label('Staff')
                    ->collapsible(),
            ])
            ->recordUrl(
                fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record])
            );
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Booking Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('booking_reference')
                            ->label('Reference')
                            ->copyable()
                            ->weight(FontWeight::Bold),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                                'no_show' => 'No Show',
                                default => ucfirst($state)
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'confirmed' => 'info',
                                'in_progress' => 'primary',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                'no_show' => 'gray',
                                default => 'gray'
                            }),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Booked On')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('confirmed_at')
                            ->label('Confirmed At')
                            ->dateTime()
                            ->placeholder('Not confirmed'),
                        Infolists\Components\TextEntry::make('service_started_at')
                            ->label('Service Started At')
                            ->dateTime()
                            ->placeholder('Not started')
                            ->visible(fn ($record): bool => $record->service_started_at !== null || $record->status === 'in_progress' || $record->status === 'completed'),
                        Infolists\Components\TextEntry::make('service_completed_at')
                            ->label('Service Completed At')
                            ->dateTime()
                            ->placeholder('Not completed')
                            ->visible(fn ($record): bool => $record->service_completed_at !== null || $record->status === 'completed'),
                        Infolists\Components\TextEntry::make('service_duration')
                            ->label('Service Duration')
                            ->getStateUsing(function ($record): ?string {
                                $duration = $record->getServiceDuration();
                                return $duration ? "{$duration} minutes" : null;
                            })
                            ->placeholder('In progress')
                            ->visible(fn ($record): bool => $record->status === 'completed' || $record->getServiceDuration() !== null)
                            ->badge()
                            ->color('success'),
                    ])->columns(2),
                    
                Infolists\Components\Section::make('Client Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('client.first_name')
                            ->label('Name')
                            ->formatStateUsing(fn ($record) => $record->client->first_name . ' ' . $record->client->last_name),
                        Infolists\Components\TextEntry::make('client.email')
                            ->label('Email')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('client.phone')
                            ->label('Phone')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('client.allergies')
                            ->label('Allergies')
                            ->placeholder('None specified'),
                    ])->columns(2),
                    
                Infolists\Components\Section::make('Service Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('service.name')
                            ->label('Service'),
                        Infolists\Components\TextEntry::make('staff.name')
                            ->label('Staff')
                            ->placeholder('Unassigned'),
                        Infolists\Components\TextEntry::make('appointment_date')
                            ->label('Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('start_time')
                            ->label('Time')
                            ->formatStateUsing(fn ($record) => $record->start_time . ' - ' . $record->end_time),
                    ])->columns(2),
                    
                Infolists\Components\Section::make('Payment Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_amount')
                            ->label('Amount')
                            ->money('KES'),
                        Infolists\Components\TextEntry::make('payment_status')
                            ->label('Payment Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'completed' => 'success',
                                'failed' => 'danger',
                                'refunded' => 'gray',
                                default => 'gray'
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'pending' => '⏳ Pending Payment',
                                'completed' => '✅ Payment Confirmed',
                                'failed' => '❌ Payment Failed',
                                'refunded' => '↩️ Refunded',
                                default => ucfirst($state)
                            }),
                        Infolists\Components\TextEntry::make('payment_validation')
                            ->label('Payment Validation')
                            ->getStateUsing(fn ($record) => $record->hasValidPayment() ? 'Valid' : 'Required')
                            ->badge()
                            ->color(fn ($record) => $record->hasValidPayment() ? 'success' : 'warning')
                            ->formatStateUsing(fn ($record, $state) => $record->hasValidPayment() ? '✅ Payment Validated' : '⚠️ Payment Required'),
                        Infolists\Components\TextEntry::make('payment_method')
                            ->label('Payment Method')
                            ->formatStateUsing(fn (?string $state): string => $state ? ucfirst(str_replace('_', ' ', $state)) : 'Not specified'),
                        Infolists\Components\TextEntry::make('mpesa_transaction_id')
                            ->label('M-Pesa Transaction ID')
                            ->placeholder('N/A')
                            ->copyable()
                            ->visible(fn ($record) => $record->payment_method === 'mpesa'),
                        Infolists\Components\TextEntry::make('service_actions_available')
                            ->label('Available Actions')
                            ->getStateUsing(function ($record) {
                                $actions = [];
                                if ($record->status === 'pending' && $record->hasValidPayment()) {
                                    $actions[] = '📋 Can be confirmed';
                                }
                                if ($record->canBeStarted()) {
                                    $actions[] = '▶️ Can start service';
                                }
                                if ($record->canBeCompleted()) {
                                    $actions[] = '✅ Can complete service';
                                }
                                if (empty($actions)) {
                                    if (!$record->hasValidPayment()) {
                                        $actions[] = '⚠️ Payment required';
                                    } else {
                                        $actions[] = '✓ No actions needed';
                                    }
                                }
                                return implode("\n", $actions);
                            })
                            ->columnSpanFull()
                            ->badge()
                            ->color(fn ($record) => $record->hasValidPayment() ? 'success' : 'warning'),
                    ])->columns(2),
                    
                Infolists\Components\Section::make('Additional Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->placeholder('N/A')
                            ->visible(fn ($record) => $record->status === 'cancelled')
                            ->columnSpanFull(),
                    ])->columns(1),
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
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'view' => Pages\ViewBooking::route('/{record}'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
            'calendar' => Pages\CalendarBookings::route('/calendar'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            
            if (!$tenant || !auth()->check()) {
                return null;
            }
            
            $count = static::getModel()::where('branch_id', $tenant->id)
                ->whereDate('appointment_date', today())
                ->whereIn('status', ['pending', 'confirmed'])
                ->count();
                
            return $count > 0 ? (string) $count : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}