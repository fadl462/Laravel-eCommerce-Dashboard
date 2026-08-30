<?php

namespace App\Services\Payments;

use InvalidArgumentException;

/**
 * The single entry point the rest of the app uses to talk to "a payment
 * gateway" without knowing which one. Adding Apple Pay/Mollie/a local
 * gateway later means writing one new class + one new line in $gateways —
 * nothing in the controllers or Order model changes.
 */
class PaymentManager
{
    protected array $gateways = [
        'stripe' => StripeGatewayService::class,
        'paypal' => PayPalGatewayService::class,
        'bank_transfer' => BankTransferGatewayService::class,
    ];

    public function gateway(string $name): PaymentGatewayInterface
    {
        if (! isset($this->gateways[$name])) {
            throw new InvalidArgumentException("Unknown payment gateway [{$name}].");
        }

        return app($this->gateways[$name]);
    }

    public function availableGateways(): array
    {
        return array_keys($this->gateways);
    }
}
