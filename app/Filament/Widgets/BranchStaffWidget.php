<?php

namespace App\Filament\Widgets;

use App\Models\Staff;
use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Facades\Filament;

class BranchStaffWidget extends BaseWidget
{
    protected static ?string $heading = 'Staff Performance Today';
    
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();
        
        return $table
            ->query(
                Staff::whereHas('branches', function($query) use ($tenant) {
                    $query->where('branch_id', $tenant?->id);
                })->where('status', 'active')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('specialties')
                    ->label('Specialties')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->wrap(),

                Tables\Columns\TextColumn::make('today_bookings')
                    ->label('Today\'s Bookings')
                    ->getStateUsing(function (Staff $record) use ($tenant) {
                        return Booking::where('branch_id', $tenant?->id)
                            ->where('staff_id', $record->id)
                            ->whereDate('appointment_date', today())
                            ->count();
                    })
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('today_revenue')
                    ->label('Today\'s Revenue')
                    ->getStateUsing(function (Staff $record) use ($tenant) {
                        return Booking::where('branch_id', $tenant?->id)
                            ->where('staff_id', $record->id)
                            ->whereDate('appointment_date', today())
                            ->where('payment_status', 'completed')
                            ->sum('total_amount');
                    })
                    ->money('KES')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status_today')
                    ->label('Current Status')
                    ->getStateUsing(function (Staff $record) use ($tenant) {
                        $currentBooking = Booking::where('branch_id', $tenant?->id)
                            ->where('staff_id', $record->id)
                            ->whereDate('appointment_date', today())
                            ->where('start_time', '<=', now()->format('H:i:s'))
                            ->where('end_time', '>=', now()->format('H:i:s'))
                            ->where('status', 'in_progress')
                            ->first();

                        if ($currentBooking) {
                            return 'In Service';
                        }

                        $nextBooking = Booking::where('branch_id', $tenant?->id)
                            ->where('staff_id', $record->id)
                            ->whereDate('appointment_date', today())
                            ->where('start_time', '>', now()->format('H:i:s'))
                            ->orderBy('start_time')
                            ->first();

                        if ($nextBooking) {
                            return 'Next: ' . $nextBooking->start_time;
                        }

                        return 'Available';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state === 'In Service' => 'danger',
                        str_starts_with($state, 'Next:') => 'warning',
                        $state === 'Available' => 'success',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('utilization_today')
                    ->label('Utilization')
                    ->getStateUsing(function (Staff $record) use ($tenant) {
                        $totalMinutesToday = Booking::where('branch_id', $tenant?->id)
                            ->where('staff_id', $record->id)
                            ->whereDate('appointment_date', today())
                            ->join('services', 'bookings.service_id', '=', 'services.id')
                            ->sum('services.duration_minutes');

                        $workingMinutes = 8 * 60; // Assuming 8-hour workday
                        $utilization = $workingMinutes > 0 ? round(($totalMinutesToday / $workingMinutes) * 100) : 0;
                        
                        return $utilization . '%';
                    })
                    ->badge()
                    ->color(function (string $state): string {
                        $percentage = (int) str_replace('%', '', $state);
                        return match (true) {
                            $percentage >= 80 => 'success',
                            $percentage >= 60 => 'warning',
                            default => 'danger'
                        };
                    }),

                Tables\Columns\TextColumn::make('hourly_rate')
                    ->money('KES')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('view_schedule')
                    ->label('Schedule')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->url(fn (Staff $record) => route('filament.director.resources.bookings.index', [
                        'tenant' => Filament::getTenant()?->id,
                        'tableFilters[staff_id][values][0]' => $record->id,
                        'tableFilters[today][isActive]' => true,
                    ])),
            ])
            ->emptyStateHeading('No staff assigned to this branch')
            ->emptyStateDescription('Add staff members to this branch to see their performance metrics.')
            ->emptyStateIcon('heroicon-o-users');
    }
}