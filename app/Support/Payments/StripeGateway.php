<?php

namespace App\Support\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe Checkout (hosted page: Bancontact, cards, Payconiq...) via the REST API, no SDK.
 *
 * @see https://docs.stripe.com/api/checkout/sessions
 */
class StripeGateway implements PaymentGateway
{
    private const API = 'https://api.stripe.com/v1/';

    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Online betalen (Bancontact, kaart)';
    }

    public function isConfigured(): bool
    {
        return (bool) settings()->get('payments.stripe_enabled') && filled(settings()->secret('payments.stripe_secret'));
    }

    private function client(): PendingRequest
    {
        return Http::asForm()->withBasicAuth((string) settings()->secret('payments.stripe_secret'), '')->baseUrl(self::API)->timeout(20);
    }

    public function createCheckout(float $amount, string $description, string $reference, string $returnUrl, string $cancelUrl): array
    {
        $response = $this->client()->post('checkout/sessions', [
            'mode' => 'payment',
            'locale' => 'nl',
            'client_reference_id' => $reference,
            'metadata[order]' => $reference,
            'success_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'expires_at' => now()->addMinutes(31)->timestamp,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => 'eur',
            'line_items[0][price_data][unit_amount]' => (int) round($amount * 100),
            'line_items[0][price_data][product_data][name]' => $description,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Stripe: '.($response->json('error.message') ?? $response->status()));
        }

        return ['reference' => (string) $response->json('id'), 'url' => (string) $response->json('url')];
    }

    public function checkoutTransaction(string $reference): ?string
    {
        $session = $this->client()->get('checkout/sessions/'.$reference)->throw()->json();

        if (($session['payment_status'] ?? null) === 'paid') {
            return (string) ($session['payment_intent'] ?? $reference);
        }

        if (($session['status'] ?? null) === 'expired') {
            throw new PaymentFailed('De betaalsessie is verlopen.');
        }

        return null;
    }

    public function refund(string $transaction): void
    {
        $response = $this->client()->post('refunds', ['payment_intent' => $transaction]);

        if ($response->failed()) {
            throw new RuntimeException('Stripe: '.($response->json('error.message') ?? $response->status()));
        }
    }

    /**
     * Checks the Stripe-Signature header (HMAC-SHA256 of "timestamp.payload", max. 5 minutes old).
     */
    public function verifyWebhook(string $payload, ?string $header): bool
    {
        $secret = settings()->secret('payments.stripe_webhook_secret');

        if (blank($secret) || blank($header)) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            $parts[$key][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);

        if ($timestamp === 0 || abs(time() - $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($parts['v1'] ?? [] as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                return true;
            }
        }

        return false;
    }
}
