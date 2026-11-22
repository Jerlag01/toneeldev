<?php

namespace App\Filament\Resources\ShopTables\Pages;

use App\Filament\Pages\Bar\QrDesigner;
use App\Filament\Resources\ShopTables\ShopTableResource;
use App\Models\ShopTable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageShopTables extends ManageRecords
{
    protected static string $resource = ShopTableResource::class;

    public function getSubheading(): ?string
    {
        return 'Rij A staat vooraan bij het podium, nummer 1 links. Het tafelplan en de QR-codes volgen deze lijst.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Tafels genereren')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->modalDescription('Maakt alle combinaties van rijen en nummers aan. Bestaande tafels blijven ongewijzigd.')
                ->schema([
                    TextInput::make('from')->label('Van rij')->default('A')->required()->maxLength(1)->alpha(),
                    TextInput::make('to')->label('Tot en met rij')->default('F')->required()->maxLength(1)->alpha(),
                    TextInput::make('numbers')->label('Tafels per rij')->numeric()->integer()->minValue(1)->maxValue(50)->default(5)->required(),
                ])
                ->action(function (array $data): void {
                    $from = ord(strtoupper($data['from']));
                    $to = ord(strtoupper($data['to']));
                    $created = 0;

                    foreach (range(min($from, $to), max($from, $to)) as $letter) {
                        for ($n = 1; $n <= (int) $data['numbers']; $n++) {
                            $table = ShopTable::query()->firstOrCreate(['row' => chr($letter), 'number' => $n], ['is_active' => true]);
                            $created += (int) $table->wasRecentlyCreated;
                        }
                    }

                    Notification::make()->success()->title($created.' tafel(s) aangemaakt')->send();
                }),
            Action::make('print')->label('Alle QR-codes afdrukken')->icon(Heroicon::OutlinedPrinter)->color('gray')
                ->url(route('admin.qr.print'), shouldOpenInNewTab: true),
            Action::make('design')->label('QR-ontwerp')->icon(Heroicon::OutlinedPaintBrush)->color('gray')
                ->url(fn (): string => QrDesigner::getUrl()),
            CreateAction::make(),
        ];
    }
}
