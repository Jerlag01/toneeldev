<?php

namespace App\Filament\Resources\Orders;

use App\Enums\NavigationGroup;
use App\Filament\Actions\CancelOrderAction;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Support\Permissions;
use App\Support\Shop\OrderService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * All orders, by default the completed (closed) ones. Closing never deletes an order.
 */
class OrderResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::SHOP_ORDERS;

    protected static ?string $model = Order::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'bestelling';

    protected static ?string $pluralModelLabel = 'bestellingen (geschiedenis)';

    protected static ?string $navigationLabel = 'Afgesloten & geannuleerd';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bar;

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * Removing an order for good (e.g. test or spam orders) is for whoever manages the bar settings.
     */
    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::SHOP_SETTINGS);
    }

    public static function canDeleteAny(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::SHOP_SETTINGS);
    }

    public static function statuses(): array
    {
        return [Order::OPEN => 'Open', Order::CLOSED => 'Afgesloten', Order::PENDING => 'Wacht op online betaling', Order::CANCELLED => 'Geannuleerd'];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('number')->label('Bestelling'),
            TextEntry::make('table_label')->label('Tafel'),
            TextEntry::make('customer_name')->label('Naam'),
            TextEntry::make('created_at')->label('Besteld')->dateTime('d/m/Y H:i:s'),
            TextEntry::make('closed_at')->label('Afgesloten')->dateTime('d/m/Y H:i:s')->placeholder('-')->helperText(fn (Order $record): ?string => $record->closer?->name),
            TextEntry::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state): string => self::statuses()[$state] ?? $state),
            TextEntry::make('payment_method')->label('Betaling')->formatStateUsing(fn (Order $record): string => $record->methodLabel().($record->isPaidOnline() ? ' - betaald '.$record->paid_at?->format('H:i') : '')),
            TextEntry::make('total')->label('Totaal')->formatStateUsing(fn (Order $record): string => settings()->bonnen($record->total).($record->isOnline() ? ' (€ '.number_format((float) $record->total_eur, 2, ',', '.').')' : '')),
            TextEntry::make('note')->label('Opmerking')->placeholder('-'),
            TextEntry::make('cancel_reason')->label('Geannuleerd')->visible(fn (Order $record): bool => $record->status === Order::CANCELLED)
                ->state(fn (Order $record): ?string => $record->cancelReasonLabel())
                ->helperText(fn (Order $record): ?string => collect([$record->cancel_note, $record->canceller?->name, $record->cancelled_at?->format('d/m/Y H:i'), $record->refunded_at ? 'terugbetaald' : null])->filter()->implode(' · ') ?: null),
            TextEntry::make('ip')->label('IP-adres')->placeholder('-')->copyable(),
            RepeatableEntry::make('items')->label('Producten')->columnSpanFull()->columns(3)->schema([
                TextEntry::make('quantity')->label('Aantal'),
                TextEntry::make('name')->label('Product'),
                TextEntry::make('price')->label('Prijs')->formatStateUsing(fn ($state): string => settings()->bonnen($state)),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('items', 'closer'))
            ->columns([
                TextColumn::make('number')->label('#')->searchable(),
                TextColumn::make('created_at')->label('Besteld')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('table_label')->label('Tafel')->searchable()->weight('bold'),
                TextColumn::make('customer_name')->label('Naam')->searchable(),
                TextColumn::make('items_summary')->label('Bestelling')->state(fn (Order $record): string => $record->items->map(fn ($i) => $i->quantity.'× '.$i->name)->implode(', '))->wrap(),
                TextColumn::make('total')->label('Bonnen')->formatStateUsing(fn ($state): string => settings()->bonnen($state))->sortable(),
                TextColumn::make('payment_method')->label('Betaling')->badge()
                    ->formatStateUsing(fn (Order $record): string => $record->isPaidOnline() ? 'Online betaald' : $record->methodLabel())
                    ->color(fn (Order $record): string => $record->isPaidOnline() ? 'success' : 'gray'),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state): string => self::statuses()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) { Order::OPEN => 'warning', Order::CLOSED => 'success', Order::CANCELLED => 'danger', default => 'gray' }),
                TextColumn::make('cancel_reason')->label('Reden annulering')->state(fn (Order $record): ?string => $record->cancelReasonLabel())
                    ->description(fn (Order $record): ?string => $record->cancel_note)->placeholder('-')->toggleable()->wrap(),
                TextColumn::make('ip')->label('IP-adres')->searchable()->copyable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('closed_at')->label('Afgesloten')->time('H:i')->description(fn (Order $record): ?string => $record->closer?->name)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(self::statuses()),
                SelectFilter::make('payment_method')->label('Betaling')->options(Order::METHODS),
                SelectFilter::make('cancel_reason')->label('Reden annulering')->options(Order::CANCEL_REASONS),
                Filter::make('day')->schema([DatePicker::make('day')->label('Dag')])
                    ->query(fn (Builder $query, array $data) => $query->when($data['day'] ?? null, fn (Builder $q, string $day) => $q->whereDate('created_at', $day))),
            ])
            ->recordActions([
                ViewAction::make(),
                CancelOrderAction::make('cancel'),
                \Filament\Actions\DeleteAction::make()->label('Definitief verwijderen')
                    ->modalDescription('De bestelling verdwijnt helemaal, ook uit de geschiedenis. Annuleren is meestal beter: dan blijft ze bewaard met de reden.')
                    ->before(function (Order $record): void {
                        // Stock comes back for an order that still counted.
                        if (in_array($record->status, [Order::OPEN, Order::PENDING], true)) {
                            app(OrderService::class)->cancelByStaff($record, 'other', 'Verwijderd', auth()->user());
                        }
                    }),
                Action::make('reopen')->label('Heropenen')->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')
                    ->visible(fn (Order $record): bool => $record->status === Order::CLOSED)
                    ->requiresConfirmation()->modalDescription('De bestelling komt terug bij de openstaande bestellingen.')
                    ->action(function (Order $record): void {
                        app(OrderService::class)->reopen($record);
                        Notification::make()->success()->title('Bestelling heropend')->send();
                    }),
            ])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([
                \Filament\Actions\DeleteBulkAction::make()->label('Definitief verwijderen'),
            ])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListOrders::route('/')];
    }
}
