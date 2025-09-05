<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Facades\Filament;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();
        
        // Ensure the staff member is associated with the current branch
        if ($tenant) {
            $data['branch_associations'] = [$tenant->id];
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $tenant = Filament::getTenant();
        
        // Associate the staff with the current branch
        if ($tenant && $this->record) {
            $this->record->branches()->syncWithoutDetaching([$tenant->id]);
        }
    }
}