<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bar order from a table. Statuses: pending_payment (waiting for an online payment, not shown to the bar yet),
 * open (on the order board), closed (handled; kept for the statistics), cancelled (online payment failed or expired).
 */
class Order extends Model
{
    public const PENDING = 'pending_payment';

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public const CANCELLED = 'cancelled';

    public const CANCEL_REASONS = [
        'customer' => 'Gevraagd door de klant',
        'excessive' => 'Overdreven / onrealistische bestelling',
        'abuse' => 'Misbruik (grap, spam, valse naam)',
        'unavailable' => 'Product niet meer beschikbaar',
        'duplicate' => 'Dubbele bestelling',
        'other' => 'Andere reden',
    ];

    public const METHODS = ['bonnen' => 'Bonnen', 'stripe' => 'Online (Stripe)', 'sumup' => 'Online (SumUp)'];

    protected $fillable = [
        'number', 'token', 'shop_table_id', 'table_label', 'customer_name', 'note', 'payment_method', 'payment_status', 'status',
        'total', 'total_eur', 'provider_reference', 'provider_transaction', 'checkout_url', 'paid_at', 'closed_at', 'closed_by', 'ip',
        'cancel_reason', 'cancel_note', 'cancelled_at', 'cancelled_by', 'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'total_eur' => 'decimal:2',
            'paid_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(ShopTable::class, 'shop_table_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function cancelReasonLabel(): ?string
    {
        if ($this->status !== self::CANCELLED) {
            return null;
        }

        return self::CANCEL_REASONS[$this->cancel_reason] ?? 'Online betaling mislukt of verlopen';
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::OPEN);
    }

    /**
     * Orders that count as sold (statistics): on the board or handled.
     */
    public function scopeSold(Builder $query): void
    {
        $query->whereIn('status', [self::OPEN, self::CLOSED]);
    }

    public function isOnline(): bool
    {
        return $this->payment_method !== 'bonnen';
    }

    public function isPaidOnline(): bool
    {
        return $this->isOnline() && $this->payment_status === 'paid';
    }

    public function publicUrl(): string
    {
        return route('shop.order', $this->token);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->payment_method] ?? $this->payment_method;
    }
}
