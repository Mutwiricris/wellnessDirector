<?php

namespace App\Filament\Resources\ServicePackageResource\Pages;

use App\Filament\Resources\ServicePackageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServicePackage extends EditRecord
{
    protected static string $resource = ServicePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load services with pivot data for the repeater
        $services = $this->record->services()->get();
        $data['services'] = $services->map(function ($service) {
            return [
                'service_id' => $service->id,
                'quantity' => $service->pivot->quantity,
                'order' => $service->pivot->order,
                'is_required' => $service->pivot->is_required,
                'notes' => $service->pivot->notes,
            ];
        })->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Calculate final price if not set
        if (!isset($data['final_price']) || $data['final_price'] === null) {
            $data['final_price'] = ($data['total_price'] ?? 0) - ($data['discount_amount'] ?? 0);
        }
        
        // Ensure final price is not negative
        $data['final_price'] = max(0, $data['final_price']);
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Sync services with pivot data
        $services = $this->data['services'] ?? [];
        $syncData = [];
        
        foreach ($services as $serviceData) {
            $syncData[$serviceData['service_id']] = [
                'quantity' => $serviceData['quantity'] ?? 1,
                'order' => $serviceData['order'] ?? 1,
                'is_required' => $serviceData['is_required'] ?? true,
                'notes' => $serviceData['notes'] ?? null,
            ];
        }
        
        $this->record->services()->sync($syncData);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}