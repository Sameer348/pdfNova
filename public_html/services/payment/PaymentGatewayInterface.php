<?php
/**
 * Contract for payment providers (Stripe, Paddle, ...).
 *
 * No provider is connected yet: PaymentFactory returns NullGateway, and the
 * pricing/subscription pages say upgrades are arranged by contacting us.
 * See docs/PAYMENTS.md for connecting Stripe.
 */

declare(strict_types=1);

namespace PDFNova\Payment;

interface PaymentGatewayInterface
{
    /** True when real checkout is possible. */
    public function isAvailable(): bool;

    public function name(): string;

    /**
     * Start a checkout for a plan and return the URL to send the visitor to.
     * @param array $user  current user row
     * @param array $plan  plans row
     * @param string $cycle 'monthly' | 'yearly'
     */
    public function createCheckout(array $user, array $plan, string $cycle, string $successUrl, string $cancelUrl): string;

    /**
     * Verify and process a webhook from the provider (payment succeeded,
     * subscription renewed/cancelled). Must verify the signature first.
     */
    public function handleWebhook(string $payload, string $signatureHeader): void;

    /** URL of the provider's self-service billing portal, if any. */
    public function billingPortalUrl(array $user, string $returnUrl): ?string;
}
