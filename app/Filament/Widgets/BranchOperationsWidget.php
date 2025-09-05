<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Staff;
use App\Models\Service;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Facades\Filament;

class BranchOperationsWidget extends BaseWidget
{
    protected static ?string $heading = 'Today\'s Operations Overview';
    
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();
        
        return $table
            ->query(
                Booking::where('branch_id', $tenant?->id)
                    ->whereDate('appointment_date', today())
                    ->with(['client', 'service', 'staff'])
                    ->orderBy('start_time')
            )
            ->columns([
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Time')
                    ->formatStateUsing(fn ($record) => $record->start_time . ' - ' . $record->end_time)
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('client.first_name')
                    ->label('Client')
                    ->formatStateUsing(fn ($record) => $record->client->first_name . ' ' . $record->client->last_name)
                    ->searchable(['first_name', 'last_name']),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Staff')
                    ->placeholder('Unassigned')
                    ->color('warning'),

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
                    ->weight('bold'),
            ])
            ->actions([
                Tables\Actions\Action::make('quick_confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => $record->status === 'pending')
                    ->action(function (Booking $record) {
                        $record->update(['status' => 'confirmed']);
                    }),
                    
                Tables\Actions\Action::make('start_service')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->visible(fn (Booking $record): bool => $record->status === 'confirmed')
                    ->action(function (Booking $record) {
                        $record->update([
                            'status' => 'in_progress',
                            'service_started_at' => now()
                        ]);
                    }),
                    
                Tables\Actions\Action::make('complete_service')
                    ->label('Complete')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => $record->status === 'in_progress')
                    ->action(function (Booking $record) {
                        $record->update([
                            'status' => 'completed',
                            'service_completed_at' => now()
                        ]);
                    }),
            ])
            ->emptyStateHeading('No appointments scheduled for today')
            ->emptyStateDescription('All clear! No bookings scheduled for today at this branch.')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }
}