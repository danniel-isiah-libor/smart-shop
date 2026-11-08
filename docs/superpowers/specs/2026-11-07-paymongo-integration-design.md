# PayMongo Payment Integration — Design

Status: **DRAFT, awaiting approval.** Nothing has been built.
Date: 2026-11-07

## 1. Intent

Let customers pay for an order either **online** through PayMongo's hosted
checkout page (GCash or card) or by **cash on delivery (COD)**. Today the
cart's "Checkout" button does nothing and no order ever leaves `pending`.
This work turns the cart into a real checkout and makes `paid` mean "money
actually arrived".

Why PayMongo: it has the best-verified pricing for a small PH shop (Nov 2026,
VAT-exclusive: local card 3.125% + ₱13.39, GCash 2.23%, no monthly fee; 12% VAT
is charged on the fee, so a ₱1,000 card payment costs the shop about ₱50).
Maya Business is the fallback if PayMongo is unavailable (see §9).

## 2. Goals / non-goals / anti-goals

**Goals**
- Hosted checkout redirect for GCash and card; COD as a second option.
- Order status becomes trustworthy: only a verified PayMongo webhook marks an
  online order paid.
- Staff can see payment state in Filament and mark COD orders paid.
- Records PayMongo's fee and net amount per payment for reconciliation.

**Non-goals (v1)**
- Refunds through the API (staff refund in the PayMongo dashboard; the order is
  then cancelled manually).
- Saved cards, subscriptions, installments/BNPL, QR Ph, online banking, Maya
  wallet. (PayMongo supports them; add by extending `payment_method_types`.)
- Multi-gateway abstraction. One concrete `PayMongo` client class, no interface.
- Coupon logic changes, tax or shipping-fee rules (existing `Order` totals are
  used as they are).

**Anti-goals (a failure even if it "works")**
- Marking an order paid because the customer landed on the success URL.
- Any float arithmetic on money.
- Card data touching our server (hosted page only), or secrets in logs.
- Double-charging or double-processing when webhooks repeat or arrive late.

## 3. Constraints (from CLAUDE.md and the codebase)

- No new packages without asking. Use Laravel's `Http` client directly.
  (`luigel/laravel-paymongo` exists; not chosen, see §10.)
- Money is `decimal(10,2)`, never float. PayMongo wants integer centavos:
  convert with string/bcmath math, not `* 100` on a float.
- Order status is a PHP enum (`App\Enums\OrderStatus`). Do not edit migrations
  that already ran; add new ones.
- Order total is calculated on the `Order` model; the amount sent to PayMongo
  is `Order::total`, never a figure from the browser.
- Cart = the user's `Pending` order (`Order::cartFor`). Once an order leaves
  `Pending`, the next `cartFor` call creates a fresh cart automatically.
- Filament 5 APIs only; admin prices use `money('PHP')`; every model gets a
  factory; Pest tests; Pint + PHPStan level 7 must pass (`composer test`).
- Don't touch Fortify/auth files.

## 4. Order lifecycle

```
Pending (cart)
  ├─ choose Online ─▶ AwaitingPayment ─ webhook paid ─▶ Paid ─▶ Shipped ─▶ Delivered
  │                        └─ expired / cancelled / failed ─▶ Cancelled (stock released)
  └─ choose COD ────▶ Confirmed ──────────────────────────────▶ Shipped ─▶ Delivered
                                                     (staff marks payment received)
Cancel allowed from: AwaitingPayment, Confirmed, Paid  (not Shipped/Delivered)
```

**Rules (my calls, from the builder review):**
- `Shipped` is allowed from `Paid` or `Confirmed` only. `Cancelled` is final:
  items are not restored to a cart; the customer starts a new cart. "Retry
  payment" exists only while the order is `AwaitingPayment`.
- Stock today is only *capped* in the cart; nothing decrements it. This work
  introduces stock decrement. Orders get a nullable `stock_reserved_at`
  timestamp; cancel/expiry releases stock **only if it is set**, so orders that
  never reserved stock (old orders, admin-created) can't inflate inventory.
- Admin-created orders (Filament order form): staff pick a payment method of
  `cod` or `manual` (paid outside the app); they reserve stock through the same
  `Order` method and get `stock_reserved_at`. Staff set their status as today.
- Migration backfill for existing orders: `payment_method = null`;
  `payment_status = paid` for `paid`/`shipped`/`delivered`, else `unpaid`;
  `stock_reserved_at = null`.

**My call:** add two `OrderStatus` cases, `AwaitingPayment` and `Confirmed`
(COD placed, payment due on delivery). Keep `Paid` meaning "money received
online". Payment detail lives on separate fields/table (§5) so a COD order can
be `Delivered` with `payment_status = unpaid → paid` without overloading
`status`. Add labels/colours for both cases (the enum feeds Filament badges).

## 5. Data model (new migrations only)

- `orders`: add `payment_method` (string enum `PaymentMethod`: `online`, `cod`,
  `manual`; nullable until checkout), `stock_reserved_at` (nullable timestamp) and `payment_status` (string enum `PaymentStatus`:
  `unpaid`, `paid`, `failed`, `refunded`; default `unpaid`).
- New `payments` table + `Payment` model + factory (one row per PayMongo
  attempt): `order_id`, `provider` (`paymongo`), `provider_session_id`
  (`cs_…`, unique), `provider_payment_id` (`pay_…`, nullable, unique),
  `method_type` (`gcash`/`card`, nullable until paid), `amount`, `fee`,
  `net_amount` (all decimal 10,2), `status`, `checkout_url`, `paid_at`,
  `needs_review` (bool, default false), `review_reason` (nullable string),
  `raw` (json, last webhook payload, no secrets), timestamps.
- New `webhook_events` table (`event_id` unique, `type`, `payload` json,
  `processed_at`) for idempotency.
- `Order` gains `payments(): HasMany`; `Payment` follows the `#[Fillable]`
  attribute and `casts()` method conventions.

## 6. Customer flow

1. Cart "Checkout" → `/checkout` (auth + verified, like the cart). A Livewire
   page collects the shipping details (existing nullable `shipping_*` columns,
   required here) and the payment method (Online / COD). Shows the order
   summary from the `Order`.
2. **COD:** in one DB transaction, lock the product rows, re-check stock,
   decrement stock, set `payment_method = cod`, `status = Confirmed`. Redirect
   to the order-confirmation page.
3. **Online:** same transaction (stock reserved, `status = AwaitingPayment`),
   then call PayMongo `POST /v2/checkout_sessions` with `payment_method_types`
   `["gcash","card"]`, `reference_number = order_number`, `success_url` →
   confirmation page, `cancel_url` → cart page, `metadata.order_id`. Store a
   `payments` row and redirect to the returned `checkout_url`. If the API call
   fails, roll back the reservation and show an error; the cart is untouched.
4. Customer pays on PayMongo. The **webhook** (not the redirect) completes the
   order. The confirmation page shows the order's real state and refreshes
   (poll every few seconds, up to about a minute) while it is still
   `AwaitingPayment`, then explains "we'll email you" if still unresolved.
5. Cancel/back from the hosted page: order stays `AwaitingPayment` until
   expiry; the order page offers "Retry payment" (creates a new session) and
   "Cancel order".

Line items sent to PayMongo: one per `OrderItem` (name, unit price, quantity),
plus a line for shipping/tax if non-zero. Nothing in the app sets
`discount`, `tax` or `shipping_fee` today (coupons exist only as an admin
resource), so these are 0 for customer carts and the checkout page has no
coupon field; the cases below only matter if staff set them in Filament.
**PayMongo line items can't be
negative, so if `discount > 0` send a single line "Order {order_number}" for
the full total.** The sum must equal `Order::total`.

## 7. Webhook (source of truth)

- Route `POST /webhooks/paymongo`, outside `auth`, excluded from CSRF, rate
  limited, one invokable controller.
- First step: verify the `Paymongo-Signature` header against the **raw body**
  with the webhook secret using `hash_equals`. Header format is
  `t=<ts>,te=<test sig>,li=<live sig>`; signature =
  `HMAC-SHA256(secret, "<t>.<raw body>")`; compare `te` in test mode and `li`
  in live mode (decide by config, never by the payload); reject timestamps more
  than 5 minutes old. Invalid → `401`, nothing parsed.
  *Caveat: PayMongo's docs disagree on whether the timestamp is part of the
  signed string. Follow the webhook-setup page (above), and confirm with a real
  test-mode event before relying on it.*
- Handle `checkout_session.payment.paid` (and the failed/expired events PayMongo
  documents for sessions). Insert the event into `webhook_events` first
  (unique `event_id`); if it already exists, return `200` and do nothing.
  Delivery is at-least-once and unordered; PayMongo retries up to 12 times and
  disables a webhook after 3 consecutive failures, so respond `2xx` quickly and
  do the work in a queued job (queue driver is `database`).
- The job locates the order by `reference_number`/session id, then checks that
  the paid amount equals `Order::total` (centavos) and the currency is PHP. On
  mismatch: do **not** mark paid; flag the payment for staff review and log it.
- On success (idempotent, inside a transaction with the order row locked):
  set `Order.status = Paid`, `payment_status = paid`, store `provider_payment_id`,
  `method_type`, `amount`, `fee`, `net_amount`, `paid_at`. For a "paid"
  event on an order that is already `Cancelled` (customer paid after we
  released stock): **alert only, never auto-reinstate.** Record the payment with
  `needs_review = true` and a reason; the order stays `Cancelled`. Staff see an
  "Needs review" filter/badge on Orders and refund in the PayMongo dashboard.
  Same flag for amount mismatches.
- Email (my call, minimal): queued Mailables for "payment received" and "COD
  order confirmed" only. No mail classes exist today; the mailer is already
  configured (`MAIL_MAILER=smtp`), so use it as is. No emails for
  expiry/cancel/failure in v1. The confirmation page must not promise an email
  for unresolved payments; it says the order page will update.

## 8. Admin (Filament 5)

- Orders table/infolist: badges for `payment_method` and `payment_status`;
  prices via `money('PHP')`; new status cases appear in filters.
- Order view: read-only Payments relation manager (session id, PayMongo
  payment id, method, amount, fee, net, status).
- Action (Filament\Actions): **Mark COD as paid** (only for COD, delivered or
  shipped orders). **Cancel order** releases stock, and for a paid online order
  reminds staff to refund in the PayMongo dashboard.
- Existing shipment/status flow keeps working; `Shipped` is allowed from `Paid`
  or `Confirmed`.

## 9. Configuration and environment

- `config/services.php` → `paymongo`: `secret_key`, `webhook_secret`,
  `base_url` (default `https://api.paymongo.com`), `mode` (`test`/`live`),
  `pending_expiry_minutes` (my call: 60). Env: `PAYMONGO_SECRET_KEY`,
  `PAYMONGO_WEBHOOK_SECRET`, `PAYMONGO_MODE`. Secret key via HTTP Basic auth
  (key as username, empty password), server-side only. Never log it.
- A scheduled command (every 5 min) cancels `AwaitingPayment` orders older than
  the expiry, releases stock, and calls the session-expire endpoint
  (`POST /v1/checkout_sessions/{id}/expire`, ignoring "already paid/ongoing"
  errors).
- Local webhook testing needs a public URL (Herd share or a tunnel) registered
  as a test-mode webhook in the PayMongo dashboard.
- **Blocker to confirm first:** PayMongo's pricing page currently says "New
  sign-ups are paused while we upgrade". Needs a working merchant account, test
  keys, and GCash/card enabled before live use. Live mode also needs KYC
  activation. If PayMongo stays closed, the fallback is Maya Business
  (GCash 2.0%, card 3.50% + ₱10, "starting rates"); the design above is mostly
  gateway-agnostic except §6 step 3 and §7.

## 10. What's left to the builder

- Exact table/column names beyond §5, class layout (suggested:
  `App\Services\PayMongo` client, `App\Actions\Checkout\*`, a queued
  `ProcessPayMongoEvent` job), and the checkout page's visual design (Flux UI,
  matching the cart).
- Whether to use `luigel/laravel-paymongo` instead of the raw client. Default is
  no: it needs approval under the "no packages" rule, and the surface used here
  (create session, expire session, verify webhook) is small.
- Handling of PayMongo events beyond `paid` (failed/expired), per their current
  event list.

## 11. Testing

Pest feature tests with `Http::fake()`; no real network.
- Checkout: COD happy path; online happy path (session request body: centavo
  amounts, reference number, line-item sum equals total; discount fallback);
  API failure rolls back stock; out-of-stock at checkout; cart is recreated
  after checkout.
- Webhook: valid signature accepted; bad signature, stale timestamp and tampered
  body rejected; duplicate event is a no-op; amount mismatch not marked paid;
  paid-after-cancelled flagged; out-of-order events.
- Expiry command releases stock and cancels.
- Filament: Mark COD as paid action; badges; Payments relation manager.
- Final check: manual run in PayMongo **test mode** with a GCash and a card test
  payment, including a real webhook delivery, before any live key is used.

## 12. Open questions for the owner

1. Do you already have (or can you get) a PayMongo account while sign-ups are
   paused? If not, is it acceptable to build everything else (checkout, stock,
   order states, admin, webhook handler) behind a faked gateway and finish
   signature verification and the test-mode run once keys exist, or should we
   plan against Maya instead?
2. Should COD be limited (maximum order value, certain areas)? Default: no
   limits.
3. Is it acceptable that stock is reserved at checkout and released on
   cancel/expiry (default 60 minutes)?
