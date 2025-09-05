<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Facades\Filament;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();
        
        // Auto-assign branch_id from current tenant
        $data['branch_id'] = $tenant->id;
        
        // Generate unique booking reference
        $data['booking_reference'] = $this->generateBookingReference();
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Send notification about new booking
        \Filament\Notifications\Notification::make()
            ->title('Booking Created Successfully')
            ->body('Booking ' . $this->record->booking_reference . ' has been created.')
            ->success()
            ->send();
    }

    private function generateBookingReference(): string
    {
        $tenant = Filament::getTenant();
        $branchCode = strtoupper(substr($tenant->name, 0, 3));
        $timestamp = now()->format('ymd');
        $random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        return $branchCode . $timestamp . $random;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCreateAnotherFormAction(),
            $this->getCancelFormAction(),
        ];
    }
}
