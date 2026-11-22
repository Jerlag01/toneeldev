<?php

namespace App\Filament\Pages\Bar;

use App\Enums\NavigationGroup;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Permissions;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Sold products per product and per day (open and closed orders; unpaid or cancelled online orders do not count).
 */
class SalesStats extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bar;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Verkoopstatistieken';

    protected static ?string $title = 'Verkoopstatistieken';

    protected static ?string $slug = 'bar/statistieken';

    protected string $view = 'filament.bar.sales-stats';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $until = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::SHOP_STATS);
    }

    public function mount(): void
    {
        // Default: the last 7 days that had sales (a run of performances), else the last 7 days.
        $last = Order::query()->sold()->max('created_at');
        $end = $last ? CarbonImmutable::parse($last) : CarbonImmutable::today();
        $this->until ??= $end->toDateString();
        $this->from ??= $end->subDays(6)->toDateString();
    }

    public function preset(string $preset): void
    {
        [$this->from, $this->until] = match ($preset) {
            'today' => [today()->toDateString(), today()->toDateString()],
            '7d' => [today()->subDays(6)->toDateString(), today()->toDateString()],
            '30d' => [today()->subDays(29)->toDateString(), today()->toDateString()],
            'year' => [today()->startOfYear()->toDateString(), today()->toDateString()],
            default => [$this->from, $this->until],
        };
    }

    /**
     * @return array{days: list<string>, rows: list<array{name: string, category: ?string, per_day: array<string, int>, quantity: int, bonnen: float}>, day_totals: array<string, array{quantity: int, bonnen: float, orders: int, online_eur: float}>, totals: array{quantity: int, bonnen: float, orders: int, online_eur: float}}
     */
    public function data(): array
    {
        $from = CarbonImmutable::parse($this->from ?: today())->startOfDay();
        $until = CarbonImmutable::parse($this->until ?: today())->endOfDay();

        if ($until->lt($from)) {
            [$from, $until] = [$until->startOfDay(), $from->endOfDay()];
        }

        $items = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', [Order::OPEN, Order::CLOSED])
            ->whereBetween('orders.created_at', [$from, $until])
            ->selectRaw('order_items.name, order_items.category, DATE(orders.created_at) AS day, SUM(order_items.quantity) AS qty, SUM(order_items.quantity * order_items.price) AS bonnen')
            ->groupBy('order_items.name', 'order_items.category', 'day')
            ->get();

        $days = $items->pluck('day')->unique()->sort()->values()->all();

        // Show every day of a short range, only the days with sales for longer ranges.
        if ($from->diffInDays($until) <= 14) {
            $days = collect(CarbonPeriod::create($from, '1 day', $until))->map(fn ($d) => $d->toDateString())->all();
        }

        $rows = $items->groupBy(fn ($i) => $i->category.'|'.$i->name)->map(fn ($group): array => [
            'name' => $group->first()->name,
            'category' => $group->first()->category,
            'per_day' => $group->mapWithKeys(fn ($i): array => [$i->day => (int) $i->qty])->all(),
            'quantity' => (int) $group->sum('qty'),
            'bonnen' => (float) $group->sum('bonnen'),
        ])->sortBy([['category', 'asc'], ['quantity', 'desc']])->values()->all();

        $orders = Order::query()->sold()->whereBetween('created_at', [$from, $until])
            ->selectRaw("DATE(created_at) AS day, COUNT(*) AS c, SUM(CASE WHEN payment_status = 'paid' THEN total_eur ELSE 0 END) AS eur")
            ->groupBy('day')->get()->keyBy('day');

        $dayTotals = [];
        foreach ($days as $day) {
            $dayItems = $items->where('day', $day);
            $dayTotals[$day] = [
                'quantity' => (int) $dayItems->sum('qty'),
                'bonnen' => (float) $dayItems->sum('bonnen'),
                'orders' => (int) ($orders[$day]->c ?? 0),
                'online_eur' => (float) ($orders[$day]->eur ?? 0),
            ];
        }

        return [
            'days' => $days,
            'rows' => $rows,
            'day_totals' => $dayTotals,
            'totals' => [
                'quantity' => (int) $items->sum('qty'),
                'bonnen' => (float) $items->sum('bonnen'),
                'orders' => (int) $orders->sum('c'),
                'online_eur' => (float) $orders->sum('eur'),
            ],
        ];
    }
}
