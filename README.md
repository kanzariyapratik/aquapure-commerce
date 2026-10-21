# AquaPure Ceramics — Laravel 12 + React/Inertia

A runnable **source-code starter project** for a ceramic water filter manufacturer. Includes retail and wholesale catalog, shopping cart, authentication, checkout, Razorpay payment verification, inventory movements, product and order admin, and B2B enquiries.

## Requirements
- PHP 8.3+ (PHP 8.4 recommended), Composer 2, Node.js 20+, npm, MySQL 8
- PHP extensions required by Laravel (including PDO MySQL, mbstring, openssl, fileinfo)

## Installation (Windows PowerShell or Linux terminal)

```bash
composer install
cp .env.example .env
php artisan key:generate
```

On Windows PowerShell, replace `cp` with `Copy-Item .env.example .env` if needed. Create an empty MySQL database named `aquapure`, then edit `.env` database credentials.

```bash
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

Open http://127.0.0.1:8000. For frontend hot reload, run `npm run dev` in a second terminal.

### Local demo admin (seeded only when APP_ENV=local)
- Email: `admin@aquapure.test`
- Password: `ChangeMe123!`

**Immediately change/remove this local account before any deployment.** Public self-registration always creates a customer role; admin access is protected by server-side middleware.

### Razorpay
In `.env`, set `RAZORPAY_KEY_ID` and `RAZORPAY_KEY_SECRET` using **test keys** initially. Restart app/config cache after editing.

Checkout creates an order and reserves stock in a DB transaction, then creates a Razorpay order. On the order page, customers open Razorpay Checkout and a signed callback is verified server-side before marking the order paid. **Do not use live payments yet**: production requires a verified Razorpay webhook, reliable reconciliation for abandoned/failed payments, stock reservation expiry, refund flows, shipping/tax/GST handling, notifications, rate limiting and end-to-end testing. A cancelled gateway initialization releases stock, but abandoned pending orders are not automatically released.

### Business configuration
- All prices, claims, names, images and product specs are demonstration data; confirm with the manufacturer.
- Wholesale tier pricing is applied when the customer selects wholesale at checkout and a line meets the product minimum quantity. There is no wholesale approval workflow yet.
- Shipping currently ₹0 for demonstration. No tax/GST or real carrier integration.
- Admin can create/edit/archive products, upload images, adjust stock, update order statuses and manage enquiries.
- Product images fall back to a CSS illustration until images are uploaded.

## Production hardening checklist
1. Replace branding, product claims, policy pages and contact details with real verified information.
2. Build and test Razorpay webhooks with signature verification and event idempotency, reconcile payment state against gateway API, and expire unpaid reservations safely.
3. Implement GST invoice generation, shipping charges, fulfillment integrations, refunds/returns, and cancellation policy.
4. Add stock reservation timeouts, audit logging, tests, monitoring, backups, email verification and password reset.
5. Enforce HTTPS, secure cookies, APP_DEBUG=false, strong admin credentials, rate limits and deployment secret management.
6. Add production-grade search, SEO metadata, accessibility review and image optimization.

## Project layout
`routes/web.php` — routes; `app/Services/OrderService.php` — transactional stock/order logic; `app/Services/RazorpayService.php` — payment API and signature check; `resources/js/Pages` — storefront/admin; `database/seeders` — sample products.
