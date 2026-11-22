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
use UnitEnum;

/**
 * The hall seen from above: row A at the front (stage), number 1 on the left, the technical tower at the back.
 * Built from the tables in Bar → Tafels, so it follows when tables are added or removed. Click a table for its orders.
 */
class TablePlan extends Page
{
    use HandlesOrders;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bar;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Tafelplan';

    protected static ?string $title = 'Tafelplan';

    protected static ?string $slug = 'bar/tafelplan';

    protected string $view = 'filament.bar.table-plan';

    public ?int $selected = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::SHOP_ORDERS);
    }

    public function select(?int $id): void
    {
        $this->selected = $this->selected === $id ? null : $id;
    }

    /**
     * Rows in order A, B, C... each with its tables from left (1) to right.
     *
     * @return Collection<string, Collection<int, ShopTable>>
     */
    public function rows(): Collection
    {
        return ShopTable::query()->ordered()->get()->groupBy('row');
    }

    /**
     * @return array<int, array{count: int, total: float, oldest: ?\Illuminate\Support\Carbon}>
     */
    public function openPerTable(): array
    {
        return Order::query()->open()->whereNotNull('shop_table_id')
            ->selectRaw('shop_table_id, COUNT(*) AS c, SUM(total) AS t, MIN(created_at) AS o')
            ->groupBy('shop_table_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->shop_table_id => ['count' => (int) $row->c, 'total' => (float) $row->t, 'oldest' => \Illuminate\Support\Carbon::parse($row->o)]])
            ->all();
    }

    public function selectedTable(): ?ShopTable
    {
        return $this->selected ? ShopTable::query()->find($this->selected) : null;
    }

    /**
     * @return Collection<int, Order>
     */
    public function selectedOrders(): Collection
    {
        return $this->selected
            ? Order::query()->open()->with('items')->where('shop_table_id', $this->selected)->oldest()->get()
            : collect();
    }
}
