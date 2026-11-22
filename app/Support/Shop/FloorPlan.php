<?php

namespace App\Support\Shop;

use App\Models\FloorElement;
use App\Models\ShopTable;
use Illuminate\Support\Facades\DB;

/**
 * The floor plan of the hall: a grid (width × height units) with the tables and other elements on it.
 * Seen from above with the stage at the top: row A is nearest to the stage, number 1 on the left.
 */
class FloorPlan
{
    /**
     * Element types offered in the editor, with default size (grid units) and colour.
     *
     * @return array<string, array{label: string, w: float, h: float, color: string, icon: string}>
     */
    public static function types(): array
    {
        return [
            'stage' => ['label' => 'Podium', 'w' => 20, 'h' => 4, 'color' => '#1d1a18', 'icon' => '🎭'],
            'bar' => ['label' => 'Bar', 'w' => 8, 'h' => 3, 'color' => '#b4232a', 'icon' => '🍺'],
            'tower' => ['label' => 'Technische toren', 'w' => 4, 'h' => 3, 'color' => '#4b5563', 'icon' => '🎚️'],
            'door' => ['label' => 'Deur', 'w' => 3, 'h' => 1, 'color' => '#a16207', 'icon' => '🚪'],
            'exit' => ['label' => 'Nooduitgang', 'w' => 3, 'h' => 1, 'color' => '#15803d', 'icon' => '🟩'],
            'corridor' => ['label' => 'Gang', 'w' => 2, 'h' => 12, 'color' => '#e7e1d8', 'icon' => '↕️'],
            'wall' => ['label' => 'Muur', 'w' => 10, 'h' => 0.5, 'color' => '#57534e', 'icon' => '▬'],
            'pillar' => ['label' => 'Zuil', 'w' => 1, 'h' => 1, 'color' => '#78716c', 'icon' => '⬛'],
            'stairs' => ['label' => 'Trap', 'w' => 3, 'h' => 3, 'color' => '#a8a29e', 'icon' => '🪜'],
            'toilets' => ['label' => 'Toiletten', 'w' => 4, 'h' => 3, 'color' => '#0369a1', 'icon' => '🚻'],
            'kitchen' => ['label' => 'Keuken', 'w' => 5, 'h' => 3, 'color' => '#c2410c', 'icon' => '🍳'],
            'cloakroom' => ['label' => 'Vestiaire', 'w' => 4, 'h' => 2, 'color' => '#7c3aed', 'icon' => '🧥'],
            'tribune' => ['label' => 'Tribune', 'w' => 16, 'h' => 5, 'color' => '#d6d3d1', 'icon' => '🪑'],
            'zone' => ['label' => 'Zone', 'w' => 6, 'h' => 4, 'color' => '#fde68a', 'icon' => '▢'],
            'text' => ['label' => 'Tekst', 'w' => 6, 'h' => 1, 'color' => '#00000000', 'icon' => 'T'],
        ];
    }

    /**
     * @return array{width: int, height: int}
     */
    public static function size(): array
    {
        $stored = (array) settings()->get('floorplan', []);

        return [
            'width' => max(10, min(120, (int) ($stored['width'] ?? 40))),
            'height' => max(10, min(120, (int) ($stored['height'] ?? 30))),
        ];
    }

    public static function hasLayout(): bool
    {
        return FloorElement::query()->exists() || ShopTable::query()->whereNotNull('plan_x')->exists();
    }

    /**
     * Everything the editor and the live plan need, as plain arrays.
     *
     * @return array{width: int, height: int, elements: list<array<string, mixed>>, tables: list<array<string, mixed>>}
     */
    public static function data(): array
    {
        return [
            ...self::size(),
            'elements' => FloorElement::query()->orderBy('z')->orderBy('id')->get()
                ->map(fn (FloorElement $e): array => ['id' => $e->id, 'type' => $e->type, 'label' => $e->label, 'x' => $e->x, 'y' => $e->y, 'w' => $e->w, 'h' => $e->h, 'color' => $e->color])
                ->values()->all(),
            'tables' => ShopTable::query()->ordered()->get()
                ->map(fn (ShopTable $t): array => [
                    'id' => $t->id, 'label' => $t->label(), 'name' => $t->name, 'active' => $t->is_active,
                    'x' => $t->plan_x, 'y' => $t->plan_y, 'w' => $t->plan_w ?? 2, 'h' => $t->plan_h ?? 2, 'shape' => $t->plan_shape ?? 'rect',
                ])->values()->all(),
        ];
    }

    /**
     * Saves the whole plan from the editor. Elements not in the list are removed; tables keep existing.
     *
     * @param  array<string, mixed>  $plan
     */
    public static function save(array $plan): void
    {
        $width = max(10, min(120, (int) ($plan['width'] ?? 40)));
        $height = max(10, min(120, (int) ($plan['height'] ?? 30)));
        $types = self::types();
        $clamp = fn ($value, float $min, float $max): float => round(max($min, min($max, (float) $value)) * 2) / 2;

        DB::transaction(function () use ($plan, $width, $height, $types, $clamp): void {
            settings()->save(['floorplan' => ['width' => $width, 'height' => $height]]);

            $keep = [];
            foreach (array_values((array) ($plan['elements'] ?? [])) as $index => $element) {
                $type = array_key_exists($element['type'] ?? '', $types) ? $element['type'] : 'zone';
                $attributes = [
                    'type' => $type,
                    'label' => filled($element['label'] ?? null) ? mb_substr((string) $element['label'], 0, 100) : null,
                    'w' => $clamp($element['w'] ?? 1, 0.5, $width),
                    'h' => $clamp($element['h'] ?? 1, 0.5, $height),
                    'color' => preg_match('/^#[0-9a-f]{6}([0-9a-f]{2})?$/i', (string) ($element['color'] ?? '')) ? $element['color'] : null,
                    'z' => $index,
                ];
                $attributes['x'] = $clamp($element['x'] ?? 0, 0, $width - $attributes['w']);
                $attributes['y'] = $clamp($element['y'] ?? 0, 0, $height - $attributes['h']);

                $model = is_numeric($element['id'] ?? null) ? FloorElement::query()->find($element['id']) : null;
                $model ? $model->update($attributes) : $model = FloorElement::query()->create($attributes);
                $keep[] = $model->id;
            }

            FloorElement::query()->whereNotIn('id', $keep ?: [0])->delete();

            foreach ((array) ($plan['tables'] ?? []) as $table) {
                $model = ShopTable::query()->find($table['id'] ?? 0);

                if (! $model) {
                    continue;
                }

                if (($table['x'] ?? null) === null || ($table['y'] ?? null) === null) {
                    $model->update(['plan_x' => null, 'plan_y' => null]);

                    continue;
                }

                $w = $clamp($table['w'] ?? 2, 1, 10);
                $h = $clamp($table['h'] ?? 2, 1, 10);
                $model->update([
                    'plan_w' => $w,
                    'plan_h' => $h,
                    'plan_x' => $clamp($table['x'], 0, $width - $w),
                    'plan_y' => $clamp($table['y'], 0, $height - $h),
                    'plan_shape' => in_array($table['shape'] ?? 'rect', ['rect', 'round'], true) ? $table['shape'] : 'rect',
                ]);
            }
        });
    }

    /**
     * A starting layout that matches how the hall is used: stage at the top, row A nearest to it, number 1 on the
     * left, a corridor in the middle, the technical tower and the entrance at the back, the bar on the side.
     *
     * @return array{width: int, height: int, elements: list<array<string, mixed>>, tables: list<array<string, mixed>>}
     */
    public static function suggestion(): array
    {
        $tables = ShopTable::query()->ordered()->get();
        $rows = $tables->groupBy('row');
        $maxPerRow = max(1, (int) $tables->max('number'));
        $tableW = 2.5;
        $gap = 1;
        $corridorW = 3;
        $leftCount = (int) ceil($maxPerRow / 2);
        $rowsWidth = $maxPerRow * ($tableW + $gap) + $corridorW;
        $width = (int) max(30, ceil($rowsWidth + 12));
        $startX = (int) round(($width - $rowsWidth) / 2);
        $rowGap = 3.5;
        $top = 7;
        $height = (int) max(26, ceil($top + $rows->count() * $rowGap + 9));
        $corridorX = $startX + $leftCount * ($tableW + $gap) + 0.5;

        $placed = [];
        foreach ($rows->values() as $rowIndex => $rowTables) {
            foreach ($rowTables as $table) {
                $offset = ($table->number - 1) * ($tableW + $gap) + ($table->number > $leftCount ? $corridorW : 0);
                $placed[] = ['id' => $table->id, 'label' => $table->label(), 'name' => $table->name, 'active' => $table->is_active,
                    'x' => $startX + $offset, 'y' => $top + $rowIndex * $rowGap, 'w' => $tableW, 'h' => 2, 'shape' => 'rect'];
            }
        }

        $back = $top + $rows->count() * $rowGap + 1.5;

        return [
            'width' => $width,
            'height' => $height,
            'elements' => [
                ['id' => null, 'type' => 'stage', 'label' => (string) settings()->get('shop.stage_label', 'Podium'), 'x' => round(($width - 24) / 2), 'y' => 1, 'w' => 24, 'h' => 4, 'color' => null],
                ['id' => null, 'type' => 'corridor', 'label' => 'Gang', 'x' => $corridorX, 'y' => $top - 1, 'w' => $corridorW - 1, 'h' => $back - $top + 1, 'color' => null],
                ['id' => null, 'type' => 'tower', 'label' => (string) settings()->get('shop.back_label', 'Technische toren'), 'x' => $corridorX - 1.5, 'y' => $back + 0.5, 'w' => 5, 'h' => 3, 'color' => null],
                ['id' => null, 'type' => 'bar', 'label' => 'Bar', 'x' => $width - 4, 'y' => $back - 6, 'w' => 3, 'h' => 8, 'color' => null],
                ['id' => null, 'type' => 'door', 'label' => 'Ingang', 'x' => 2, 'y' => $height - 1, 'w' => 4, 'h' => 1, 'color' => null],
                ['id' => null, 'type' => 'exit', 'label' => 'Nooduitgang', 'x' => 0, 'y' => 8, 'w' => 1, 'h' => 3, 'color' => null],
            ],
            'tables' => $placed,
        ];
    }
}
