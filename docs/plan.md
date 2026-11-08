# Order module completion: contract

Status: **DRAFT, awaiting approval.** No code has been written.

Replaces the earlier "order status history" contract. That feature is shipped (`ad46709`) and the old text is in git history.

Goal: stock follows payment and cancellation, coupons discount an order, and staff get an order View page with filters.

## Findings that shape the design

- **`Order::place()` already decrements stock inline** and sets `Paid` directly, bypassing `moveTo()`. Adding stock logic to `moveTo()` alone would double-decrement at checkout. Both paths must call one shared method (below).
- `moveTo()` is the only other `Paid` path (admin "Mark as paid", `OrderStatusActions`). Today it moves a `Pending` order to `Paid` without touching stock. That is the bug.
- `OrderStatus::allowedNext()`: `Cancelled` is reachable only from `Pending` or `Paid`. Stock was taken only if the order was `Paid`, so restore only on `Paid` to `Cancelled`.
- `coupons.discount` is a **fixed amount** (`decimal(10,2)`), not a percentage. There is no usage limit or minimum spend, and I am not adding either (YAGNI).
- `orders.discount` already exists and `recalculateTotals()` already does `subtotal - discount + tax + shipping_fee`. It never writes `discount` today.
- `OrderForm` dehydrates `status` on create, so an admin can create an order directly as `Paid` or `Cancelled`. That bypasses `moveTo()` and desyncs stock. Fixed in the admin contract.
- `OrderStatusHistoriesRelationManager` already exists. A View page shows it with no new code.
- Existing patterns to copy: `Coupons/Pages/ViewCoupon.php` and `Coupons/Schemas/CouponInfolist.php`.

## New columns

One new migration, `*_add_coupon_to_orders_table.php`. Never edit ones that ran. **No stock columns**: `products.stock` already exists and the status machine guarantees one decrement and at most one restore per order.

| Table | Column | Type | Notes |
|---|---|---|---|
| `orders` | `coupon_id` | foreignId, nullable | constrained to `coupons`, `nullOnDelete`, indexed |
| `orders` | `coupon_code` | string, nullable | snapshot of the code at apply time, so the View page still shows it if the coupon is deleted (same idea as `OrderItem.product_name`) |

The discount amount lives in the existing `orders.discount`. No `coupon_discount` column.

## Backend contract (`Order`, `Coupon`)

**`Order`**
- `#[Fillable]` gains `coupon_id`, `coupon_code`.
- `coupon(): BelongsTo<Coupon, $this>`
- `reserveStock(): void`: inside the caller's transaction, `lockForUpdate` each item's product, throw `\DomainException` if `stock < quantity`, then `decrement('stock', quantity)`. Extracted from the loop in `place()`; `place()` calls it instead of its inline loop (behavior unchanged, checkout tests stay green).
- `restoreStock(): void`: `increment('stock', quantity)` for each item. Skips items whose product no longer exists.
- `moveTo()`, same signature, inside its existing `DB::transaction`, before the status update:
  - `next === Paid` calls `reserveStock()`. Insufficient stock throws, and the status and history row roll back.
  - `previous === Paid && next === Cancelled` calls `restoreStock()`.
  - `Pending` to `Cancelled` touches no stock.
- `applyCoupon(Coupon|string $coupon, ?User $by = null): void`: see rules below.
- `removeCoupon(): void`: clears `coupon_id`, `coupon_code`, `discount`, then `recalculateTotals()`. `Pending` only.
- `recalculateTotals()` (modified): while status is `Pending` and `coupon_id` is set, recompute `discount` as `min(coupon.discount, subtotal)` before computing `total`. On any non-`Pending` order it keeps the stored `discount`, so paid orders never reprice. Money math must not use float. New code uses decimal-string math.

**Coupon rules** (`applyCoupon` throws `\DomainException` with a translated message):
- Order must be `Pending`.
- Coupon must be `is_active` and not expired. `expires_at` null means no expiry, and a coupon is valid through the end of its `expires_at` date.
- Unknown code throws.
- Order must have at least one item, otherwise the discount is 0 and the apply is misleading.
- One coupon per order. Applying a second replaces the first.
- Discount is capped at the subtotal, so `total` never goes below `shipping_fee + tax`.

**`Coupon`**: `isUsable(): bool` (active and not expired) and `static findUsableByCode(string $code): ?self`. These are the only place the validity rule lives.

**Factories**: `OrderFactory::withCoupon(?Coupon $coupon = null)` state. `CouponFactory` already has `inactive()` and `expired()`.

## Events

- **No new events.** Stock changes must commit atomically with the status change. Listeners run after the transaction (and the notification one is queued), so a listener-based design could pay an order and fail to take stock.
- `OrderStatusChanged` is unchanged. It still fires after `moveTo()` commits, and `SendOrderStatusUpdatedNotification` still emails the customer on `Paid` and `Cancelled`.
- Coupon apply and remove fire nothing and write no history row (YAGNI). See decision 3.

## Admin contract (Filament 5)

- `Pages/ViewOrder.php`, registered as `'view' => ViewOrder::route('/{record}')` in `OrderResource::getPages()`. Header actions: status actions, the two coupon actions, `EditAction`, `CommentsAction`. The existing `OrderStatusHistoriesRelationManager` renders below it.
- `Schemas/OrderInfolist.php`, wired through `OrderResource::infolist()`. Sections:
  1. **Customer**: `user.name`, `user.email`, and shipping and billing addresses.
  2. **Items**: `RepeatableEntry` on `items` with `product_name`, `unit_price` (money), `quantity`, `line_total` (money).
  3. **Coupon**: `coupon_code` (placeholder "None") and `discount` (money). Hidden if no coupon.
  4. **Totals**: `subtotal`, `discount`, `tax`, `shipping_fee`, `total`, all `money('PHP')`.
  5. **Status**: `status` badge using the enum's `HasLabel`/`HasColor`.
- `OrderCouponActions.php` (beside `OrderStatusActions.php`): `applyCoupon` (a `Select` of usable coupons, calls `$record->applyCoupon()`) and `removeCoupon`. Both are visible only while `Pending`, and both catch `DomainException` into a danger notification like `OrderStatusActions`. They are added to the View and Edit header actions. Authorization reuses `OrderPolicy::update`. No new permission.
- `OrdersTable`: add `ViewAction`. Keep the existing `status` `SelectFilter`. Add a **created date range** `Filter` (`created_from`, `created_until`, both `DatePicker`, inclusive, with indicators). Date column stays `created_at`.
- `OrderForm`: on create, restrict `status` options to `Pending` only. Non-pending states are reached only through `moveTo()`.
- Prices use `money('PHP')`. Totals are displayed, never computed in the view.

## Folder ownership

Each agent edits only its own paths. Cross-boundary needs go through the contract above.

| Agent | Owns (create/edit) | Must not touch |
|---|---|---|
| **backend-developer** | `database/migrations/*_add_coupon_to_orders_table.php`, `database/factories/OrderFactory.php`, `app/Models/Order.php`, `app/Models/Coupon.php` | `app/Filament`, `app/Livewire`, `app/Events`, `app/Listeners`, `app/Policies`, `tests`, auth/Fortify |
| **frontend-developer** | `app/Filament/Resources/Orders/**` (`Pages/ViewOrder.php`, `Schemas/OrderInfolist.php`, `OrderCouponActions.php`, edits to `OrderResource.php`, `Tables/OrdersTable.php`, `Schemas/OrderForm.php`, `Pages/EditOrder.php`) | models, migrations, `tests`, storefront Livewire |
| **test-writer** | `tests/Feature/OrderTest.php`, `tests/Feature/CheckoutTest.php` (additions), new `tests/Feature/OrderStockTest.php`, `tests/Feature/OrderCouponTest.php`, `tests/Feature/Filament/OrderResourceTest.php` (additions) | all of `app/`, `database/` (reports bugs instead) |

Order of work: **backend first** (its signatures are the interface), then **frontend-developer and test-writer in parallel** against this contract.

## Tests required

Stock
- Admin `Pending` to `Paid` via `moveTo()` decrements each product by item quantity.
- `Paid` to `Cancelled` restores it. `Pending` to `Cancelled` changes nothing.
- Insufficient stock on `moveTo(Paid)` throws, leaves status `Pending`, writes no history row and changes no stock (atomic).
- `place()` still decrements exactly once, including on an idempotent retry. The existing `CheckoutTest` cases stay green.
- A cancelled order cannot be cancelled again, so there is no double restore.

Coupon
- Valid coupon sets `coupon_id`, `coupon_code`, `discount` and a recalculated `total`.
- Inactive, expired, unknown, and empty-cart cases throw `DomainException` and change nothing.
- Discount larger than the subtotal is capped, and `total` is never negative.
- Changing cart quantity re-caps the discount. Removing the coupon returns the original `total`.
- Applying on a `Paid` order throws. A paid order's discount survives `recalculateTotals()`.
- Deleting the coupon keeps `coupon_code` and `discount` on the order.

Admin
- View page shows customer, items, coupon, totals and status history, with money formatted.
- Date range filter includes both boundary days. Status filter still works.
- Apply and remove coupon actions are hidden for non-`Pending` orders.
- Create form cannot start an order as anything but `Pending`.

## Review focus

Inputs the spec is silent on that are most likely to bite:
1. Two admins mark the same order paid at once. The `lockForUpdate` in `reserveStock()` prevents oversell, but the status check in `moveTo()` reads `$this->status` outside the lock. Expect the second call to fail the transition, not double-decrement. Test it.
2. Order item whose product was deleted. `order_items.product_id` is `restrictOnDelete`, so this cannot happen. `restoreStock()` still skips safely.
3. Coupon that expires between apply and payment. The stored discount stands, because the customer was shown that price.
4. Coupon discount equal to subtotal. Total becomes `tax + shipping_fee`, not zero.
5. Date filter with only one bound set, or `from` after `until`.

## Open decisions for you

1. **Cart coupon box on the storefront?** Out of scope as written (admin applies coupons). Customers cannot enter a code today. Recommended: ship the admin side now, add the cart field as a follow-up using the same `Order::applyCoupon()`.
2. **Deleting a `Paid` order** never restores stock, because only the cancel path does. Recommended: leave it and rely on cancelling first. Alternative is a policy rule, which touches `app/Policies`, a path no agent owns.
3. **Record coupon apply/remove in the status history?** Recommended no: it is not a status change, and the History table models status only.
4. **Existing `Paid` orders** are not backfilled, so cancelling an order paid before this ships restores stock that was taken by the old `place()`. That is correct. But a `Paid` order created by an admin before this ships never took stock, and cancelling it would over-restore. Recommended: accept, since it is a demo dataset. Alternative is a one-off check.

## Verification

`composer test` (config:clear, Pint `--test`, PHPStan level 7, Pest) must pass before done.
