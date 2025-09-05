<?php

namespace App\Filament\Resources\PosTransactionResource\Pages;

use App\Filament\Resources\PosTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPosTransactions extends ListRecords
{
    protected static string $resource = PosTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
