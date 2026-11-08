---
name: build-shop-feature
description: Quality standard for building or changing any feature in the shop app (models, Order logic, Livewire storefront pages, Filament admin resources, migrations, tests). Enforces SOLID, DRY, KISS, YAGNI, modern PHP 8.5, security-first code, no deprecated APIs, and one consistent UI/UX across the Flux storefront and Filament admin. Use this whenever the user asks to add, build, implement, extend or refactor a feature, page, resource, action or model here, even if they never mention code quality or principles.
---

# Build shop feature

Every change should look like the same careful developer wrote it. Read `CLAUDE.md` first; its conventions (Order::moveTo(), customers are Users, decimal money, enum statuses, Filament 5 APIs, no new packages) outrank anything below. Pair with `laravel-best-practices`, `livewire-development`, `fluxui-development` or `filament-development` for API details, and use `search-docs` rather than memory for Laravel 13 / Filament 5 / Livewire 4 syntax.

## Workflow

1. **Reuse before building.** Search `app/` for an existing method, enum, component or schema that already does the job. Extend it instead of adding a parallel one (DRY).
2. **Put logic where the domain lives.** Order/cart rules go on `Order`, not in Livewire components or Filament pages. Components and resources stay thin wrappers.
3. **Write the failing Pest test first** for behavior and for the security boundary (see Security). Use factories and their states.
4. **Implement the smallest thing that satisfies the request.**
5. **Verify:** `vendor/bin/pint --dirty --format agent`, then `composer test` (Pint, PHPStan level 7, tests). Report real output; do not claim success from a partial run.

## Principles, applied to this codebase

- **SRP / DIP.** One reason to change per class. Introduce a class only when a responsibility is genuinely separate (e.g. Filament's `Schemas/`, `Tables/`, `Pages/` split). Depend on framework contracts and injected collaborators, not `new` inside methods.
- **OCP.** Vary behavior through enums (`match` on `OrderStatus`) or small interfaces, not growing `if` chains. Add a case; do not edit every consumer.
- **LSP / ISP.** Keep interfaces tiny and implementations substitutable. Do not create an interface with a single implementation and no seam need.
- **DRY.** Extract on the third repetition, not the second. Never extract two things that merely look alike but change for different reasons.
- **KISS.** Prefer Eloquent and framework features over custom abstractions. No repositories, service layers or DTOs unless the codebase already uses one for that concern.
- **YAGNI.** No speculative options, config flags, hooks, or unused parameters. Do not add packages (project rule), tables, or columns "for later".

## Modern PHP 8.5 style

- `declare(strict_types=1)` only if the file already uses it; match neighbors. Full parameter, return and property types; `readonly` for value objects; `final` for classes not designed for extension.
- Use native features: constructor promotion, enums with methods, `match`, first-class callable syntax (`strlen(...)`), `#[Fillable]` attribute and `casts()` method on models (project convention), `array_first()` / `array_last()`, the pipe operator `|>` when it clarifies a transformation chain, `#[\NoDiscard]` on methods whose result must not be ignored.
- Avoid deprecated or discouraged constructs: backtick shell operator, non-canonical casts (`(boolean)`, `(integer)`), `null` as an array offset, `__sleep`/`__wakeup` (use `__serialize`/`__unserialize`), `$casts` property, `$fillable` property, dynamic properties, `${var}` interpolation, implicit nullable parameter types (`Type $x = null`), Livewire 3 / Filament 3 APIs (`Filament\Forms\Form`, `Tables\Actions\*`).
- Money is `decimal(10,2)` end to end; never cast to float. Name things by domain meaning, keep methods short, early-return instead of nesting, no commented-out code.

## Security first

- **Authorize everything.** Every Livewire action that takes an id (`wire:click="remove($id)"`) must scope the lookup to the current user (`auth()->user()->cart()->items()->findOrFail()`), because public properties and arguments are client-controlled. Filament resources get a policy; storefront routes keep `auth` + `verified`.
- **Validate at the boundary** (Form Request, `#[Validate]`, or Filament field rules) with an explicit allowlist; mass assignment only through `#[Fillable]`. Never `$request->all()` or `forceFill` from input.
- **No raw SQL with interpolation**, no `{!! !!}` on user data, no secrets in code (use `config()`/env), no `env()` outside config files, no sensitive data in logs.
- **State integrity:** change order status only via `Order::moveTo()`; wrap multi-write operations in `DB::transaction()`; lock stock when decrementing.
- Test the negative path: another user's id returns 404/403, invalid input is rejected, guests are redirected.
- Do not touch Fortify/auth files unless asked.

## Uniform UI/UX

Follow the patterns already in `resources/views/livewire/cart.blade.php` and the existing Filament resources instead of inventing new ones.

**Storefront (Livewire + Flux + Tailwind 4)**
- Page skeleton: `flex flex-col gap-8` root; header with a ghost back `flux:button`, `flux:heading size="xl" level="1"` and `flux:subheading`.
- Use Flux components first (`flux:card`, `flux:button`, `flux:input`, `flux:badge`, `flux:modal`, `flux:pagination`, `flux:separator`); hand-roll markup only when Flux has no equivalent.
- Every state is designed: empty (centered heading + one call-to-action), loading (`wire:loading` / `wire:target`), validation errors (`flux:error` via field), success (toast or inline message).
- Strings via `__()` / `trans_choice()`; money as `₱` + `number_format($x, 2)` with `tabular-nums`; icon-only buttons carry `aria-label`; landmarks (`section`/`aside`) get `aria-label`; support dark mode with `dark:` variants; mobile-first and responsive with flex-wrap, no fixed widths that overflow.
- `wire:key` on every looped element; `wire:navigate` on internal links.

**Admin (Filament 5)**
- Resource split into `Schemas/`, `Tables/`, `Pages/`, `RelationManagers/`; imports from `Filament\Schemas`, `Filament\Actions`.
- Prices always `money('PHP')` in tables and infolists, `TextInput::make()->numeric()->prefix('₱')->step(0.01)->minValue(0)` in forms.
- Statuses render through the enum's `HasLabel`/`HasColor` as badges. Destructive actions require confirmation. Same labels and ordering of columns, filters and actions across resources.

If a new screen needs something neither pattern covers, add it to both surfaces consistently or note the gap to the user instead of diverging silently.

## Done checklist

- [ ] Reused existing code; no duplicated logic; nothing speculative added
- [ ] Domain logic on the model; components/resources thin
- [ ] Types complete, PHP 8.5 idioms, no deprecated APIs
- [ ] Authorization and validation in place, negative-path tests pass
- [ ] Storefront and admin follow the shared UI patterns (states, a11y, dark mode, ₱ formatting)
- [ ] Pint clean, PHPStan clean, full suite green, results reported honestly
