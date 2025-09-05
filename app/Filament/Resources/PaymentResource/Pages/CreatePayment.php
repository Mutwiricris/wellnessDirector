<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Booking;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;
    
    public function mount(): void
    {
        parent::mount();
        
        // Auto-select booking if booking_id is provided in URL
        $bookingId = request()->query('booking_id');
        if ($bookingId) {
            $booking = Booking::find($bookingId);
            if ($booking) {
                $this->form->fill([
                    'booking_id' => $booking->id,
                    'amount' => $booking->total_amount,
                    'payment_method' => $booking->payment_method ?? 'cash',
                    'status' => 'completed', // Default to completed for immediate payment
                    'processed_at' => now(),
                ]);
                
                Notification::make()
                    ->title('Booking Pre-selected')
                    ->body("Payment form filled for booking: {$booking->booking_reference}")
                    ->success()
                    ->send();
            }
        }
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        $data['branch_id'] = $tenant->id;
        
        // Auto-set processed_at if status is completed
        if ($data['status'] === 'completed' && empty($data['processed_at'])) {
            $data['processed_at'] = now();
        }
        
        return $data;
    }
    
    protected function afterCreate(): void
    {
        // Update booking payment status when payment is created
        if ($this->record->booking_id && $this->record->status === 'completed') {
            $booking = Booking::find($this->record->booking_id);
            if ($booking) {
                $booking->update([
                    'payment_status' => 'completed',
                    'payment_method' => $this->record->payment_method,
                ]);
                
                Notification::make()
                    ->title('Booking Updated')
                    ->body('Booking payment status updated to completed')
                    ->success()
                    ->send();
            }
        }
    }
}
