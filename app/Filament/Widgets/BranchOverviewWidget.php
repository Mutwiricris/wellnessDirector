<?php

namespace App\Filament\Widgets;

use App\Models\Branch;
use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class BranchOverviewWidget extends BaseWidget
{
    protected static ?string $heading = 'Branch Performance Overview';
    
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Branch::withoutGlobalScopes()->active()->withCount([
                    'bookings',
                    'bookings as today_bookings_count' => function ($query) {
                        $query->whereDate('appointment_date', today());
                    },
                    'bookings as completed_bookings_count' => function ($query) {
                        $query->where('status', 'completed');
                    }
                ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Branch Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('address')
                    ->label('Address')
                    ->limit(50),
                    
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->icon('heroicon-m-phone'),
                    
                Tables\Columns\TextColumn::make('today_bookings_count')
                    ->label('Today\'s Bookings')
                    ->badge()
                    ->color('warning'),
                    
                Tables\Columns\TextColumn::make('bookings_count')
                    ->label('Total Bookings')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('completed_bookings_count')
                    ->label('Completed')
                    ->badge()
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'gray'
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('View Details')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Branch $record): string => "/director/{$record->id}/dashboard")
                    ->openUrlInNewTab(),
            ]);
    }
}