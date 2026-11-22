<?php

namespace App\Filament\Pages\Bar;

use App\Enums\NavigationGroup;
use App\Support\Permissions;
use App\Support\Shop\FloorPlan;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Draw the hall: place and size the tables, the stage, the bar, doors, corridors, walls, the technical tower...
 * The live table plan (Bar → Tafelplan) shows exactly this drawing with the open orders on it.
 */
class FloorPlanEditor extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bar;

    protected static ?int $navigationSort = 14;

    protected static ?string $navigationLabel = 'Grondplan';

    protected static ?string $title = 'Grondplan van de zaal';

    protected static ?string $slug = 'bar/grondplan';

    protected string $view = 'filament.bar.floor-plan-editor';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::SHOP_TABLES);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed> the plan as saved (with ids for new elements)
     */
    public function savePlan(array $plan): array
    {
        abort_unless(static::canAccess(), 403);

        FloorPlan::save($plan);
        Notification::make()->success()->title('Grondplan opgeslagen')->send();

        return FloorPlan::data();
    }

    /**
     * @return array<string, mixed>
     */
    public function suggestion(): array
    {
        abort_unless(static::canAccess(), 403);

        return FloorPlan::suggestion();
    }
}
