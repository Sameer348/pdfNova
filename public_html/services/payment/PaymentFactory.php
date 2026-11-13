<?php
/** Builds the configured payment gateway (config payment.provider). */

declare(strict_types=1);

namespace PDFNova\Payment;

final class PaymentFactory
{
    public static function make(): PaymentGatewayInterface
    {
        $provider = (string) config('payment.provider', 'none');
        // Register real gateways here once implemented, e.g.:
        //   'stripe' => new StripeGateway(config('payment.stripe_secret'), config('payment.stripe_webhook')),
        return match ($provider) {
            default => new NullGateway(),
        };
    }
}
