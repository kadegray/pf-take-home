# Pixelforge

A Laravel + Vue (Inertia) app that lets signed-in users generate images through
Pixelforge, a (fictional) async AI image-generation provider. Built as a
take-home assessment for PromptEdit.

**Live:** [https://pf-take-home.ibexel.com/](https://pf-take-home.ibexel.com/)

## What it does

- **Generate** an image from a prompt, size, quality, and optional reference
  images, with a live, server-computed cost estimate before you submit.
- **History** shows each generation's live status (queued → processing →
  succeeded/failed), its estimated and final cost, and the resulting image.
- **Credits**: every account starts with a small balance; generating holds the
  estimate up front and settles to the real cost once Pixelforge resolves it.
  A "buy credits" page tops up the balance (fake, no real payment).
- **Admin report** (for admin users): spend, provider cost, and profit by day
  and by user, reconcilable against Pixelforge's own `GET /v1/usage`.

## Why it's built this way

Pixelforge's cost is only fully known after a generation completes, and it
comes in fractions of a cent — so this app never does money math in floats.
`Pricing` converts token usage to cost using `bcmath` on integer **micros**
(1,000,000ths of a dollar), round-half-up throughout, with the 25% markup
applied to the already-rounded provider cost.

Pixelforge delivers results as webhooks, and webhooks can arrive twice or out
of order. `WebhookController` handles both:

- Every event is recorded under a **database-level unique constraint** on its
  event id, so a redelivered event — even concurrently — is a no-op rather
  than being double-processed.
- Generation status moves through a small guarded state machine
  (`queued → processing → succeeded|failed`); once a generation is terminal,
  later/stale events for it are ignored instead of overwriting the result.
- Failed generations always cost $0, on both the webhook path and the
  synchronous path where Pixelforge rejects a request outright.

Credits use a **hold-then-settle** ledger (`CreditLedger`): submitting a
generation holds the estimate under a locked user row (so concurrent
submissions can't overdraw a balance), and the hold is reconciled to the real
cost — refunded on failure, adjusted by the delta on success — once the
generation resolves.

## Local setup

Requires PHP 8.3+, Node (see `.nvmrc`), and Postgres.

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
# fill in PIXELFORGE_API_KEY / PIXELFORGE_WEBHOOK_SECRET and your DB_* values

php artisan migrate

npm run dev        # Vite dev server
php artisan serve  # or your preferred local server
```

Local mail (registration/verification) is caught by [Mailpit](https://github.com/axllent/mailpit)
on `127.0.0.1:1025`; the Mailpit UI defaults to `http://127.0.0.1:8025`.

Pixelforge needs its own API key and webhook signing secret per environment —
local and deployed each have their own webhook URL/secret pair.

## Tests

```bash
php artisan test
```

Covers pricing/rounding correctness (`tests/Unit/PricingTest.php`), webhook
signature verification, idempotency, and out-of-order delivery
(`tests/Feature/WebhookTest.php`), the credit ledger
(`tests/Feature/CreditLedgerTest.php`, `GenerationBalanceTest.php`), and the
admin report (`tests/Feature/AdminReportTest.php`).
