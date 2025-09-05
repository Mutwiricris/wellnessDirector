<?php

namespace App\Filament\Resources\BranchProductInventoryResource\Pages;

use App\Filament\Resources\BranchProductInventoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBranchProductInventory extends CreateRecord
{
    protected static string $resource = BranchProductInventoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}