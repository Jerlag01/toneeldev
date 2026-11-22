<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A table in the hall: row letter (A = front, at the stage) + number (1 = far left, seen from the hall towards the stage).
 */
class ShopTable extends Model
{
    protected $fillable = ['row', 'number', 'name', 'seats', 'is_active', 'plan_x', 'plan_y', 'plan_w', 'plan_h', 'plan_shape'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'number' => 'integer', 'seats' => 'integer',
            'plan_x' => 'float', 'plan_y' => 'float', 'plan_w' => 'float', 'plan_h' => 'float',
        ];
    }

    public function isPlaced(): bool
    {
        return $this->plan_x !== null && $this->plan_y !== null;
    }

    protected static function booted(): void
    {
        static::saving(fn (ShopTable $table) => $table->row = strtoupper(trim((string) $table->row)));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderByRaw('LENGTH(`row`)')->orderBy('row')->orderBy('number');
    }

    public function label(): string
    {
        return $this->row.$this->number;
    }

    /**
     * Finds "A3", "a 3" or "A-3".
     */
    public static function findByLabel(?string $label): ?self
    {
        if (! preg_match('/^\s*([A-Za-z]{1,5})\s*[-\s]?\s*(\d{1,3})\s*$/', (string) $label, $match)) {
            return null;
        }

        return self::query()->where('row', strtoupper($match[1]))->where('number', (int) $match[2])->first();
    }

    public function orderUrl(): string
    {
        return route('shop.index', ['tafel' => $this->label()]);
    }
}
