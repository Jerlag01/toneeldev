<?php

namespace App\Filament\Pages\Bar;

use App\Filament\Actions\CancelOrderAction;
use App\Models\Order;
use App\Support\Permissions;
use App\Support\Shop\OrderService;
use Filament\Notifications\Notification;

/**
 * Closing orders and switching ordering on/off, shared by the order board and the table plan.
 */
trait HandlesOrders
{
    public function closeOrder(int $id): void
    {
        abort_unless(auth()->user()?->hasPermission(Permissions::SHOP_ORDERS), 403);

        $order = Order::query()->find($id);

        if ($order) {
            app(OrderService::class)->close($order, auth()->id());
            Notification::make()->success()->title('Bestelling '.$order->number.' afgesloten')->send();
        }
    }

    public function cancelOrderAction(): \Filament\Actions\Action
    {
        return CancelOrderAction::make();
    }

    public function closeTable(int $tableId): void
    {
        abort_unless(auth()->user()?->hasPermission(Permissions::SHOP_ORDERS), 403);

        $orders = Order::query()->open()->where('shop_table_id', $tableId)->get();
        $orders->each(fn (Order $order) => app(OrderService::class)->close($order, auth()->id()));
        Notification::make()->success()->title($orders->count().' bestelling(en) afgesloten')->send();
    }

    public function toggleShop(): void
    {
        abort_unless(auth()->user()?->hasAnyPermission(Permissions::SHOP_SETTINGS, Permissions::SHOP_ORDERS), 403);

        $open = ! OrderService::isOpen();
        settings()->save(['shop' => [...(array) settings()->get('shop'), 'open' => $open]]);
        Notification::make()->{$open ? 'success' : 'warning'}()->title($open ? 'Bestellen is weer open' : 'Bestellen is gesloten')->send();
    }

    public function shopOpen(): bool
    {
        return OrderService::isOpen();
    }

    public function refreshSeconds(): int
    {
        return max(2, min(60, (int) settings()->get('shop.board_refresh', 5)));
    }
}
