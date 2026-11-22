<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Payments\StripeGateway;
use App\Support\Shop\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Payment notifications. The payload's status is never trusted: the order is synced from the provider's API.
 */
class PaymentWebhookController extends Controller
{
    public function stripe(Request $request, StripeGateway $stripe, OrderService $orders): Response
    {
        if (! $stripe->verifyWebhook($request->getContent(), $request->header('Stripe-Signature'))) {
            return response('Invalid signature', 400);
        }

        $session = (array) $request->json('data.object', []);
        $order = Order::query()->where('payment_method', 'stripe')->where('provider_reference', $session['id'] ?? '-')->first();

        if ($order) {
            $orders->sync($order);
        }

        return response('ok');
    }

    public function sumup(Request $request, OrderService $orders): Response
    {
        $order = Order::query()->where('payment_method', 'sumup')->where('provider_reference', (string) $request->input('id', '-'))->first();

        if ($order) {
            $orders->sync($order);
        }

        return response('ok');
    }
}
