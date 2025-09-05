<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Facades\Filament;

class CalendarBookings extends Page
{
    protected static string $resource = BookingResource::class;

    protected static string $view = 'filament.resources.booking-resource.pages.calendar-bookings';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->url(BookingResource::getUrl('create')),
        ];
    }

    public function getViewData(): array
    {
        $tenant = Filament::getTenant();
        
        if (!$tenant) {
            return ['bookings' => []];
        }

        // Get bookings for the current month
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        $bookings = Booking::where('branch_id', $tenant->id)
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->with(['client', 'service', 'staff'])
            ->get()
            ->map(function (Booking $booking) {
                return [
                    'id' => $booking->id,
                    'title' => $booking->service->name . ' - ' . $booking->client->first_name . ' ' . $booking->client->last_name,
                    'start' => $booking->appointment_date->format('Y-m-d') . 'T' . $booking->start_time,
                    'end' => $booking->appointment_date->format('Y-m-d') . 'T' . $booking->end_time,
                    'color' => match ($booking->status) {
                        'pending' => '#f59e0b',
                        'confirmed' => '#3b82f6',
                        'in_progress' => '#8b5cf6',
                        'completed' => '#10b981',
                        'cancelled' => '#ef4444',
                        'no_show' => '#6b7280',
                        default => '#6b7280'
                    },
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'booking_reference' => $booking->booking_reference,
                        'client_name' => $booking->client->first_name . ' ' . $booking->client->last_name,
                        'staff_name' => $booking->staff?->name ?? 'Unassigned',
                        'service_name' => $booking->service->name,
                        'status' => $booking->status,
                        'payment_status' => $booking->payment_status,
                        'total_amount' => $booking->total_amount,
                        'view_url' => BookingResource::getUrl('view', ['record' => $booking]),
                        'edit_url' => BookingResource::getUrl('edit', ['record' => $booking]),
                    ]
                ];
            });

        return [
            'bookings' => $bookings->toArray(),
            'create_url' => BookingResource::getUrl('create'),
        ];
    }
}