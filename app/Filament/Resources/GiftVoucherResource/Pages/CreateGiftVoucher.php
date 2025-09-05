<?php

namespace App\Filament\Resources\GiftVoucherResource\Pages;

use App\Filament\Resources\GiftVoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateGiftVoucher extends CreateRecord
{
    protected static string $resource = GiftVoucherResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure remaining amount equals original amount for new vouchers
        $data['remaining_amount'] = $data['original_amount'];
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}