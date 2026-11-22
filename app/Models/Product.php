<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something on the menu of the bar. The price is in bonnen; online payments use settings shop.bon_value per bon.
 * With track_stock the stock goes down per order and the product becomes sold out at 0.
 */
class Product extends Model
{
    protected $fillable = ['shop_category_id', 'name', 'description', 'image', 'price', 'is_active', 'sold_out', 'track_stock', 'stock', 'sort'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'sold_out' => 'boolean',
            'track_stock' => 'boolean',
            'stock' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ShopCategory::class, 'shop_category_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeOrderable(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('sold_out', false)
            ->where(fn (Builder $query) => $query->where('track_stock', false)->orWhere('stock', '>', 0))
            ->where(fn (Builder $query) => $query->whereNull('shop_category_id')->orWhereHas('category', fn (Builder $query) => $query->where('is_active', true)));
    }

    public function isOrderable(): bool
    {
        return $this->is_active && ! $this->sold_out && (! $this->track_stock || (int) $this->stock > 0);
    }

    /**
     * How many may still be ordered (null = no limit).
     */
    public function available(): ?int
    {
        return $this->track_stock ? max(0, (int) $this->stock) : null;
    }
}
