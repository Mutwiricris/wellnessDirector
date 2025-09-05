<?php

namespace App\Filament\Resources\ServiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Service Bookings';

    protected static ?string $label = 'Booking';

    protected static ?string $pluralLabel = 'Bookings';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('appointment_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Time')
                    ->formatStateUsing(fn ($record) => $record->start_time . ' - ' . $record->end_time),

                Tables\Columns\TextColumn::make('client.first_name')
                    ->label('Client')
                    ->formatStateUsing(fn ($record) => $record->client->first_name . ' ' . $record->client->last_name)
                    ->searchable(['first_name', 'last_name']),

                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Staff Member')
                    ->searchable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->badge()
                    ->color('info'),

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
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => $state ? $state . '/5 ⭐' : 'Not rated')
                    ->color('warning'),

                Tables\Columns\TextColumn::make('feedback')
                    ->label('Client Feedback')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->feedback),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'no_show' => 'No Show',
                    ]),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),

                Tables\Filters\SelectFilter::make('branch')
                    ->relationship('branch', 'name'),

                Tables\Filters\Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn ($query) => $query->whereMonth('appointment_date', now()->month)),

                Tables\Filters\Filter::make('high_rated')
                    ->label('High Rated (4+ stars)')
                    ->query(fn ($query) => $query->where('rating', '>=', 4)),
            ])
            ->defaultSort('appointment_date', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.director.resources.bookings.view', [
                        'tenant' => \Filament\Facades\Filament::getTenant()?->id,
                        'record' => $record->id
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // No bulk actions for view-only relationship
                ]),
            ])
            ->emptyStateHeading('No bookings yet')
            ->emptyStateDescription('This service has no booking history.')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }
}