<?php

namespace App\Filament\Actions;

use App\Models\Order;
use App\Support\Permissions;
use App\Support\Shop\OrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Cancels an order with a reason (guest asked, excessive, abuse...), optionally refunding an online payment and
 * blocking the guest's IP address. Used on the order board and table plan (order id in the arguments) and in the
 * order list (the record).
 */
class CancelOrderAction
{
    public static function make(string $name = 'cancelOrder'): Action
    {
        $resolve = function (?Order $record = null, array $arguments = []): ?Order {
            return $record ?? Order::query()->find($arguments['order'] ?? 0);
        };

        return Action::make($name)
            ->label('Annuleren')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize(fn (): bool => (bool) auth()->user()?->hasPermission(Permissions::SHOP_ORDERS))
            ->modalHeading(fn (?Order $record = null, array $arguments = []): string => 'Bestelling '.($resolve($record, $arguments)?->number ?? '').' annuleren')
            ->modalDescription(fn (?Order $record = null, array $arguments = []): string => ($order = $resolve($record, $arguments))
                ? 'Tafel '.$order->table_label.' - '.$order->customer_name.': '.$order->items()->get()->map(fn ($i) => $i->quantity.'× '.$i->name)->implode(', ').'. De bestelling blijft bewaard (met reden), verdwijnt uit de lijst en telt niet mee in de statistieken. De stock komt terug.'
                : '')
            ->modalSubmitActionLabel('Bestelling annuleren')
            ->schema(fn (?Order $record = null, array $arguments = []): array => [
                Select::make('reason')->label('Reden')->options(Order::CANCEL_REASONS)->default('customer')->required()->live(),
                Textarea::make('note')->label('Toelichting (optioneel)')->rows(2)->maxLength(500),
                Toggle::make('refund')
                    ->label('Online betaling terugbetalen')
                    ->helperText('Via '.(($resolve($record, $arguments)?->payment_method === 'sumup') ? 'SumUp' : 'Stripe').'. Bij misbruik kan je dit uitlaten.')
                    ->default(fn (Get $get): bool => ! in_array($get('reason'), ['abuse'], true))
                    ->visible(fn (): bool => (bool) $resolve($record, $arguments)?->isPaidOnline()),
                Toggle::make('block_ip')
                    ->label('IP-adres van deze gast 24 uur blokkeren')
                    ->helperText(fn (): string => 'IP-adres: '.($resolve($record, $arguments)?->ip ?: 'onbekend').'. De gast kan dan de hele website niet meer openen (aanpassen bij Beveiliging → Geblokkeerde IP\'s). Let op: gasten op hetzelfde wifi-netwerk delen soms één adres.')
                    ->default(fn (Get $get): bool => $get('reason') === 'abuse')
                    ->visible(fn (): bool => filled($resolve($record, $arguments)?->ip) && (bool) auth()->user()?->hasPermission(Permissions::SECURITY)),
                Text::make('Je kan enkel IP-adressen blokkeren met het recht "Beveiliging".')
                    ->visible(fn (Get $get): bool => $get('reason') === 'abuse' && ! auth()->user()?->hasPermission(Permissions::SECURITY)),
            ])
            ->visible(fn (?Order $record = null, array $arguments = []): bool => in_array($resolve($record, $arguments)?->status, [Order::OPEN, Order::CLOSED, Order::PENDING], true))
            ->action(function (array $data, ?Order $record = null, array $arguments = []) use ($resolve): void {
                $order = $resolve($record, $arguments);

                if (! $order) {
                    return;
                }

                $blockIp = ! empty($data['block_ip']) && auth()->user()?->hasPermission(Permissions::SECURITY);
                $warnings = app(OrderService::class)->cancelByStaff($order, (string) $data['reason'], $data['note'] ?? null, auth()->user(), ! empty($data['refund']), $blockIp);

                Notification::make()
                    ->title('Bestelling '.$order->number.' geannuleerd'.($blockIp ? ' en IP-adres geblokkeerd' : ''))
                    ->body($warnings === [] ? null : implode(' ', $warnings))
                    ->{$warnings === [] ? 'success' : 'warning'}()
                    ->persistent($warnings !== [])
                    ->send();
            });
    }
}
