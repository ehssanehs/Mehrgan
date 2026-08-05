<p align="center">
  <img src="https://raw.githubusercontent.com/ehssanehs/Mehrgan/main/github/github/logo.png" width="320" alt="Mehrgan logo">
</p>

<h1 align="center">Mehrgan</h1>

<p align="center">
  A Persian-first VPN subscription storefront, Telegram sales bot, and administration panel for Marzban, Sanaei/X-UI, and PasarGuard.
</p>

<p align="center">
  <a href="https://github.com/ehssanehs/Mehrgan"><img src="https://img.shields.io/badge/Laravel-12.x-ff2d20?style=for-the-badge&logo=laravel" alt="Laravel 12"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.3%2B-777bb4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.3+"></a>
  <a href="https://filamentphp.com/"><img src="https://img.shields.io/badge/Filament-3.x-f59e0b?style=for-the-badge" alt="Filament 3"></a>
  <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/License-MIT-22c55e?style=for-the-badge" alt="MIT license"></a>
</p>

> **Language note:** the application UI and most administration labels are Persian by default. This README is in English so that deployment and integration details are easier to follow.

<p align="center">
  <img src="https://raw.githubusercontent.com/ehssanehs/Mehrgan/main/github/github/panel1.PNG" width="45%" alt="Mehrgan dashboard">
  <img src="https://raw.githubusercontent.com/ehssanehs/Mehrgan/main/github/github/panel2.PNG" width="45%" alt="Mehrgan user management">
  <br>
  <img src="https://raw.githubusercontent.com/ehssanehs/Mehrgan/main/github/github/panel3.PNG" width="90%" alt="Mehrgan server settings">
</p>

## Contents

- [What Mehrgan does](#what-mehrgan-does)
- [Feature overview](#feature-overview)
- [Architecture and modules](#architecture-and-modules)
- [Requirements](#requirements)
- [Deployment options](#deployment-options)
  - [Automated Ubuntu deployment](#1-automated-ubuntu-deployment)
  - [Manual production deployment](#2-manual-production-deployment)
- [First-time configuration](#first-time-configuration)
- [Configure a VPN panel](#configure-a-vpn-panel)
- [Configure Telegram](#configure-telegram)
- [Payments and orders](#payments-and-orders)
- [Updates and server operations](#updates-and-server-operations)
- [Backups and restore](#backups-and-restore)
- [Testing and development](#testing-and-development)
- [Routes and APIs](#routes-and-apis)
- [Environment configuration](#environment-configuration)
- [Security and known limitations](#security-and-known-limitations)
- [Support and contribution](#support-and-contribution)

## What Mehrgan does

Mehrgan is a Laravel application for selling and operating VPN subscriptions from one place. It combines:

- A public pricing and checkout website.
- A Filament administration panel at `/admin`.
- A Telegram bot for purchases, service delivery, support, referrals, and account management.
- Multi-server routing with location, capacity, panel type, and output-link selection.
- Direct API provisioning for Marzban, Sanaei/X-UI, and PasarGuard.
- Customer, reseller, wallet, referral, ticket, blog, backup, and module-management features.

The repository contains the web application and its server-side integrations. It does **not** contain a native Android, iOS, or Windows client; the Telegram bot can display connection tutorials for V2RayNG, V2Box/Streisand, and V2RayN.

## Feature overview

### Customer website and account area

- Persian-ready public storefront with active plans, pricing, features, duration, volume, and popular-plan presentation.
- Nine selectable public/authentication/admin themes:
  `welcome`, `rocket`, `arcane`, `cyberpunk`, `dragon`, `phoenix`, `nebula`, `aurora`, and `obsidian`.
- Registration, login, logout, password reset, password confirmation, and email-verification flows from Laravel Breeze.
- Customer dashboard with:
  - Active and recently expired services.
  - Connection/subscription links and expiry dates.
  - Renewal actions and renewal notifications.
  - Wallet balance and transaction history.
  - Order history and payment status.
  - Notifications with mark-read, mark-all-read, and delete actions.
  - Support tickets and replies.
- Profile update and account deletion.
- Admin-enforced account banning and unbanning. A banned user is logged out of the website and blocked in the Telegram bot.

### Plans, orders, and provisioning

- Plans are configured by name, price, traffic volume in GB, duration in days, features, popularity, active status, and supported server type.
- Multi-server orders can be routed by country/location, capacity, and panel type.
- The system chooses an available server when a customer has not selected one explicitly.
- Service provisioning creates or updates the customer on the target VPN panel and stores the resulting connection data on the order.
- Renewal keeps the original panel username and updates the existing panel account where the integration supports it.
- Admins can inspect and edit stored order connection data from the user-management view. Changes made there update Mehrgan's database only; they do not automatically change the remote panel.
- Sequential client naming is available with an admin-configurable prefix and concurrency-safe counter. Custom usernames still take precedence.

### Supported VPN panels

| Panel | Supported operations | Output / notes |
|---|---|---|
| **Sanaei / X-UI** | Login, inbound lookup, client creation, update, traffic reset, client disable, UUID search | Single VLESS link, subscription URL, or tunneled VLESS link. Sanaei is handled by the X-UI integration. |
| **Marzban** | Token login, user creation, update, lookup, traffic reset, disable, UUID search | Uses the panel's subscription URL and optional node hostname. |
| **PasarGuard** | Token login, user creation, update, lookup, traffic reset, disable, UUID search | Uses the panel's subscription URL and optional node hostname. |

Both the modern `MultiServer` records and legacy single-panel settings are supported. MultiServer configuration is the recommended path for new installations. The current service-plan form exposes `all`, `xui`, and `marzban` as plan filters; if you need a PasarGuard-only plan, use `all` or extend the plan enum/form before restricting it.

### Payments, wallets, and discounts

- **Card-to-card payment:** the admin configures one or more bank cards; a card is selected for the customer, who uploads a receipt. An admin approves or rejects the receipt.
- **Wallet:** customers can request a wallet top-up by card receipt, wait for manual approval, and use their balance for instant service purchases and renewals.
- **Manual wallet adjustment:** admins can credit or debit a customer's wallet and record a reason. The customer can also be notified in Telegram.
- **Discount codes:** fixed or percentage discounts with optional maximum discount, total/per-user usage limits, minimum order amount, start/end dates, plan restrictions, wallet restriction, renewal restriction, and active/inactive status.
- **Order review:** admins can approve, reject, and, when necessary, disable an already-created remote VPN account during rejection.
- **NOWPayments webhook endpoint:** `/webhooks/nowpayments` accepts selected NOWPayments statuses and records the payment ID/status. See the limitations section before using it as a fully automated production payment flow.
- **Crypto checkout:** the current website action is an informational placeholder and is not a complete payment gateway.

### Telegram bot

The bot is webhook-based and supports both reply and inline keyboards. Main user flows include:

- `/start` registration and referral deep links such as `/start REF-ABC123`.
- Service plan browsing by duration and traffic volume.
- Location/server selection and username selection.
- Automatic sequential usernames when that feature is enabled.
- Wallet balance, wallet top-up instructions, transaction history, and wallet purchases.
- Card-to-card payment instructions and receipt-photo submission.
- Service list, service details, renewal, copy-link buttons, and QR codes.
- Delivery of the connection link after an order is approved, including long subscription splitting when a Telegram message would be too large.
- Existing-subscription import from a VLESS URI or an HTTP(S) subscription URL.
- Trial account creation with configurable volume, duration, per-user limit, server, copy link, and QR code.
- Referral link and referral earnings.
- FAQ and Android/iOS/Windows connection tutorials.
- Support-ticket creation, replies, attachments, and ticket notifications.
- Optional forced membership in a Telegram channel before the bot can be used.
- Reseller/agent registration, wallet status, account creation, and reports when the reseller module is enabled.
- Admin payment approval/rejection, ticket replies, broadcasts, and user notifications.

Telegram bot content, deposit amounts, tutorial text, welcome/start messages, FAQ records, admin chat IDs, and feature buttons are managed from Filament.

### Existing subscription import

Customers can import a subscription from the website at `/subscription/import` or through the Telegram bot.

Supported input:

1. A single `vless://...` URI.
2. An `http://` or `https://` subscription URL whose response contains VLESS entries, either as plain text or base64-encoded content.

Import behavior:

1. Validate and detect the input type.
2. Fetch and decode the subscription when necessary.
3. Use the UUID from the **first** valid VLESS entry.
4. Reject invalid UUIDs and duplicate paid imports.
5. Search active MultiServer records and legacy X-UI, Marzban, and PasarGuard configuration.
6. Read the remote username, traffic, usage, expiry, panel metadata, and subscription link.
7. Create a paid imported order and match it to an active plan when possible.

The importer includes input-length limits, HTTP/HTTPS-only validation, timeout handling, localhost/private-IP checks, and DNS-resolution checks intended to reduce SSRF risk. The API-style website endpoint is `POST /subscription/import/api` and still requires an authenticated web session.

### Referral system

- Unique referral codes are generated for users.
- Referral links work in the website/bot registration flow and Telegram `/start` deep links.
- Admins can enable/disable referrals, configure a fixed reward, percentage reward, welcome gift, minimum purchase amount, first-purchase-only behavior, and duplicate-IP protection for the welcome gift.
- Optional Telegram notifications are sent when a referred user joins or completes a qualifying purchase.
- Referral reports show registrations, successful referrals, earnings, and wallet balance.

### Trial accounts and QR codes

- Admins can enable trial accounts and set the traffic limit in MB, duration in hours, per-user limit, and optional trial server.
- The Telegram bot creates a temporary account using the configured panel path, stores the link briefly in cache, and offers copy-link and QR-code actions.
- Purchased services and reseller accounts also expose QR-code functionality where a connection/subscription URL is available.

### Support, notifications, and content

- Ticketing supports priorities, open/answered/closed states, web replies, Telegram replies, and JPG/JPEG/PNG/PDF/ZIP attachments up to 5 MB.
- Ticket replies can notify the user through Telegram.
- The blog module provides `/blog`, published post listing, slug pages, categories, related posts, view counters, featured images, rich content, scheduled publication, and SEO fields.
- Admin broadcast messages are dispatched through the queue so large Telegram audiences do not block a web request.
- The profit report filters successful plan orders by date and shows order type, source, payment method, plan, username, and total amount.

### Reseller and agent operations

The `Reseller` module provides a separate VPN resale domain:

- Reseller plans with quota-based or pay-as-you-go pricing.
- Reseller applications, approval/rejection, optional payment receipt, and Telegram status notifications.
- Reseller wallets and wallet transactions.
- VPN server and product catalogs for Sanaei/X-UI, Marzban, and PasarGuard.
- Background account creation jobs with retries and automatic refunds after permanent failure.
- Product traffic, period, protocol, inbound/tag, and reseller price configuration.
- Reseller account status, expiry, subscription URL, raw server response, and QR-code API output.
- Sanctum API endpoints under `/api/v1/reseller`.

The repository also retains the older `Agent`, `AgentServer`, and `AgentTransaction` administration models for compatibility with earlier agent workflows.

### Backups and modules

- `MatinBackup` creates ZIP backups containing a MySQL dump, metadata, and `storage/app/public` files.
- Backups can be created, uploaded, downloaded, restored, and deleted from Filament.
- Backups can be sent to one or more configured Telegram admin chat IDs.
- A daily scheduled command, `backup:daily-telegram`, is registered by the module.
- The module manager can install a ZIP module, scan modules, enable/disable a module, or remove a module.
- Nwidart Laravel Modules provides the module discovery and activation system.

## Architecture and modules

Mehrgan is a Laravel 12 monolith with a modular feature layer:

```text
app/                         Core models, services, controllers, jobs, traits
Modules/
  Blog/                      Public blog and Filament content management
  MatinBackup/               Backup UI, service, and scheduled Telegram backup
  MultiServer/               Locations, servers, capacity, and panel routing
  Referral/                  Referral reporting and referral integration
  Reseller/                  Reseller plans, wallets, products, accounts, APIs
  TelegramBot/               Telegram webhook, menus, FAQ, and bot settings
  Ticketing/                 Web/Telegram support tickets and notifications
database/migrations/         Core database schema and feature migrations
resources/views/             Blade views, dashboard, payments, auth, themes
public/themes/               Public theme templates and theme assets
routes/                      Core web/auth/console routes
install.sh                   Interactive multi-instance Ubuntu installer
update.sh                    Per-instance update script
manage.sh                    Per-instance worker/status helper
uninstall.sh                 Destructive per-instance removal script
```

All modules are enabled in the repository's `modules_statuses.json` by default. Enabled module resources are discovered automatically by the Filament admin panel.

## Requirements

### Application runtime

- Ubuntu 22.04 is the target of the bundled installer. Other Linux distributions can work with equivalent packages.
- PHP **8.3 or newer** with at least:
  `bcmath`, `curl`, `dom`, `gd`, `intl`, `mbstring`, `mysql`/PDO MySQL, `redis`, `xml`, and `zip`.
- Composer 2.
- Node.js LTS and npm.
- MySQL 8+/MariaDB with a database and user. The backup module expects a working `mysqldump` command.
- Redis for the default queue and the recommended cache/session setup.
- Nginx or another web server pointed at `public/`.
- Supervisor for a persistent Redis queue worker.
- Cron/systemd timer for Laravel's scheduler.
- A public domain with DNS already pointing to the server. HTTPS is strongly recommended and required for a reliable Telegram webhook.

### External services

- A reachable Marzban, Sanaei/X-UI, or PasarGuard panel with API credentials.
- A Telegram bot token from BotFather if Telegram features are enabled.
- Optional SMTP/provider credentials if real email verification and password-reset email delivery is required.
- Optional public subscription/tunnel domains for X-UI output modes.

## Deployment options

### 1. Automated Ubuntu deployment

The bundled `install.sh` installs and configures multiple independent Mehrgan instances. Each instance gets its own project directory, database, Nginx site, Supervisor worker group, and cron file.

#### Step 1: Prepare DNS and SSH

Create an A/AAAA record such as `vpn.example.com` pointing to the server, then connect over SSH with a sudo-capable account. Make sure ports 22, 80, and 443 are reachable.

#### Step 2: Download the repository

Cloning the repository is safer than downloading only `install.sh`, because reinstall and uninstall paths use the other scripts too:

```bash
cd /tmp
git clone https://github.com/ehssanehs/Mehrgan.git
cd Mehrgan
sudo bash install.sh
```

The installer currently clones the repository's `main` branch for each new instance.

#### Step 3: Answer the installer prompts

The installer asks whether server prerequisites are already installed. If you answer `n`, it installs or enables:

- Nginx, MySQL, Redis, Supervisor, Git, Certbot, UFW.
- PHP 8.3 and required extensions.
- Node.js LTS, npm, and build tools.
- Composer.

Then, for each instance, enter:

1. A folder/instance name, for example `mehrgan-1`.
2. The public domain, for example `vpn.example.com`.
3. A database name, database username, and non-empty database password.
4. The initial admin email and password.
5. An email for the optional Certbot certificate.
6. Whether SSL should be enabled.

The installer then clones the project, creates the database, writes `.env`, installs Composer/npm dependencies, generates `APP_KEY`, migrates/seeds the database, creates the storage link, builds assets, creates Nginx and Supervisor configuration, registers the scheduler, and optionally requests SSL.

> **Installer warning:** the prerequisite branch is intended for a clean Ubuntu server and removes existing PHP packages before installing PHP 8.3. Do not run it blindly on a server hosting other PHP applications. Back up the server first or answer `y` only when the required stack is already installed.

#### Step 4: Verify the instance

The installer prints the instance URL and admin URL. Check them in a browser:

```text
https://vpn.example.com/
https://vpn.example.com/admin
```

Check the worker and web services:

```bash
sudo supervisorctl status
sudo systemctl status nginx php8.3-fpm mysql redis-server supervisor
```

#### Step 5: Complete the application setup

Continue with [First-time configuration](#first-time-configuration). Telegram cannot be configured completely by the shell installer because the bot token and admin chat IDs are stored in the Filament settings database.

### 2. Manual production deployment

Use this path when the server already has a web stack or when you need full control over versions and service isolation. Replace `/var/www/mehrgan` and `vpn.example.com` with your values.

#### Step 1: Install system packages

On Ubuntu 22.04, an example baseline is:

```bash
sudo apt update
sudo apt install -y git curl unzip zip build-essential nginx mysql-server redis-server supervisor \
  certbot python3-certbot-nginx software-properties-common

sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd \
  php8.3-dom php8.3-redis
```

Install Node.js LTS and Composer if they are not already present:

```bash
curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
sudo apt install -y nodejs

php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

Enable services:

```bash
sudo systemctl enable --now nginx php8.3-fpm mysql redis-server supervisor
```

#### Step 2: Create the production database

Use a strong password and do not use the MySQL root account from the application:

```bash
sudo mysql
```

```sql
CREATE DATABASE mehrgan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mehrgan_app'@'localhost' IDENTIFIED BY 'replace-with-a-long-password';
GRANT ALL PRIVILEGES ON mehrgan.* TO 'mehrgan_app'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### Step 3: Clone the application

```bash
sudo mkdir -p /var/www
sudo git clone https://github.com/ehssanehs/Mehrgan.git /var/www/mehrgan
sudo chown -R www-data:www-data /var/www/mehrgan
cd /var/www/mehrgan
```

#### Step 4: Create and edit `.env`

```bash
sudo -u www-data cp .env.example .env
sudo -u www-data nano .env
```

At minimum, set production values similar to:

```dotenv
APP_NAME=Mehrgan
APP_ENV=production
APP_DEBUG=false
APP_URL=https://vpn.example.com

ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=change-this-before-installation

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mehrgan
DB_USERNAME=mehrgan_app
DB_PASSWORD=replace-with-a-long-password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=redis
FILESYSTEM_DISK=local

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
```

Keep `APP_DEBUG=false` in production. The `ADMIN_*` values are read by the first database seed only; re-running the seed does not overwrite an existing admin account.

#### Step 5: Install PHP and JavaScript dependencies

```bash
cd /var/www/mehrgan
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction
sudo -u www-data HOME=/var/www npm ci
sudo -u www-data php artisan key:generate --force
```

If `package-lock.json` has intentionally changed in your deployment branch, use `npm install` instead of `npm ci` and review the resulting lockfile.

#### Step 6: Migrate, seed, link storage, and build assets

```bash
sudo -u www-data php artisan migrate --seed --force
sudo -u www-data php artisan storage:link
sudo -u www-data npm run build

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

The `public/build` directory is ignored by Git, so `npm run build` must be executed on every fresh production checkout and after frontend changes.

#### Step 7: Configure Nginx

Create `/etc/nginx/sites-available/mehrgan`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name vpn.example.com;

    root /var/www/mehrgan/public;
    index index.php;
    client_max_body_size 10M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable and validate it:

```bash
sudo ln -s /etc/nginx/sites-available/mehrgan /etc/nginx/sites-enabled/mehrgan
sudo nginx -t
sudo systemctl reload nginx
```

#### Step 8: Configure a queue worker

Create `/etc/supervisor/conf.d/mehrgan-worker.conf`:

```ini
[program:mehrgan-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mehrgan/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=/var/www/mehrgan
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/mehrgan-worker.log
stopwaitsecs=3600
```

Load it:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start "mehrgan-worker:*"
sudo supervisorctl status
```

The worker is required for Telegram broadcasts and reseller account-creation jobs, and is recommended for all production installs because the application defaults to Redis queues.

#### Step 9: Register Laravel's scheduler

Laravel's scheduler runs the daily backup command and any future scheduled tasks. Add `/etc/cron.d/mehrgan`:

```cron
* * * * * www-data cd /var/www/mehrgan && php artisan schedule:run >> /dev/null 2>&1
```

#### Step 10: Enable HTTPS

After DNS and HTTP are working:

```bash
sudo certbot --nginx -d vpn.example.com -m admin@example.com --agree-tos --redirect
```

Update `APP_URL` to the HTTPS URL if necessary, then clear and rebuild cached application state:

```bash
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan view:cache
```

#### Step 11: Verify the deployment

```bash
curl -I https://vpn.example.com
sudo supervisorctl status
sudo -u www-data php artisan about
```

Then log in at `https://vpn.example.com/admin` and follow the configuration checklist below.

## First-time configuration

Complete these steps in order after the first successful deployment.

### 1. Sign in to Filament

Open `/admin` and sign in with the admin credentials supplied through `ADMIN_EMAIL` and `ADMIN_PASSWORD` during the first seed. Change the password immediately if a temporary/default password was used.

### 2. Set the public site and payment settings

Open the site/settings page and configure:

- The active theme and its brand, hero, pricing, FAQ, and footer content.
- One or more payment cards and the common card-payment instructions.
- Any social links displayed by the selected theme.
- The public site-login URL used by the Telegram bot.

### 3. Configure MultiServer

Open **MultiServer → Locations** and create each country/location with a name, flag, unique slug, and active status.

Open **MultiServer → Servers** and add each panel:

- Select the location.
- Select `X-UI / Sanaei`, `Marzban`, or `PasarGuard`.
- Enter the panel address, port, path, HTTPS setting, username, and password.
- For X-UI, set the inbound ID or use the inbound selector to load it from the panel.
- For Marzban/PasarGuard, optionally set the node hostname.
- Set capacity and active status.
- Choose the output link type:
  - `single`: a direct VLESS configuration.
  - `subscription`: a subscription URL using the configured domain/path/port.
  - `tunnel`: a VLESS link addressed to the configured tunnel endpoint.

Do not mark a server active until its credentials and inbound/subscription values have been tested.

### 4. Create service plans

Open **Service Plans** and create at least one active plan:

- Name and price in toman.
- Traffic volume in GB.
- Duration in days.
- Features, one per line.
- Supported server type (`all`, `xui`, or `marzban` in the current plan form).
- Popular and active flags.

The public landing page and Telegram bot show only active plans.

### 5. Configure Telegram

Follow [Configure Telegram](#configure-telegram), set the webhook, and send `/start` to the bot. Confirm that a new user is created and that the menu can load plans.

### 6. Configure optional features

- **Trial:** set `trial_enabled`, trial server, MB limit, hours, and per-user limit under trial settings.
- **Referrals:** set enablement, fixed/percentage rewards, welcome gift, limits, and notifications.
- **FAQ/tutorials:** add FAQ records and edit tutorial text in Telegram settings.
- **Forced channel membership:** enable it and enter a public username or private channel chat ID. The bot must be able to query membership.
- **Discounts:** create an active discount code and test it with a low-risk plan.
- **Backups:** configure Telegram admin chat IDs and run a manual backup before going live.
- **Resellers:** create reseller plans, VPN servers/products, and configure reseller deposit cards if the module is part of the business flow.

### 7. Run an end-to-end test

Before selling real subscriptions, test:

1. Website registration and login.
2. Telegram `/start` and the plan menu.
3. A low-volume plan on every active panel type.
4. Card receipt upload and admin approval.
5. Wallet top-up approval followed by wallet purchase.
6. Telegram service delivery, copy link, and QR code.
7. Renewal and traffic reset.
8. Referral reward and discount-code conditions.
9. Ticket creation, admin reply, and Telegram notification.
10. Queue worker, scheduler, and backup restore on a non-production database.

## Configure a VPN panel

### MultiServer (recommended)

1. Create a location.
2. Create an active server under that location.
3. Choose the panel type.
4. Add credentials and verify the address from the Mehrgan host, not only from your laptop.
5. For X-UI/Sanaei, choose an inbound whose protocol and stream settings are valid for the intended client.
6. Configure the generated-link mode and any subscription/tunnel endpoint.
7. Create a plan compatible with the server type.
8. Place a test order and verify the account appears on the remote panel.

### Legacy single-panel mode

Older installations can still use values stored in the generic `settings` table for `xui_*`, `marzban_*`, and `pasarguard_*` keys. This mode is kept for backward compatibility. New deployments should use MultiServer so locations, capacity, and per-server credentials are explicit.

### Panel connectivity checklist

From the Mehrgan server, verify:

- The panel hostname resolves.
- The panel port is reachable through the firewall.
- The API user can log in.
- The selected inbound exists and is enabled.
- The subscription/tunnel domain resolves to the intended endpoint.
- The panel accepts the traffic and expiry units used by its API.

## Configure Telegram

### 1. Create the bot

Use BotFather to create a bot and keep the token private. Do not commit it to Git or place it in a public issue.

### 2. Save the token and admin chat IDs

In Filament, open the site/Telegram settings and save:

- `telegram_bot_token`.
- One or more numeric `telegram_admin_chat_id` values.
- Optional `site_login_url`.
- Optional `force_join_enabled` and `telegram_required_channel_id`.
- Visibility of the reseller and trial buttons.

The main bot controller reads the token from the database settings. `TELEGRAM_BOT_TOKEN` in the environment is useful as the package fallback, but saving the token in Filament is the supported application configuration path.

If Telegram must be reached through a proxy, set `TELEGRAM_PROXY` in `.env` and clear/reload the application configuration. When forced channel membership is enabled, add the bot as an administrator of the channel so Telegram can answer membership checks.

### 3. Set the webhook

Run this from the application directory after `APP_URL` is a public HTTPS URL and the database token setting exists:

```bash
sudo -u www-data php artisan telegram:set-webhook
```

The command registers:

```text
https://your-domain.example/webhooks/telegram
```

The webhook endpoint is also available as `POST /webhooks/telegram` and returns HTTP 200 after the update is handled/logged.

### 4. Verify the bot

Send `/start` and test:

- Plan listing.
- Import flow.
- Trial flow, if enabled.
- A card receipt photo.
- A support ticket.
- A service link and QR code after admin approval.

Check `storage/logs/laravel.log` and the Telegram API response if a message is not delivered.

## Payments and orders

### Website purchase flow

1. The visitor chooses an active plan.
2. The application creates a pending order.
3. The customer selects a server when MultiServer selection is enabled.
4. The customer can apply a discount code.
5. The customer pays by wallet or card receipt.
6. Wallet payments provision immediately inside a database transaction.
7. Card payments wait for an admin approval action in Filament.
8. Approval provisions the remote account, saves the link/expiry, records a transaction, and sends notifications.
9. Rejection records the reason and sends a Telegram message when the user has a Telegram chat ID.

### Card receipt requirements

- Website receipt uploads accept image files up to 2 MB.
- Ticket attachments are separate and accept JPG/JPEG/PNG/PDF/ZIP up to 5 MB.
- Configure `client_max_body_size` in Nginx to at least 10 MB as done by the installer.
- Run `php artisan storage:link` so public uploads can be served.

### Wallet flow

1. Customer opens wallet top-up.
2. A pending wallet order is created.
3. The customer submits a card receipt.
4. Admin approves it from Orders.
5. The amount is credited to the customer's wallet and a deposit transaction is recorded.
6. The balance can be used for a plan purchase or renewal.

## Updates and server operations

### Recommended manual update sequence

Always back up the database and `.env` before updating:

```bash
cd /var/www/mehrgan
sudo cp .env ".env.backup.$(date +%Y%m%d-%H%M%S)"
sudo -u www-data php artisan down --retry=60

sudo -u www-data git fetch origin --prune
sudo -u www-data git pull --ff-only origin main
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction
sudo -u www-data HOME=/var/www npm ci
sudo -u www-data HOME=/var/www npm run build
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan storage:link || true
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
sudo supervisorctl restart "mehrgan-worker:*"
sudo -u www-data php artisan up
```

Use the branch and worker name that belong to your instance. If an update fails while the application is in maintenance mode, recover it with:

```bash
cd /var/www/mehrgan
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan up
sudo supervisorctl status
```

### Bundled per-instance update script

The repository includes an interactive-free update helper for an installed instance:

```bash
cd /var/www/mehrgan-1
sudo bash update.sh
```

It backs up `.env`, enables maintenance mode, fetches the current branch, installs Composer/npm dependencies, builds assets, migrates the database, restarts that instance's Supervisor workers, clears/warms caches, and disables maintenance mode.

The script can also receive a path:

```bash
sudo bash update.sh --path=/var/www/mehrgan-1
```

It may stash or hard-reset local Git changes. Do not use it to deploy uncommitted production edits. Also note that the current script attempts `php artisan route:cache` while the project contains closure routes; if that command fails, use the manual recovery commands above and omit route caching.

### Instance management helper

```bash
bash manage.sh list
bash manage.sh status
bash manage.sh status mehrgan-1
bash manage.sh restart mehrgan-1
bash manage.sh stop mehrgan-1
bash manage.sh start mehrgan-1
```

### Removing an instance

`uninstall.sh` is destructive: it stops workers, removes Nginx/Supervisor/cron configuration, deletes the SSL certificate, drops the database and database user, and deletes the project directory.

```bash
sudo bash uninstall.sh --slug=mehrgan-1
```

Create and verify a backup before using it. `--all` can remove every detected Mehrgan instance.

## Backups and restore

### Filament backup page

Open **System → Backup Management** and use:

- **Create backup:** dumps the MySQL database and packages it with public storage files.
- **Upload backup:** uploads a ZIP into the backup directory.
- **Restore:** imports the SQL dump and replaces the public storage directory.
- **Download/delete:** manage existing backup archives.

A restore can overwrite current data and public files. Test the archive and restore process on a staging database first.

### Command line and schedule

Run a backup immediately:

```bash
sudo -u www-data php artisan backup:daily-telegram
```

The command creates a ZIP and attempts to send it to all configured Telegram admin chat IDs. The module registers the same command daily through Laravel's scheduler, so the cron entry from deployment must be active.

The backup service requires:

- A working MySQL-compatible `mysqldump` binary.
- PHP `ZipArchive`.
- Correct MySQL credentials in the application configuration.
- Telegram bot token and admin chat IDs if remote delivery is desired.

Keep backup archives private; they contain database data and potentially sensitive uploaded files.

## Testing and development

Install development dependencies first:

```bash
composer install
npm ci
```

Run the PHP test suite:

```bash
composer test
# or
php artisan test
# or
./vendor/bin/pest
```

Run selected tests:

```bash
./vendor/bin/pest --filter=VlessParser
./vendor/bin/pest --filter=SubscriptionImport
./vendor/bin/pest --filter=SequentialNaming
./vendor/bin/pest --filter=UserBan
```

Build frontend assets:

```bash
npm run build
```

For local development, configure a local database/Redis in `.env`, then use:

```bash
composer run dev
```

The development script starts the Laravel server, queue listener, log viewer, and Vite concurrently. Alternatively, run them separately:

```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

The test suite uses an in-memory SQLite database through `phpunit.xml`; production should use MySQL/MariaDB as described above.

## Routes and APIs

Use `php artisan route:list` on the deployed version for the definitive route list. The main application exposes:

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/` | Public storefront using the active theme. |
| `GET` | `/login`, `/register` | Authentication pages. |
| `GET` | `/dashboard` | Authenticated customer dashboard. |
| `GET` | `/subscription/import` | Authenticated import form. |
| `POST` | `/subscription/import` | Web subscription import. |
| `POST` | `/subscription/import/api` | JSON-style import response; authenticated web session required. |
| `GET` | `/blog` | Published blog posts. |
| `GET` | `/blog/{slug}` | Published blog post. |
| `POST` | `/webhooks/telegram` | Telegram bot webhook. |
| `POST` | `/webhooks/nowpayments` | NOWPayments status webhook. |
| `GET/POST` | `/tickets/...` | Authenticated, verified support-ticket pages from the Ticketing module. |

Module APIs are mounted below `/api` by the module route providers. Important examples include:

- `/api/v1/reseller/profile`
- `/api/v1/reseller/servers`
- `/api/v1/reseller/accounts`
- `/api/v1/reseller/accounts/{id}`
- `/api/v1/telegram/plans`
- `/api/v1/telegram/reseller/apply`
- `/api/v1/telegram/reseller/status/{user_id}`
- `/api/v1/test/vpn/create` and `/api/v1/test/vpn/delete/{id}`

The reseller account APIs use Sanctum authentication. Review the route middleware before exposing any module API publicly; some Telegram/testing endpoints are integration endpoints rather than a general public customer API.

## Environment configuration

`.env.example` contains the Laravel defaults. The most important deployment values are:

| Variable | Purpose |
|---|---|
| `APP_ENV` | Use `production` on a live server. |
| `APP_DEBUG` | Keep `false` in production. |
| `APP_URL` | Public HTTPS URL; also used to construct Telegram webhook URLs. |
| `APP_KEY` | Laravel encryption key; generate it once with `php artisan key:generate`. |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | First-seed admin credentials only. |
| `DB_*` | MySQL/MariaDB connection. |
| `SESSION_DRIVER` | Default is database; the sessions migration must be present. |
| `CACHE_STORE` | Default is database; Redis is also available. |
| `QUEUE_CONNECTION` | Installer example uses Redis; requires a worker. |
| `REDIS_*` | Redis connection for queues/cache. |
| `FILESYSTEM_DISK` | Default local disk. Public uploads use the configured public disk and storage link. |
| `MAIL_*` | Real email delivery for verification/reset messages. The example uses the log mailer. |
| `TELEGRAM_PROXY` | Optional proxy for Telegram HTTP requests. |
| `HTTP_PROXY` | Optional proxy used by XUI HTTP requests. |
| `TELEGRAM_BOT_TOKEN` | Package-level Telegram fallback; the bot's application token should be saved in Filament settings. |
| `VITE_APP_NAME` | Frontend build-time application name. |

Panel credentials, payment cards, referral rules, theme content, Telegram settings, trial settings, and most business settings are intentionally stored in the database and configured from Filament rather than from `.env`.

## Security and known limitations

### Security practices included

- Laravel authentication, CSRF protection, password hashing, signed email verification URLs, and session regeneration.
- Filament admin access requires `is_admin` and a non-banned account.
- Website and Telegram access are both blocked for banned users.
- Eloquent and request validation are used for database/user input.
- Subscription import limits input length and blocks common private/local SSRF targets.
- Telegram Markdown/HTML output has escaping and plain-text fallbacks in important delivery paths.
- Queue workers, database transactions, row locks, and duplicate checks protect wallet and sequential-name operations.

### Important production limitations

- **SSL verification:** panel HTTP services and the subscription importer currently disable TLS certificate verification for compatibility. Prefer trusted panel certificates and restrict panel access at the network layer.
- **Importer rate limiting:** the import endpoints are authenticated but are not currently protected by a dedicated per-user rate limiter. Add throttling before exposing the feature to an untrusted/high-volume audience.
- **VLESS-only import parsing:** non-VLESS entries in a subscription are ignored; the first valid VLESS entry supplies the imported UUID.
- **Imported usage:** imported metadata stores the usage observed during import. The dashboard does not continuously poll the remote panel for live usage.
- **Imported renewals:** an import with no matching active plan may have a null `plan_id`; create an appropriate active plan before relying on the normal renewal flow.
- **NOWPayments/crypto:** the NOWPayments webhook currently updates order status/payment ID; the crypto website flow is a placeholder. Verify and complete provisioning, signature validation, and reconciliation before treating either as a production payment gateway.
- **Email:** `.env.example` uses the log mailer. Configure a real mail transport if email verification or password reset is required.
- **Backups:** the backup module executes `mysqldump`/`mysql` commands and restores database/public files. Restrict admin access and test restore archives securely.
- **Route cache:** the project currently defines several closure routes, so `route:cache` may fail. Do not leave the application in maintenance mode if a bundled update stops at that step.
- **Native clients:** Android/iOS/Windows applications are not part of this repository; only tutorials and VPN links are delivered.
- **Remote panel consistency:** editing a stored order link in Filament changes Mehrgan only. Use the panel API or the panel itself to change the remote account.

## Support and contribution

- Telegram support group: [Mehrgan Official Support](https://t.me/Mehrgan_OfficialSupport)
- Video/tutorial channel: [Iran Eclips on YouTube](https://www.youtube.com/@iraneclips8168/videos)

For a bug report, include the Laravel/PHP versions, the relevant module, a sanitized log excerpt, and reproducible steps. Never include `.env`, bot tokens, panel passwords, private subscription URLs, or raw customer UUIDs in an issue.

The project metadata declares the MIT license. See `composer.json` and the repository's GitHub project for the applicable license and contribution terms.
