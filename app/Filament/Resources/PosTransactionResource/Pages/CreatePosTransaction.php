<?php

namespace App\Filament\Resources\PosTransactionResource\Pages;

use App\Filament\Resources\PosTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePosTransaction extends CreateRecord
{
    protected static string $resource = PosTransactionResource::class;
}
