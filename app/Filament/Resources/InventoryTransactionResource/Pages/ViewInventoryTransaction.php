<?php

namespace App\Filament\Resources\InventoryTransactionResource\Pages;

use App\Filament\Resources\InventoryTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryTransaction extends ViewRecord
{
    protected static string $resource = InventoryTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (): bool => 
                    $this->record->created_at->isAfter(now()->subHours(24))
                ),
        ];
    }
}