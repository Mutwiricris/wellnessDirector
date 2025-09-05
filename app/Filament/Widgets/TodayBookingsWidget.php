<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Facades\Filament;
use Filament\Support\Enums\FontWeight;

class TodayBookingsWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    
    protected static ?string $heading = 'Today\'s Appointments';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->where('branch_id', Filament::getTenant()?->id)
                    ->whereDate('appointment_date', today())
                    ->with(['client', 'service', 'staff'])
                    ->orderBy('start_time')
            )
            ->columns([
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Time')
                    ->formatStateUsing(fn ($record) => $record->start_time . ' - ' . $record->end_time)
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                Tables\Columns\TextColumn::make('booking_reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('client.first_name')
                    ->label('Client')
                    ->formatStateUsing(fn ($record) => $record->client->first_name . ' ' . $record->client->last_name)
                    ->searchable(['first_name', 'last_name'])
                    ->weight(FontWeight::Medium),
                    
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service')
                    ->searchable()
                    ->wrap(),
                    
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Staff')
                    ->searchable()
                    ->placeholder('Unassigned'),
                    
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
                    ->label('Amount')
                    ->money('KES')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('start_service')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->size('sm')
                    ->visible(fn (Booking $record): bool => $record->canBeStarted())
                    ->action(function (Booking $record) {
                        $record->startService();
                        $this->dispatch('$refresh');
                    }),
                    
                Tables\Actions\Action::make('complete_service')
                    ->label('Complete')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->size('sm')
                    ->visible(fn (Booking $record): bool => $record->canBeCompleted() || 
                        ($record->status === 'confirmed' && $record->appointment_date->toDateString() === now()->toDateString()))
                    ->action(function (Booking $record) {
                        if ($record->status === 'confirmed') {
                            $record->update([
                                'status' => 'completed',
                                'service_started_at' => now()->subHour(),
                                'service_completed_at' => now(),
                            ]);
                        } else {
                            $record->completeService();
                        }
                        $this->dispatch('$refresh');
                    }),
                    
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->size('sm')
                    ->url(fn (Booking $record): string => 
                        \App\Filament\Resources\BookingResource::getUrl('view', ['record' => $record])
                    ),
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
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_booking')
                    ->label('New Booking')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(\App\Filament\Resources\BookingResource::getUrl('create')),
                    
                Tables\Actions\Action::make('view_calendar')
                    ->label('Calendar')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->url(\App\Filament\Resources\BookingResource::getUrl('calendar')),
            ])
            ->emptyStateHeading('No appointments today')
            ->emptyStateDescription('There are no bookings scheduled for today.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->poll('30s');
    }
}