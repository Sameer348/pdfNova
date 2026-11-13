<?php
/** Used until a payment provider is configured: no checkout is offered. */

declare(strict_types=1);

namespace PDFNova\Payment;

final class NullGateway implements PaymentGatewayInterface
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'none';
    }

    public function createCheckout(array $user, array $plan, string $cycle, string $successUrl, string $cancelUrl): string
    {
        throw new \LogicException('Online payment is not configured.');
    }

    public function handleWebhook(string $payload, string $signatureHeader): void
    {
        throw new \LogicException('Online payment is not configured.');
    }

    public function billingPortalUrl(array $user, string $returnUrl): ?string
    {
        return null;
    }
}
