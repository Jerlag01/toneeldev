<?php

namespace App\Support\Payments;

use InvalidArgumentException;

class Payments
{
    public function gateway(string $key): PaymentGateway
    {
        return match ($key) {
            'stripe' => app(StripeGateway::class),
            'sumup' => app(SumUpGateway::class),
            default => throw new InvalidArgumentException("{$key} is geen online betaalmethode."),
        };
    }

    /**
     * Online providers that are switched on and configured.
     *
     * @return array<string, PaymentGateway>
     */
    public function available(): array
    {
        $gateways = [];

        foreach (['stripe', 'sumup'] as $key) {
            $gateway = $this->gateway($key);

            if ($gateway->isConfigured()) {
                $gateways[$key] = $gateway;
            }
        }

        return $gateways;
    }
}
