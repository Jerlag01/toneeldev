<?php

namespace App\Filament\Resources\Productions\Pages;

use App\Filament\Resources\Productions\ProductionResource;
use App\Models\Production;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProduction extends EditRecord
{
    protected static string $resource = ProductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label('Bekijken')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn (Production $record): string => $record->url(), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
