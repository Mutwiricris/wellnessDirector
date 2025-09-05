<?php

namespace App\Filament\Resources\EcommerceOrderResource\Pages;

use App\Filament\Resources\EcommerceOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEcommerceOrder extends EditRecord
{
    protected static string $resource = EcommerceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}