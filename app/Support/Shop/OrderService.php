<?php

namespace App\Support\Shop;

use App\Models\Order;
use App\Models\Product;
use App\Models\ShopTable;
use App\Models\User;
use App\Support\Security\IpBlocker;
use App\Support\Payments\PaymentFailed;
use App\Support\Payments\Payments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Places bar orders (checking stock inside a transaction), handles online payments and closing orders.
 * Stock goes down when the order is placed and comes back when an online payment fails or expires.
 */
class OrderService
{
    public function __construct(private Payments $payments) {}

    public static function isOpen(): bool
    {
        return (bool) settings()->get('shop.open', true);
    }

    /**
     * @param  array<int|string, int|string>  $quantities  product id => quantity
     */
    public function place(ShopTable $table, string $name, array $quantities, string $method, ?string $note = null, ?string $ip = null): Order
    {
        if (! self::isOpen()) {
            throw ValidationException::withMessages(['order' => (string) settings()->get('shop.closed_title')]);
        }

        // One guest cannot flood the bar: a maximum of open orders per IP address.
        $maxOpen = (int) settings()->get('shop.max_open_per_ip', 5);
        if ($ip && $maxOpen > 0 && Order::query()->where('ip', $ip)->whereIn('status', [Order::OPEN, Order::PENDING])->count() >= $maxOpen) {
            throw ValidationException::withMessages(['order' => "Je hebt al {$maxOpen} bestellingen die nog niet gebracht zijn. Wacht even tot die er zijn."]);
        }

        if ($method !== 'bonnen' && ! array_key_exists($method, $this->payments->available())) {
            throw ValidationException::withMessages(['payment_method' => 'Deze betaalmethode is niet beschikbaar.']);
        }

        $max = max(1, (int) settings()->get('shop.max_quantity', 20));
        $quantities = collect($quantities)
            ->mapWithKeys(fn ($quantity, $id): array => [(int) $id => min($max, max(0, (int) $quantity))])
            ->filter();

        if ($quantities->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Je bestelling is leeg.']);
        }

        return DB::transaction(function () use ($table, $name, $quantities, $method, $note, $ip): Order {
            $products = Product::query()->with('category')->whereKey($quantities->keys())->lockForUpdate()->get()->keyBy('id');
            $problems = [];

            foreach ($quantities as $id => $quantity) {
                $product = $products->get($id);

                if (! $product || ! $product->isOrderable() || ($product->category && ! $product->category->is_active)) {
                    $problems[] = ($product?->name ?? 'Een product').' is niet meer beschikbaar.';
                } elseif ($product->available() !== null && $quantity > $product->available()) {
                    $problems[] = "Van {$product->name} zijn er nog maar {$product->available()}.";
                }
            }

            if ($problems !== []) {
                throw ValidationException::withMessages(['items' => $problems]);
            }

            $total = $quantities->reduce(fn (float $sum, int $quantity, int $id): float => $sum + (float) $products[$id]->price * $quantity, 0.0);
            $online = $method !== 'bonnen';

            $order = Order::query()->create([
                'number' => $this->nextNumber(),
                'token' => Str::random(40),
                'shop_table_id' => $table->id,
                'table_label' => $table->label(),
                'customer_name' => mb_substr(trim($name), 0, 100),
                'note' => filled($note) ? mb_substr(trim($note), 0, 500) : null,
                'payment_method' => $method,
                'payment_status' => $online ? 'pending' : 'unpaid',
                'status' => $online ? Order::PENDING : Order::OPEN,
                'total' => $total,
                'total_eur' => round($total * (float) settings()->get('shop.bon_value', 1), 2),
                'ip' => $ip,
            ]);

            foreach ($quantities as $id => $quantity) {
                $product = $products[$id];
                $order->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category?->name,
                    'price' => $product->price,
                    'quantity' => $quantity,
                ]);

                if ($product->track_stock) {
                    $product->stock = max(0, (int) $product->stock - $quantity);
                    $product->save();
                }
            }

            return $order;
        });
    }

    /**
     * Number shown to the guest and the bar: day-based, e.g. "0412-017" (17th order of 4 December).
     */
    private function nextNumber(): string
    {
        $prefix = now()->format('dm').'-';
        $last = Order::query()->where('number', 'like', $prefix.'%')->lockForUpdate()->max('number');
        $next = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Starts the hosted checkout and returns the URL to send the guest to.
     */
    public function startCheckout(Order $order): string
    {
        $gateway = $this->payments->gateway($order->payment_method);
        $checkout = $gateway->createCheckout(
            (float) $order->total_eur,
            settings()->siteName().' - bestelling '.$order->number.' (tafel '.$order->table_label.')',
            $order->number,
            $order->publicUrl().'?betaald=1',
            $order->publicUrl().'?geannuleerd=1',
        );

        $order->update(['provider_reference' => $checkout['reference'], 'checkout_url' => $checkout['url']]);

        return $checkout['url'];
    }

    /**
     * Asks the provider for the status of a pending online order: paid → onto the order board, failed → cancelled.
     */
    public function sync(Order $order): Order
    {
        if ($order->status !== Order::PENDING || blank($order->provider_reference)) {
            return $order;
        }

        try {
            $transaction = $this->payments->gateway($order->payment_method)->checkoutTransaction($order->provider_reference);
        } catch (PaymentFailed) {
            $this->cancel($order);

            return $order->refresh();
        } catch (Throwable $exception) {
            report($exception);

            return $order;
        }

        if ($transaction !== null) {
            $order->update([
                'payment_status' => 'paid',
                'provider_transaction' => $transaction,
                'paid_at' => now(),
                'status' => Order::OPEN,
                // The board shows orders by creation time; a late payment should not end up at the bottom.
                'created_at' => now(),
            ]);
        }

        return $order->refresh();
    }

    /**
     * An unpaid online order: puts the stock back.
     */
    public function cancel(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::query()->lockForUpdate()->find($order->id);

            if (! $order || $order->status !== Order::PENDING) {
                return;
            }

            foreach ($order->items()->with('product')->get() as $item) {
                if ($item->product?->track_stock) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            $order->update(['status' => Order::CANCELLED, 'payment_status' => 'failed']);
        });
    }

    /**
     * Pending online orders older than 45 minutes: checked once more, then cancelled.
     */
    public function expireStale(): int
    {
        $count = 0;

        Order::query()->where('status', Order::PENDING)->where('created_at', '<', now()->subMinutes(45))->each(function (Order $order) use (&$count): void {
            if ($this->sync($order)->status === Order::PENDING) {
                $this->cancel($order);
                $count++;
            }
        });

        return $count;
    }

    /**
     * Cancels an order by hand (asked by the guest, excessive, abuse...). The order stays in the history,
     * leaves the board and the statistics, and its stock is put back. Optionally refunds an online payment
     * and blocks the guest's IP address.
     *
     * @return list<string> warnings to show (e.g. a refund that failed)
     */
    public function cancelByStaff(Order $order, string $reason, ?string $note, ?User $by, bool $refund = false, bool $blockIp = false): array
    {
        $warnings = [];

        if ($order->status === Order::CANCELLED) {
            return $warnings;
        }

        if ($refund && $order->isPaidOnline() && ! $order->refunded_at) {
            try {
                $this->payments->gateway($order->payment_method)->refund((string) $order->provider_transaction);
                $order->refunded_at = now();
            } catch (Throwable $exception) {
                report($exception);
                $warnings[] = 'Terugbetalen mislukt ('.$exception->getMessage().'). Betaal terug via het dashboard van '.($order->payment_method === 'stripe' ? 'Stripe' : 'SumUp').'.';
            }
        } elseif ($order->isPaidOnline() && ! $refund) {
            $warnings[] = 'Deze bestelling was online betaald en is niet terugbetaald.';
        }

        DB::transaction(function () use ($order, $reason, $note, $by): void {
            $locked = Order::query()->lockForUpdate()->find($order->id);

            foreach ($locked->items()->with('product')->get() as $item) {
                if ($item->product?->track_stock) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            $locked->forceFill([
                'status' => Order::CANCELLED,
                'cancel_reason' => array_key_exists($reason, Order::CANCEL_REASONS) ? $reason : 'other',
                'cancel_note' => filled($note) ? mb_substr($note, 0, 500) : null,
                'cancelled_at' => now(),
                'cancelled_by' => $by?->id,
                'refunded_at' => $order->refunded_at,
                'payment_status' => $order->refunded_at ? 'refunded' : $locked->payment_status,
            ])->save();
        });

        if ($blockIp && filled($order->ip)) {
            if ($order->ip === request()->ip()) {
                $warnings[] = 'Het IP-adres is niet geblokkeerd: het is je eigen adres.';
            } else {
                app(IpBlocker::class)->block($order->ip, 'Bestelling '.$order->number.': '.(Order::CANCEL_REASONS[$reason] ?? $reason), 1440, $by);
            }
        }

        $order->refresh();

        return $warnings;
    }

    public function close(Order $order, ?int $userId): void
    {
        if ($order->status === Order::OPEN) {
            $order->update(['status' => Order::CLOSED, 'closed_at' => now(), 'closed_by' => $userId]);
        }
    }

    public function reopen(Order $order): void
    {
        if ($order->status === Order::CLOSED) {
            $order->update(['status' => Order::OPEN, 'closed_at' => null, 'closed_by' => null]);
        }
    }
}
