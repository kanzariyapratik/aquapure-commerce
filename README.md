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

### Password management and email

Customers and admins use the same sign-in page. Select **Forgot password?** to request an email reset link. Links expire after 60 minutes and can be used once. The response does not reveal whether an email is registered. Requests are rate limited.

Signed-in customers can open **Account menu > Change password** in the storefront header (`/account/password`). Admins can use **Change password** in the admin sidebar (`/admin/password`). Both require the current password and a confirmed new password of 12–64 characters containing letters and numbers. Registration and password resets use the same policy. Bcrypt's byte limit is also enforced for multibyte passwords. Existing passwords continue to work. Changing a password also invalidates outstanding reset links and remembered logins. Other sessions tracked by the password-session middleware must sign in again after a password change or reset.

The default `MAIL_MAILER=log` writes reset messages to `storage/logs/laravel.log` for local testing. To send real emails, set `MAIL_MAILER=smtp`, your provider's `MAIL_SCHEME` (`smtp` for STARTTLS, `smtps` for implicit TLS), `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and verified `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` in `.env`. Set `APP_URL` to your application's public URL, then run `php artisan config:clear` after changing configuration. No additional database migration is needed; the existing schema includes `password_reset_tokens`.

Run password-management tests with `php artisan test`. Tests use an isolated SQLite in-memory database and fake notifications; they do not send email or modify the application's database.

### Two-factor authentication

Run `composer install`, `php artisan migrate`, and `npm run build` when deploying these changes. The additive migration adds encrypted two-factor secrets, encrypted recovery-code hashes, an enrollment confirmation timestamp, and a counter that prevents authenticator-code replay.

- Admins must complete enrollment at **Admin > Security & 2FA** (`/admin/security`) before accessing admin pages or actions. They cannot disable 2FA. Existing admin accounts are directed to setup on their next admin request.
- Customers can enable 2FA at **Account menu > Security & 2FA** (`/account/security`). New customers are directed there after registration. Password confirmation starts a 10-minute setup session. Scan the locally generated QR code with Google Authenticator, Microsoft Authenticator, or another TOTP app, then enter a valid code to activate protection.
- Save the ten recovery codes immediately; they are shown only once and can be downloaded. Each code is usable once. The database stores only encrypted hashes of recovery codes. Generating replacement codes requires the password and a fresh authenticator code or unused recovery code, and invalidates all old recovery codes. Customers need both factors to disable 2FA.
- With 2FA enabled, password verification creates only a pending five-minute login session. No authenticated session is created until the second factor passes. Codes use a 30-second interval with one interval of clock tolerance. Accepted authenticator codes cannot be reused, including immediately after enrollment; wait for the next code. Code consumption uses database row locks.
- Password resets do **not** disable or bypass 2FA. A changed password or authenticator invalidates pending login challenges. Previously signed-in sessions without second-factor proof are challenged before further access. There is no email-only bypass for lost authenticators: use a saved recovery code. If both authenticator and recovery codes are lost, account recovery requires a separately verified support process; no self-service bypass is provided.
- Sign-in is limited per account and per IP. Two-factor attempts have a separate per-account limit that survives starting another login session. Registration and 2FA settings are also rate limited. Passwords and authentication codes are excluded from validation flash input.

Deploy behind **HTTPS** with `APP_ENV=production`, `APP_DEBUG=false`, a stable private `APP_KEY`, and accurate server time. Production session cookies are forced secure; Laravel's HTTP-only and SameSite defaults remain in place. Authentication and account responses use `Cache-Control: no-store`; 2FA pages clear Inertia history and encrypt it on secure origins. Local HTTP `.test` development does not support encrypted browser history, so use test credentials there. Keep `APP_KEY` backed up securely; it is required to decrypt enrolled authenticator secrets. Configure trusted proxies correctly when TLS terminates at a proxy.

The implementation uses the maintained [Google2FA](https://github.com/antonioribeiro/google2fa) and [BaconQrCode](https://github.com/Bacon/BaconQrCode) libraries. QR generation happens inside the application; authenticator secrets are never sent to an external QR service.

### Razorpay
In `.env`, set `RAZORPAY_KEY_ID` and `RAZORPAY_KEY_SECRET` using **test keys** initially. Restart app/config cache after editing.

Checkout creates an order and reserves stock in a DB transaction, then creates a Razorpay order. On the order page, customers open Razorpay Checkout and a signed callback is verified server-side before marking the order paid. **Do not use live payments yet**: production requires a verified Razorpay webhook, reliable reconciliation for abandoned/failed payments, stock reservation expiry, refund flows, shipping/tax/GST handling, notifications, rate limiting and end-to-end testing. A cancelled gateway initialization releases stock, but abandoned pending orders are not automatically released.

### Business configuration
- Visitors can browse the public storefront and catalog. Cart viewing, adding/removing items, checkout, order history, and payment verification require authentication on the server. Guests are redirected to sign in; registration preserves the intended destination too. Accounts with 2FA enabled must complete the second-factor challenge before accessing cart or ordering routes.
- All prices, claims, names, images and product specs are demonstration data; confirm with the manufacturer.
- Wholesale tier pricing is applied when the customer selects wholesale at checkout and a line meets the product minimum quantity. There is no wholesale approval workflow yet.
- Shipping currently ₹0 for demonstration. No tax/GST or real carrier integration.
- Admin can create/edit/archive products, upload images, adjust stock, update order statuses and manage enquiries.
- Product images fall back to a CSS illustration until images are uploaded.

## Production hardening checklist
1. Replace branding, product claims, policy pages and contact details with real verified information.
2. Build and test Razorpay webhooks with signature verification and event idempotency, reconcile payment state against gateway API, and expire unpaid reservations safely.
3. Implement GST invoice generation, shipping charges, fulfillment integrations, refunds/returns, and cancellation policy.
4. Add stock reservation timeouts, audit logging, monitoring, backups and email verification; expand test coverage beyond password management.
5. Enforce HTTPS, secure cookies, APP_DEBUG=false, strong admin credentials, rate limits and deployment secret management.
6. Add production-grade search, SEO metadata, accessibility review and image optimization.

## Project layout
`routes/web.php` — routes; `app/Services/OrderService.php` — transactional stock/order logic; `app/Services/RazorpayService.php` — payment API and signature check; `resources/js/Pages` — storefront/admin; `database/seeders` — sample products.
