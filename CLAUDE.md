# Shop: Order Management System

## Stack
- Laravel 13, PHP 8.5+, Filament 5
- Livewire starter kit, Pest
- SQLite (MySQL optional)

## Commands
- Dev: composer run dev
- Tests: php artisan test
- Reset: php artisan migrate:fresh --seed
- Format: vendor/bin/pint

## Conventions
- Money: decimal(10,2), never float
- Order status is a PHP enum
- Every model gets a factory
- Filament 5 APIs only (Schemas, Filament\Actions)
- Admin prices use money('PHP')
- Customers are User records. Never add a customers table.
- An order’s total is calculated from its items in the Order model.
- Change order status only through Order::moveTo().
  Never update orders.status directly.

## Don’t
- Edit migrations that already ran
- Touch Fortify/auth files unless asked
- Add packages without asking

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

`AGENTS.md` holds the Laravel Boost guidelines (Pint, Pest, Livewire, Artisan/`make:` conventions, `search-docs`, skills in `.claude/skills`). Follow it; this file covers only what it doesn't.

## Stack

Laravel 13 shop app on PHP 8.5: Livewire 4 + Flux UI for the storefront, Filament 5 for the admin panel (`AdminPanelProvider`), Fortify for auth (incl. 2FA and passkeys), Tailwind 4, Pest 5. Frontend tooling is Vite+ (`vp`), not plain Vite. Default DB is SQLite; sessions, cache and queue use the `database` driver.

## Commands

- `composer setup` — first-time install (deps, `.env`, key, migrate, npm build)
- `composer dev` — runs `php artisan dev` (server, queue, Vite together). Herd already serves the site, so don't start a separate web server.
- `composer test` — the CI pipeline: `config:clear`, Pint `--test`, PHPStan (level 7, Larastan), then `artisan test`
- `php artisan test --compact tests/Feature/CartTest.php` (or `--filter=name`) — run a single test
- `composer lint` / `vendor/bin/pint --dirty --format agent` — format; `composer types:check` — PHPStan only
- `npm run build` — build assets (needed if you see "Unable to locate file in Vite manifest")

## Architecture

**Two UIs, one domain.** Customers use Livewire pages under `routes/web.php`; staff use Filament resources in `app/Filament/Resources/{Categories,Products,Orders}`. Each Filament resource splits into `Schemas/` (form), `Tables/`, `Pages/` (and `RelationManagers/`) rather than one big class.

**Cart = pending order.** There is no cart table. `User::cart()` calls `Order::cartFor()`, which `firstOrCreate`s the user's `OrderStatus::Pending` order. Cart mutations go through `Order::setProductQuantity()` / `removeProduct()` (quantity capped at `Product::stock`, zero removes the line) and always end in `recalculateTotals()`, which is where `total = subtotal - discount + tax + shipping_fee` lives. `OrderItem` snapshots `product_name` and `unit_price` at add time, so later product edits don't change existing carts. Put new cart/order logic on `Order`, not in the Livewire component (`app/Livewire/Cart.php` is a thin wrapper).

**`OrderStatus` enum** (`pending`, `paid`, `shipped`, `delivered`, `cancelled`) implements Filament's `HasLabel`/`HasColor`, so it is used directly in Filament tables and badges.

**Routing quirks.** Storefront routes (`/products`, `/products/{product}`, `/cart`, `dashboard`) require `auth` + `verified`; only `/` (landing) is public. Full-page Livewire components are registered with `Route::livewire()`. Settings pages are single-file Livewire components in `resources/views/pages/{auth,settings}` and referenced by the `pages::` namespace (e.g. `pages::settings.profile`), not by classes in `app/Livewire`. `ProductList` is a Livewire component embedded in `resources/views/products/index.blade.php`, served by a plain controller.

**Model attributes.** Models use the `#[Fillable([...])]` attribute rather than a `$fillable` property, and `casts()` as a method. Match this when adding models.

## Testing

Pest feature tests in `tests/Feature` (Filament tests in `tests/Feature/Filament`); the suite uses in-memory SQLite via `phpunit.xml`. Use model factories, including their states.
