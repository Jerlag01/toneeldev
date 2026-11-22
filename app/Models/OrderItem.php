<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an order. Name, category and price are copied, so later product changes do not alter old orders.
 */
class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'name', 'category', 'price', 'quantity'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'quantity' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
