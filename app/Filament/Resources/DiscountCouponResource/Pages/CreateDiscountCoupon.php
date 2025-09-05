<?php

namespace App\Filament\Resources\DiscountCouponResource\Pages;

use App\Filament\Resources\DiscountCouponResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDiscountCoupon extends CreateRecord
{
    protected static string $resource = DiscountCouponResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Initialize used_count to 0
        $data['used_count'] = 0;
        
        // Set default values for time restrictions if not provided
        if (empty($data['time_restrictions'])) {
            $data['time_restrictions'] = null;
        }
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}