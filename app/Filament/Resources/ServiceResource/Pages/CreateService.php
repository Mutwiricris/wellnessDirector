<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Facades\Filament;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    protected function afterCreate(): void
    {
        $tenant = Filament::getTenant();
        
        // Associate the service with the current branch
        if ($tenant && $this->record) {
            $this->record->branches()->syncWithoutDetaching([$tenant->id => [
                'is_available' => true,
                'custom_price' => null
            ]]);
        }
    }
}