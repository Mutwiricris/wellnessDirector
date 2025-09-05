<?php

namespace App\Filament\Resources\GiftVoucherResource\Pages;

use App\Filament\Resources\GiftVoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewGiftVoucher extends ViewRecord
{
    protected static string $resource = GiftVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            
            Actions\Action::make('mark_expired')
                ->label('Mark as Expired')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->visible(fn () => $this->getRecord()->status === 'active')
                ->requiresConfirmation()
                ->action(function () {
                    $this->getRecord()->update(['status' => 'expired']);
                    
                    Notification::make()
                        ->title('Voucher marked as expired')
                        ->success()
                        ->send();
                        
                    $this->refreshFormData([
                        'status',
                    ]);
                }),

            Actions\Action::make('cancel_voucher')
                ->label('Cancel Voucher')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => in_array($this->getRecord()->status, ['active', 'expired']))
                ->requiresConfirmation()
                ->modalDescription('Are you sure you want to cancel this voucher? This action cannot be undone.')
                ->action(function () {
                    $this->getRecord()->update(['status' => 'cancelled']);
                    
                    Notification::make()
                        ->title('Voucher cancelled successfully')
                        ->success()
                        ->send();
                        
                    $this->refreshFormData([
                        'status',
                    ]);
                }),

            Actions\Action::make('print_voucher')
                ->label('Print Voucher')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('gift-vouchers.print', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }
}