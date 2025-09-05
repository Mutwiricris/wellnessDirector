<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Booking;
use App\Models\Staff;
use App\Models\StaffCommission;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

class StaffPerformanceWidget extends BaseWidget
{
    protected static ?string $heading = 'Staff Performance & Productivity Analysis';
    
    protected static ?int $sort = 5;
    
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\ImageColumn::make('profile_photo')
                    ->label('Photo')
                    ->circular()
                    ->defaultImageUrl('/images/default-avatar.png')
                    ->size(40),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Staff Member')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('specializations')
                    ->label('Specialization')
                    ->badge()
                    ->separator(',')
                    ->limit(2),
                    
                Tables\Columns\TextColumn::make('bookings_count')
                    ->label('Bookings')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('revenue')
                    ->label('Revenue')
                    ->sortable()
                    ->money('KES')
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('commissions')
                    ->label('Commissions')
                    ->sortable()
                    ->money('KES')
                    ->color('warning'),
                    
                Tables\Columns\TextColumn::make('completion_rating')
                    ->label('Completion Rate')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $rating = $record->bookings_count > 0 && $record->completed_bookings > 0 
                            ? ($record->completed_bookings / $record->bookings_count) * 5.0 
                            : 4.0;
                        return number_format($rating, 1) . '/5.0';
                    })
                    ->color(function ($record) {
                        $rating = $record->bookings_count > 0 && $record->completed_bookings > 0 
                            ? ($record->completed_bookings / $record->bookings_count) * 5.0 
                            : 4.0;
                        return match (true) {
                            $rating >= 4.5 => 'success',
                            $rating >= 4.0 => 'warning',
                            default => 'danger',
                        };
                    }),
                    
                Tables\Columns\TextColumn::make('utilization_rate')
                    ->label('Utilization')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $rate = $record->bookings_count > 0 
                            ? min(($record->bookings_count * 2.0 / 22 * 8), 100)
                            : 0;
                        return number_format($rate, 1) . '%';
                    })
                    ->color(function ($record) {
                        $rate = $record->bookings_count > 0 
                            ? min(($record->bookings_count * 2.0 / 22 * 8), 100)
                            : 0;
                        return match (true) {
                            $rate >= 80 => 'success',
                            $rate >= 60 => 'warning',
                            default => 'danger',
                        };
                    }),
                    
                Tables\Columns\TextColumn::make('performance_score')
                    ->label('Performance')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $score = $record->bookings_count > 0 
                            ? (
                                ($record->completed_bookings / max($record->bookings_count, 1) * 40) +
                                (min($record->bookings_count / 20.0, 1.0) * 35) +
                                (min(($record->revenue ?? 0) / 100000.0, 1.0) * 25)
                            )
                            : 0;
                        return number_format($score, 0) . '%';
                    })
                    ->color(function ($record) {
                        $score = $record->bookings_count > 0 
                            ? (
                                ($record->completed_bookings / max($record->bookings_count, 1) * 40) +
                                (min($record->bookings_count / 20.0, 1.0) * 35) +
                                (min(($record->revenue ?? 0) / 100000.0, 1.0) * 25)
                            )
                            : 0;
                        return match (true) {
                            $score >= 90 => 'success',
                            $score >= 75 => 'warning',
                            default => 'danger',
                        };
                    }),
            ])
            ->defaultSort('bookings_count', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->poll('60s');
    }

    protected function getTableQuery(): Builder
    {
        $tenant = Filament::getTenant();
        
        if (!$tenant) {
            return Staff::query()->whereRaw('1 = 0'); // Return empty query
        }

        $currentMonth = now();
        $startDate = $currentMonth->copy()->startOfMonth();
        $endDate = $currentMonth->copy()->endOfMonth();

        $baseQuery = Staff::whereHas('branches', function($q) use ($tenant) {
            $q->where('branches.id', $tenant->id);
        })
        ->where('status', 'active');

        // Add aggregations
        $queryWithAggregations = $baseQuery
            ->withCount([
                'bookings as bookings_count' => function($q) use ($tenant, $startDate, $endDate) {
                    $q->where('branch_id', $tenant->id)
                      ->whereBetween('appointment_date', [$startDate, $endDate]);
                }
            ])
            ->withSum([
                'bookings as revenue' => function($q) use ($tenant, $startDate, $endDate) {
                    $q->where('branch_id', $tenant->id)
                      ->whereBetween('appointment_date', [$startDate, $endDate])
                      ->where('payment_status', 'completed');
                }
            ], 'total_amount')
            ->withSum([
                'commissions as commissions' => function($q) use ($tenant, $startDate, $endDate) {
                    $q->where('branch_id', $tenant->id)
                      ->whereBetween('earned_date', [$startDate, $endDate])
                      ->where('approval_status', 'approved');
                }
            ], 'commission_amount')
            ->withCount([
                'bookings as completed_bookings' => function($q) use ($tenant, $startDate, $endDate) {
                    $q->where('branch_id', $tenant->id)
                      ->whereBetween('appointment_date', [$startDate, $endDate])
                      ->where('bookings.status', 'completed');
                }
            ]);

        // Return the query with basic aggregations, calculations will be done in column formatting
        return $queryWithAggregations;
    }
}