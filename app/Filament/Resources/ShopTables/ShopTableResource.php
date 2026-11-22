<?php

namespace App\Filament\Resources\ShopTables;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\ShopTables\Pages\ManageShopTables;
use App\Models\ShopTable;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Tables in the hall: a row letter (A = front, at the stage) and a number (1 = left). The table plan follows these.
 */
class ShopTableResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::SHOP_TABLES;

    protected static ?string $model = ShopTable::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'tafel';

    protected static ?string $pluralModelLabel = 'tafels';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bar;

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('row')->label('Rij (letter)')->helperText('A = vooraan bij het podium.')->required()->maxLength(5)->alpha()
                ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state))),
            TextInput::make('number')->label('Nummer')->helperText('1 = links (gezien vanuit de zaal naar het podium).')->numeric()->integer()->minValue(1)->maxValue(999)->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('row', strtoupper(trim((string) $get('row'))))),
            TextInput::make('name')->label('Extra naam (optioneel)')->placeholder('bv. Sponsortafel')->maxLength(60),
            TextInput::make('seats')->label('Plaatsen')->numeric()->integer()->minValue(1)->maxValue(99),
            Toggle::make('is_active')->label('In gebruik (kan bestellen)')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->ordered())
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->columns([
                TextColumn::make('label')->label('Tafel')->state(fn (ShopTable $record): string => $record->label())->weight('bold')->size('lg'),
                TextColumn::make('name')->label('Naam')->placeholder('-'),
                TextColumn::make('seats')->label('Plaatsen')->placeholder('-'),
                TextColumn::make('url')->label('Bestellink (QR)')->state(fn (ShopTable $record): string => $record->orderUrl())->copyable()->limit(50)->color('gray'),
                ToggleColumn::make('is_active')->label('In gebruik'),
            ])
            ->recordActions([
                Action::make('qr')->label('QR')->icon(Heroicon::OutlinedQrCode)->color('gray')
                    ->url(fn (ShopTable $record): string => route('admin.qr.print', ['tables' => $record->id]), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('printQr')->label('QR-codes afdrukken')->icon(Heroicon::OutlinedPrinter)
                    ->action(fn (Collection $records, $livewire) => $livewire->js('window.open('.json_encode(route('admin.qr.print', ['tables' => $records->pluck('id')->implode(',')])).', "_blank")')),
                BulkAction::make('activate')->label('In gebruik')->icon(Heroicon::OutlinedCheck)->action(fn (Collection $records) => $records->each->update(['is_active' => true])),
                BulkAction::make('deactivate')->label('Niet in gebruik')->icon(Heroicon::OutlinedXMark)->action(fn (Collection $records) => $records->each->update(['is_active' => false])),
                DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageShopTables::route('/')];
    }
}
