<?php

namespace App\Filament\Resources\PosTransactionResource\Pages;

use App\Filament\Resources\PosTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPosTransaction extends ViewRecord
{
    protected static string $resource = PosTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (): bool => $this->record->payment_status === 'pending'),
                
            Actions\Action::make('print_receipt')
                ->label('Print Receipt')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn (): string => route('pos.receipt.print', $this->record))
                ->openUrlInNewTab(),
                
            Actions\Action::make('resend_receipt')
                ->label('Resend Receipt')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->action('resendReceipt')
                ->visible(fn (): bool => $this->record->isCompleted()),
        ];
    }

    public function resendReceipt(): void
    {
        if ($this->record->receipt) {
            $success = $this->record->receipt->deliver();
            
            if ($success) {
                $this->notify('success', 'Receipt sent successfully');
            } else {
                $this->notify('error', 'Failed to send receipt');
            }
        }
    }
}