# StoreHub API — Laravel Backend

This is the Laravel backend for the Arabic eCommerce Dashboard prototype
(`storehub-prototype.html`). It implements the domain model, business logic,
and payment integrations described in the original brief.

**Important — read this first:** this code was hand-written in a sandbox
with no PHP, Composer, or Packagist access, so **it has not been run or
autoloaded by an actual Laravel installation.** The PHP syntax and Laravel
conventions are correct to the best of a careful, senior-level review, but
treat this as a strong first draft to run `composer install` against and
smoke-test — not as pre-tested, deploy-ready code. Section 8 below is a
checklist for exactly that.

---

## 1. What's in this package vs. what `laravel new` gives you

This package does **not** include the Laravel framework itself (vendor/,
artisan, public/index.php, the default bootstrap/app.php, default
migrations for `users`/`cache`/`jobs`) — those come from Composer. This
package is the **application layer** on top of that skeleton:

```
app/Models/                  26 Eloquent models
app/Services/                Payment gateways, Order/Inventory business logic
app/Http/Controllers/Api/    15 controllers
app/Http/Requests/           Form validation
app/Http/Resources/          JSON response shaping
app/Http/Middleware/         Permission + locale middleware
database/migrations/         9 migrations, full schema
database/seeders/            Roles/permissions + demo data
routes/api.php               Every endpoint, permission-gated
bootstrap/app.php            Reference version — merge, don't overwrite
config/services-additions.php  Merge into your config/services.php
.env.example                 All required environment variables
```

## 2. Setup, from zero

```bash
# 1. Fresh Laravel skeleton
composer create-project laravel/laravel storehub-api
cd storehub-api

# 2. Required packages
composer require laravel/sanctum stripe/stripe-php guzzlehttp/guzzle

# 3. Copy this package's files into the new project, overwriting where noted:
#    - app/, database/migrations/, database/seeders/, routes/api.php  → copy in directly
#    - config/services-additions.php  → merge its array entries into config/services.php
#    - bootstrap/app.php  → merge the ->withMiddleware() alias() call into yours
#    - .env.example  → merge these keys into your .env (then fill in real values)

# 4. Sanctum setup
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# 5. Database
php artisan migrate
php artisan db:seed
# This creates the demo login: john@storehub.com / password — change it immediately.

# 6. Storage (for product images / bank transfer proofs)
php artisan storage:link

# 7. Run it
php artisan serve
```

## 3. How the pieces fit together

**Payment architecture.** `PaymentGatewayInterface` is the contract; Stripe,
PayPal, and Bank Transfer each implement it independently.
`PaymentManager::gateway('stripe')` resolves the right one. Nothing in
`OrderController` or the `Order` model ever branches on gateway name — adding
Apple Pay later means one new class + one line in `PaymentManager`, matching
the brief's "PAYMENT SYSTEM → Stripe / PayPal / Bank Transfer → Payment
record → Order" diagram.

**Payment status vs. fulfillment status.** These are deliberately two separate
enum columns on `orders` (`payment_status`, `status`), never collapsed into
one. `OrderService::applyPaymentResult()` is the only place `payment_status`
changes; `OrderService::transitionStatus()` is the only place `status`
changes, and it's guarded by `Order::STATUS_FLOW` so the API can't skip the
pipeline (e.g. `pending` → `delivered` directly is rejected).

**Webhooks are trusted by signature, not by auth.** `/api/webhooks/stripe` and
`/api/webhooks/paypal` are public routes (see `routes/api.php`) — Stripe and
PayPal can't send a Bearer token. Trust comes from
`StripeGatewayService::verifySignature()` (HMAC against your webhook secret)
and `PayPalWebhookController::verifiedByPayPal()` (round-trips the payload to
PayPal's own verify endpoint). Every inbound webhook is logged to
`payment_webhook_logs` — including ones that fail to verify — before anything
else happens, so a disputed payment can always be reconstructed.

**Inventory.** `Product.stock_quantity` is physical stock on hand;
`reserved_quantity` is stock tied up in unpaid/unshipped orders.
`availableStock() = stock_quantity − reserved_quantity`, matching the brief's
formula exactly. `InventoryService` is the only thing allowed to touch either
column, and every change writes an `inventory_movements` row (the ledger
behind the dashboard's "Inventory Activity" panel).

**Permissions.** Granular keys like `products.edit`, `orders.cancel`,
`payments.verify` live in the `permissions` table, grouped onto `roles` via
`permission_role`. `User::hasPermission()` checks this (Super Administrator
short-circuits to `true`); the `permission:` route middleware enforces it per
endpoint. `RolePermissionSeeder` encodes the exact 5 roles and default grid
from the dashboard's Roles & Permissions screen.

**Activity log.** `ActivityLogger::log()` is called explicitly at the end of
every sensitive controller action (see `ProductController::store()`,
`BankTransferGatewayService::confirm()`, etc.) rather than via a global
observer — this keeps the log's wording ("Updated Product", "Payment
verified") matching what the dashboard actually displays, instead of a
generic "Model updated" string.

## 4. API surface (all under `/api`, JSON only)

| Area | Endpoints |
|---|---|
| Auth | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` |
| Dashboard | `GET /dashboard/summary`, `GET /dashboard/sales-series` |
| Products | `GET|POST /products`, `GET|PUT|DELETE /products/{id}`, `POST /products/{id}/adjust-stock` |
| Categories | `GET|POST /categories`, `PUT|DELETE /categories/{id}` |
| Orders | `GET /orders`, `GET /orders/{id}`, `PUT /orders/{id}/status`, `POST /orders/{id}/cancel`, `GET /orders/{id}/activity` |
| Customers | `GET|POST /customers`, `GET|PUT|DELETE /customers/{id}`, `GET /customers/{id}/orders`, `POST /customers/{id}/status` |
| Payments | `GET /payments`, `GET /payments/summary`, `POST /payments/{id}/refund`, `POST /orders/{id}/payments/initiate` |
| Bank transfer | `GET /bank-transfers/pending`, `POST /bank-transfers/{id}/confirm`, `POST /bank-transfers/{id}/reject`, `POST /payments/{id}/bank-transfer/submit` |
| Webhooks (public) | `POST /webhooks/stripe`, `POST /webhooks/paypal` |
| Admin/roles | `GET|POST /administrators`, `POST /administrators/{id}/status`, `GET /roles`, `GET /permissions`, `PUT /roles/{id}/permissions` |
| System | `GET /activity-log`, `GET|PUT /settings` |

Every admin route (everything except the two webhooks and the storefront
bank-transfer-submit route) requires `Authorization: Bearer <token>` from
`/auth/login`, plus the specific `permission:` listed in `routes/api.php`.

## 5. Connecting the existing frontend prototype

`storehub-prototype.html` currently runs on hardcoded mock data (`const DB = {...}`
near the top of its `<script>`). To point it at this API instead of the mock
data, the swap is: replace direct `DB.products` / `DB.orders` / etc. reads
with `fetch('/api/products', { headers: { Authorization: 'Bearer ' + token }})`
calls, and replace `doLogin()`'s fake gate with a real call to `/api/auth/login`
storing the returned token. The resource JSON shapes in `app/Http/Resources/`
were deliberately named to match the prototype's existing field names
(`sku`, `stock`, `status`, `payment_status` etc.) to make this swap
mechanical rather than a rewrite.

## 6. Arabic/RTL note

This API is intentionally locale-agnostic about *content* — it does not
translate product names or order data. What it does do:
- `name` / `name_ar` columns on `products` and `categories`, so both
  languages' labels can be stored and returned side by side.
- The `locale` middleware reads an `Accept-Language: ar|en` header (sent
  once by the dashboard when the user flips the language switch) and sets
  Laravel's locale accordingly — this affects validation error message
  language, not stored data.
- `settings` seeds a store-wide default (`localization.default_language`,
  `localization.direction`) matching the Settings → Localization tab.

The actual RTL layout work lives entirely in the frontend
(`storehub-prototype.html`), which was already built and verified — this API
doesn't need to know or care which direction the dashboard is rendering in.

## 7. What's deliberately lighter-weight

To keep this focused on the modules the brief emphasized most (products,
orders, customers, payments, roles), a few areas are scaffolded at the
migration/model level but don't yet have full controllers: `shipping_zones`/
`shipping_methods`/`shipments`, `coupons`, `discounts`. Adding controllers
for these follows the exact same pattern as `CategoryController` — they're
the least architecturally risky part of the system to extend later.

## 8. Before you trust this in production — a checklist

Since this was never run against a real PHP interpreter, budget time for:

- [ ] `composer install` actually resolves — no typos in `use` statements or class names
- [ ] Run every migration fresh (`migrate:fresh --seed`) and confirm no FK order issues
- [ ] Stripe: test with the CLI (`stripe listen --forward-to localhost:8000/api/webhooks/stripe`) using real test-mode keys
- [ ] PayPal: test against the sandbox with a real sandbox buyer account
- [ ] Confirm `EnsureFrontendRequestsAreStateful` isn't needed (it isn't, for Bearer-token auth — but double check if you later add a cookie-based SPA login)
- [ ] Add rate limiting to `/auth/login` (not included — a few failed-login attempts should lock out or throttle)
- [ ] Add form request validation for the controllers marked with inline `abort_unless()` checks instead of a dedicated Request class (Category, Administrator, Setting) if you want fully consistent validation error shapes
- [ ] Write feature tests for at least: order status transition guards, inventory reservation math, and one webhook payload per gateway
