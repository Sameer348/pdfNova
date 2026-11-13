# Payments (Stripe later)

PDFNova ships **without** a live payment integration, as requested. Today:

- `/pricing` and `/dashboard/subscription` show the plans from the `plans`
  table and offer **"Contact us to upgrade"** — no fake checkout.
- An admin upgrades a user by adding a row to `subscriptions` (the admin
  panel in Phase 14 does this). `current_plan()` picks it up immediately and
  the user's limits change.

The adapter lives in `services/payment/`:

| File | Role |
|---|---|
| `PaymentGatewayInterface.php` | Contract: `isAvailable()`, `createCheckout()`, `handleWebhook()`, `billingPortalUrl()` |
| `NullGateway.php` | Active now — reports "not available" |
| `PaymentFactory.php` | Picks the gateway from `config('payment.provider')` |

## Connecting Stripe (outline)

1. **Install the SDK** on your computer and upload `vendor/`:
   ```bash
   composer require stripe/stripe-php
   ```
2. **Create products/prices** in the Stripe Dashboard for Pro and Business,
   monthly and yearly. Store each price ID — e.g. add columns
   `stripe_price_monthly` / `stripe_price_yearly` to `plans` via a migration.
3. **Create `services/payment/StripeGateway.php`** implementing the interface:
   - `createCheckout()` → `\Stripe\Checkout\Session::create([...])` with
     `mode => 'subscription'`, the plan's price ID, `customer_email`,
     `client_reference_id => $user['public_id']`, and the success/cancel URLs;
     return `$session->url`.
   - `handleWebhook()` → `\Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret)`
     (this verifies the signature — never skip it), then on
     `checkout.session.completed`, `customer.subscription.updated` and
     `customer.subscription.deleted` insert/update the user's `subscriptions`
     row (`gateway = 'stripe'`, `gateway_subscription_id`, `status`,
     `current_period_end`) and record `payments` rows for invoices.
   - `billingPortalUrl()` → `\Stripe\BillingPortal\Session::create([...])->url`.
4. **Register it** in `PaymentFactory::make()`:
   ```php
   'stripe' => new StripeGateway((string) config('payment.stripe_secret'), (string) config('payment.stripe_webhook')),
   ```
5. **Add two endpoints**: `api/checkout.php` (POST + CSRF + `require_login()`,
   calls `createCheckout()` and redirects) and `api/stripe-webhook.php`
   (no CSRF — Stripe calls it; the signature check protects it; read the raw
   body with `file_get_contents('php://input')`).
6. **Config** (`includes/config.php`, never committed):
   ```php
   'payment' => ['provider' => 'stripe', 'stripe_secret' => 'sk_live_...', 'stripe_webhook' => 'whsec_...'],
   ```
7. **Test with Stripe test keys** (`sk_test_...`) and the Stripe CLI
   (`stripe listen --forward-to https://yourdomain.com/api/stripe-webhook.php`)
   before switching to live keys.

When `isAvailable()` returns true, the pricing and subscription pages switch
from "Contact us" to "Upgrade" automatically.
