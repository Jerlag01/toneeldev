<?php

namespace App\Support\Payments;

/**
 * A hosted online checkout (Stripe Checkout or SumUp hosted checkout) for one amount.
 */
interface PaymentGateway
{
    public function key(): string;

    public function label(): string;

    public function isConfigured(): bool;

    /**
     * @return array{reference: string, url: string}
     */
    public function createCheckout(float $amount, string $description, string $reference, string $returnUrl, string $cancelUrl): array;

    /**
     * The provider's transaction id when paid, null while not (yet). Throws PaymentFailed when it failed or expired.
     */
    public function checkoutTransaction(string $reference): ?string;

    /**
     * Refunds the full amount of a paid transaction.
     */
    public function refund(string $transaction): void;
}
