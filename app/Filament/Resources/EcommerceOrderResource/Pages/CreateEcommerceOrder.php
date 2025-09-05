<?php

namespace App\Filament\Resources\EcommerceOrderResource\Pages;

use App\Filament\Resources\EcommerceOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEcommerceOrder extends CreateRecord
{
    protected static string $resource = EcommerceOrderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}