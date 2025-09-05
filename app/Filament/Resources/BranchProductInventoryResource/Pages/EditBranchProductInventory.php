<?php

namespace App\Filament\Resources\BranchProductInventoryResource\Pages;

use App\Filament\Resources\BranchProductInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBranchProductInventory extends EditRecord
{
    protected static string $resource = BranchProductInventoryResource::class;

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