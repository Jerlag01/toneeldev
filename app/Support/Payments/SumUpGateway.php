<?php

namespace App\Support\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SumUp hosted checkout via the REST API. SumUp webhooks are unsigned, so the status is always fetched from the API.
 *
 * @see https://developer.sumup.com/api/checkouts
 */
class SumUpGateway implements PaymentGateway
{
    private const API = 'https://api.sumup.com/v0.1/';

    public function key(): string
    {
        return 'sumup';
    }

    public function label(): string
    {
        return 'Online betalen via SumUp';
    }

    public function isConfigured(): bool
    {
        return (bool) settings()->get('payments.sumup_enabled')
            && filled(settings()->secret('payments.sumup_api_key'))
            && filled(settings()->get('payments.sumup_merchant_code'));
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()->withToken((string) settings()->secret('payments.sumup_api_key'))->baseUrl(self::API)->timeout(20);
    }

    public function createCheckout(float $amount, string $description, string $reference, string $returnUrl, string $cancelUrl): array
    {
        $response = $this->client()->post('checkouts', [
            'checkout_reference' => $reference.'-'.now()->format('His'),
            'amount' => round($amount, 2),
            'currency' => 'EUR',
            'merchant_code' => settings()->get('payments.sumup_merchant_code'),
            'description' => $description,
            'return_url' => route('webhooks.sumup'),
            'redirect_url' => $returnUrl,
            'hosted_checkout' => ['enabled' => true],
        ]);

        if ($response->failed() || blank($response->json('hosted_checkout_url'))) {
            throw new RuntimeException('SumUp: '.($response->json('message') ?? $response->json('error_message') ?? $response->status()));
        }

        return ['reference' => (string) $response->json('id'), 'url' => (string) $response->json('hosted_checkout_url')];
    }

    public function checkoutTransaction(string $reference): ?string
    {
        $checkout = $this->client()->get('checkouts/'.$reference)->throw()->json();
        $status = strtoupper((string) ($checkout['status'] ?? ''));

        if ($status === 'PAID') {
            $transaction = collect($checkout['transactions'] ?? [])->firstWhere('status', 'SUCCESSFUL') ?? ($checkout['transactions'][0] ?? []);

            return (string) ($transaction['id'] ?? $checkout['transaction_id'] ?? $reference);
        }

        if ($status === 'FAILED' || $status === 'EXPIRED') {
            throw new PaymentFailed('De SumUp-betaling is mislukt of verlopen.');
        }

        return null;
    }

    public function refund(string $transaction): void
    {
        $response = $this->client()->post('me/refund/'.$transaction);

        if ($response->failed()) {
            throw new RuntimeException('SumUp: '.($response->json('message') ?? $response->status()));
        }
    }
}
