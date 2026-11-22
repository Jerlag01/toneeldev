<?php

namespace App\Filament\Pages\Bar;

use App\Enums\NavigationGroup;
use App\Models\Order;
use App\Models\ShopTable;
use App\Support\Permissions;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * The live list of open orders for the bar. Only the list refreshes (Livewire polling), the page itself never reloads.
 */
class OrderBoard extends Page
{
    use HandlesOrders;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bar;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Bestellingen';

    protected static ?string $title = 'Openstaande bestellingen';

    protected static ?string $slug = 'bar/bestellingen';

    protected string $view = 'filament.bar.order-board';

    #[Url(as: 'tafel')]
    public ?string $table = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::SHOP_ORDERS);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Order::query()->open()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function clearTable(): void
    {
        $this->table = null;
    }

    public function selectedTable(): ?ShopTable
    {
        return filled($this->table) ? ShopTable::findByLabel($this->table) : null;
    }

    /**
     * @return Collection<int, Order>
     */
    public function orders(): Collection
    {
        $table = $this->selectedTable();

        return Order::query()->open()->with('items')
            ->when($table, fn ($query) => $query->where('shop_table_id', $table->id))
            ->oldest()
            ->get();
    }
}
