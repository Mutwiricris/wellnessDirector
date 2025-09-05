<?php

namespace App\Filament\Resources\GiftVoucherResource\Pages;

use App\Filament\Resources\GiftVoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGiftVoucher extends EditRecord
{
    protected static string $resource = GiftVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}