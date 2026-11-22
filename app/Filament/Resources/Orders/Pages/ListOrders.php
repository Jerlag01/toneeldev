<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        return [
            'closed' => Tab::make('Afgesloten')->modifyQueryUsing(fn (Builder $query) => $query->where('status', Order::CLOSED)),
            'cancelled' => Tab::make('Geannuleerd')->modifyQueryUsing(fn (Builder $query) => $query->where('status', Order::CANCELLED))
                ->badge(fn (): ?int => Order::query()->where('status', Order::CANCELLED)->whereDate('cancelled_at', today())->count() ?: null)->badgeColor('danger'),
            'open' => Tab::make('Open')->modifyQueryUsing(fn (Builder $query) => $query->where('status', Order::OPEN)),
            'all' => Tab::make('Alle'),
        ];
    }
}
