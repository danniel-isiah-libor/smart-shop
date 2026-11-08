# Order status history: contract

Status: **DRAFT, awaiting approval.** No code has been written.

Goal: record who changed an order's status, from what, to what, and when.

## Findings that shape the design

- `Order::moveTo()` (`app/Models/Order.php`) is the single place status changes. It already captures `$previous` and dispatches `OrderStatusChanged`.
- **Gap:** `Order::place()` sets `status => Paid` directly via `fill()->save()` (cart to paid at checkout). It bypasses `moveTo()`, so it would leave no history row. Handled below.
- `Order::cartFor()` creates orders as `Pending`. The first row is not a transition, so the creation row has `from_status = null`.
- Actor: `auth()->id()`, which is null for system or console changes (and checkout is the customer, so it is set).
- Filament actions in `OrderStatusActions` already call `moveTo()`. They need no change.

## Table: `order_status_histories`

New migration (never edit ones that ran).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `order_id` | foreignId | constrained, `cascadeOnDelete`, indexed |
| `user_id` | foreignId, nullable | actor; constrained to `users`, `nullOnDelete`. Null means system/console |
| `from_status` | string, nullable | `OrderStatus` value. Null for the initial row |
| `to_status` | string | `OrderStatus` value |
| `created_at` | timestamp | the "when". No `updated_at`: rows are append-only |

Index: `(order_id, created_at)`.

No `notes` column (YAGNI). No new customers table; actor is a `User`.

## Backend contract

**Model** `App\Models\OrderStatusHistory` (`#[Fillable([...])]`, `casts()` method, `HasFactory`, `const UPDATED_AT = null`)
- casts: `from_status`, `to_status` => `OrderStatus`
- `order(): BelongsTo<Order>`
- `user(): BelongsTo<User>` (the actor)

**Factory** `OrderStatusHistoryFactory`: default `Pending` to `Paid`, with a `user_id`. States: `initial()` (from null), `bySystem()` (user null).

**`Order` additions**
- `statusHistories(): HasMany<OrderStatusHistory>` ordered latest first
- `moveTo(OrderStatus $next, ?User $by = null): void`: signature gains an optional actor, defaulting to `auth()->user()`. Inside one `DB::transaction`: update status, create the history row, then dispatch `OrderStatusChanged` after commit.
- `recordStatusChange(?OrderStatus $from, OrderStatus $to, ?User $by = null): OrderStatusHistory`: `protected`, the only writer of history rows.
- `Order::place()` writes a `Pending` to `Paid` history row, with the buyer as actor, inside its existing transaction. It keeps its direct write. Routing it through `moveTo()` would fire the notification event and change checkout behavior, so that is out of scope. Flagged for your decision below.
- `Order::cartFor()`: no history row for the pending creation (the cart is not yet an order). Decision below.

**Policy**: reuse `OrderPolicy::view`. No new permission.

## Admin contract (Filament 5)

- `OrderStatusHistoriesRelationManager` registered in `OrderResource::getRelations()`: read-only table (no create, edit, delete actions), latest first.
- Columns: `created_at` (dateTime), `from_status` badge (placeholder "Created" when null), `to_status` badge, `user.name` (placeholder "System"). Badges use the `OrderStatus` enum's `HasLabel`/`HasColor`.
- No money columns, so `money('PHP')` does not apply.

## Folder ownership

Each agent edits only its own paths. Cross-boundary needs go through the contract above.

| Agent | Owns (create/edit) | Must not touch |
|---|---|---|
| **backend** | `database/migrations/*_create_order_status_histories_table.php`, `app/Models/OrderStatusHistory.php`, `app/Models/Order.php`, `database/factories/OrderStatusHistoryFactory.php`, `app/Events`, `app/Listeners` (only if needed) | `app/Filament`, `tests`, auth/Fortify files |
| **admin** | `app/Filament/Resources/Orders/**` (new `RelationManagers/OrderStatusHistoriesRelationManager.php`, edit `OrderResource::getRelations()`) | models, migrations, `tests`, storefront Livewire |
| **test** (`test-writer`) | `tests/Feature/OrderStatusHistoryTest.php`, `tests/Feature/Filament/OrderStatusHistoryRelationManagerTest.php`, and additions to `tests/Feature/OrderTest.php` / `CheckoutTest.php` | all of `app/`, `database/` (reports bugs instead) |

Order of work: backend first (the contract is the interface), then admin and test in parallel against the contract.

## Test scope

- `moveTo()` writes exactly one row with the correct from, to, actor and timestamp
- Invalid transition throws and writes no row, and the status is unchanged
- Row and status update are atomic (rollback leaves neither)
- System/console change stores a null actor
- `place()` writes a `Pending` to `Paid` row for the buyer, and an idempotent retry does not add a second row
- Relation manager lists rows latest first, is read-only, and respects `OrderPolicy`
- Cascade: deleting an order removes its history

## Open decisions for you

1. `place()`: add the history row inline (recommended) or route through `moveTo()` (fires customer notification, changes behavior)?
2. Record the initial `Pending` creation row (`from_status = null`) for carts? Recommended no: carts are created and abandoned often, which would add noise.
3. Backfill existing orders with one row each? Recommended no: history starts from deploy.

## Verification

`composer test` (Pint, PHPStan level 7, Pest) must pass before done.
