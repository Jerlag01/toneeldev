<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ShopCategory;
use App\Models\ShopTable;
use App\Support\Payments\Payments;
use App\Support\Shop\OrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Ordering at the table: the QR code on the table opens /bestel?tafel=A3.
 */
class ShopController extends Controller
{
    public function index(Request $request, Payments $payments): View
    {
        $table = ShopTable::findByLabel($request->query('tafel'));
        $table = $table?->is_active ? $table : null;

        if (! OrderService::isOpen()) {
            return view('shop.closed', ['table' => $table]);
        }

        $categories = ShopCategory::query()->where('is_active', true)->orderBy('sort')->orderBy('name')->get();
        $products = Product::query()->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('shop_category_id')->orWhereIn('shop_category_id', $categories->pluck('id')))
            ->orderBy('sort')->orderBy('name')->get();

        $methods = ['bonnen' => (string) settings()->get('shop.bonnen_label')];
        foreach ($payments->available() as $key => $gateway) {
            $methods[$key] = $gateway->label();
        }

        return view('shop.index', [
            'table' => $table,
            'tables' => ShopTable::query()->where('is_active', true)->ordered()->get(),
            'categories' => $categories,
            'products' => $products,
            'methods' => $methods,
            'menu' => [
                'categories' => $categories->map(fn (ShopCategory $category): array => ['id' => $category->id, 'name' => $category->name])->values(),
                'products' => $products->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'category' => $product->shop_category_id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => (float) $product->price,
                    'image' => media_url($product->image),
                    'available' => $product->isOrderable(),
                    'max' => $product->available(),
                ])->values(),
            ],
        ]);
    }

    public function store(Request $request, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'table' => ['required', 'string', 'max:10'],
            'name' => [settings()->get('shop.require_name', true) ? 'required' : 'nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'string', 'in:bonnen,stripe,sumup'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['integer', 'min:0', 'max:999'],
        ], [
            'name.required' => 'Vul je naam in, zodat we weten voor wie het is.',
            'table.required' => 'Kies je tafel.',
            'items.required' => 'Je bestelling is leeg.',
        ]);

        $table = ShopTable::findByLabel($data['table']);

        if (! $table || ! $table->is_active) {
            throw ValidationException::withMessages(['table' => 'Deze tafel bestaat niet. Scan de QR-code op je tafel opnieuw.']);
        }

        $order = $orders->place($table, (string) ($data['name'] ?? ''), $data['items'], $data['payment_method'], settings()->get('shop.allow_note', true) ? ($data['note'] ?? null) : null, $request->ip());

        if ($order->isOnline()) {
            try {
                return response()->json(['redirect' => $orders->startCheckout($order)]);
            } catch (Throwable $exception) {
                report($exception);
                $orders->cancel($order);

                throw ValidationException::withMessages(['payment_method' => 'Online betalen lukt nu niet. Kies een andere betaalmethode.']);
            }
        }

        return response()->json(['redirect' => $order->publicUrl()]);
    }

    public function order(Request $request, string $token, OrderService $orders): View
    {
        $order = Order::query()->with('items')->where('token', $token)->firstOrFail();

        if ($order->status === Order::PENDING) {
            $order = $orders->sync($order);
        }

        return view('shop.order', ['order' => $order, 'cancelled' => $request->boolean('geannuleerd')]);
    }

    /**
     * Small JSON status for the confirmation page, which refreshes itself while the order is not handled yet.
     */
    public function status(string $token, OrderService $orders): JsonResponse
    {
        $order = Order::query()->where('token', $token)->firstOrFail();

        if ($order->status === Order::PENDING) {
            $order = $orders->sync($order);
        }

        return response()->json(['status' => $order->status, 'payment_status' => $order->payment_status]);
    }

    public function retry(string $token, OrderService $orders): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $order = Order::query()->where('token', $token)->where('status', Order::PENDING)->firstOrFail();

        return redirect()->away($order->checkout_url ?: $orders->startCheckout($order));
    }
}
