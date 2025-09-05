<?php

namespace App\Filament\Resources\ServicePackageResource\Pages;

use App\Filament\Resources\ServicePackageResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateServicePackage extends CreateRecord
{
    protected static string $resource = ServicePackageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Calculate final price if not set
        if (!isset($data['final_price']) || $data['final_price'] === null) {
            $data['final_price'] = ($data['total_price'] ?? 0) - ($data['discount_amount'] ?? 0);
        }
        
        // Ensure final price is not negative
        $data['final_price'] = max(0, $data['final_price']);
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Attach services with pivot data
        $services = $this->data['services'] ?? [];
        
        foreach ($services as $serviceData) {
            $this->record->services()->attach($serviceData['service_id'], [
                'quantity' => $serviceData['quantity'] ?? 1,
                'order' => $serviceData['order'] ?? 1,
                'is_required' => $serviceData['is_required'] ?? true,
                'notes' => $serviceData['notes'] ?? null,
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}