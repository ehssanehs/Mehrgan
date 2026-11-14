# Mehrgan — Project Index (نقشه پروژه)

> مرجع سریع برای کار روی این ریپو. هر تغییری در معماری یا فلوها باید همین‌جا به‌روز شود.
> آخرین بازبینی: کامل‌خوانی کل کد (۴۵۰ فایل PHP، ~۴۱٬۰۰۰ خط).

## 1. شناسنامه

| مورد | مقدار |
|---|---|
| ریپو | `github.com/ehssanehs/Mehrgan` (main) |
| مسیر محلی | `/root/Mehrgan` |
| نوع | Laravel 12 + Filament 3 (پنل `/admin`) + ربات تلگرام + ماژول‌های nwidart |
| PHP | `^8.3` |
| پکیج‌های کلیدی | `filament/filament ^3.2`, `irazasyed/telegram-bot-sdk ^3.15`, `nwidart/laravel-modules`, `morilog/jalali`, `simplesoftwareio/simple-qrcode`, `doctrine/dbal ^4.3` |
| فرانت | Vite 7 + Tailwind 3 + Alpine (فقط بیلد asset؛ UI اصلی Blade است) |
| DB | MySQL 8 / MariaDB — Redis برای Queue، Cache پیش‌فرض `database` |
| زبان UI | فارسی (RTL)، تاریخ شمسی با `to_jalali_date()` |
| تست | Pest 4 (`php artisan test`)، تست‌های واقعی برای import/naming/referral/ban/PasarGuard |

محصول: فروشگاه + پنل مدیریت فروش سرویس VPN با اتصال به پنل‌های **Marzban**، **Sanaei/X-UI (3x-ui)** و **PasarGuard**.

## 2. ساختار پوشه‌ها

```
app/
  Http/Controllers/     OrderController (هسته خرید/پرداخت), SubscriptionImportController,
                        WebhookController (NOWPayments), ProfileController, Auth/*
  Services/             MarzbanService, XUIService, PasarGuardService,
                        SubscriptionImportService, PanelSearchService,
                        VlessParserService, SubscriptionFetcherService,
                        ClientNamingService, TelegramOrderNotificationService
  Traits/               ManagesServiceProvisioning (نسخه قدیمی/فیلامنت), RequiresChannelMembership
  Models/               User, Plan, Order, Setting, Transaction, DiscountCode,
                        DiscountCodeUsage, Inbound, Notification, SequentialNamingSetting,
                        TelegramBotSetting, Agent/AgentServer/AgentTransaction (legacy)
  Filament/             Resources (Order, User, Plan, DiscountCode, Agent*), Pages (تنظیمات), Widgets
  Support/SiteTheme.php پالت ۹ قالب + اعمال روی پنل ادمین
Modules/
  TelegramBot/          ★ WebhookController.php ≈ ۶٬۰۰۰ خط = کل ربات
  MultiServer/          Location + Server (ms_locations/ms_servers) + تنظیمات نمایش ظرفیت
  Reseller/             نمایندگی: plans, requests, wallets, accounts, vpn_servers/products + API
  Ticketing/            Ticket + TicketReply + Events/Listeners (اعلان تلگرام)
  Referral/             گزارش دعوت‌ها (Filament Resource) — منطق واقعی در Listener اصلی
  Blog/                 پست و دسته + کنترلر وب
  MatinBackup/          BackupService (mysqldump → zip → تلگرام) + دستور روزانه
database/migrations/    اسکیمای اصلی (users/plans/orders/transactions/settings/discount...)
resources/views/        dashboard, payment/*, wallet, subscription/import, themes/*
public/themes/          asset و shell هر ۹ قالب
install.sh / update.sh / manage.sh / uninstall.sh   ← نصب چندنمونه‌ای روی Ubuntu
```

## 3. مدل داده (جداول کلیدی)

- **users**: `is_admin`, `telegram_chat_id`, `bot_state` (FSM ربات), `balance`, `referral_code` (auto `REF-XXXXXX`), `referrer_id`, `trial_accounts_taken`, `show_renewal_notification`, `is_banned/banned_at/ban_reason/banned_by`.
- **plans**: `name, price, volume_gb, duration_days, features(json), is_popular, is_active, server_type` (`all|xui|marzban`).
- **orders**: `user_id, plan_id(nullable = شارژ کیف پول), server_id, status(pending|paid|rejected|expired), source(web|telegram|telegram_deposit|telegram_renewal), payment_method(card|wallet|crypto|imported), amount, discount_amount, discount_code_id, config_details, expires_at, panel_username, panel_client_id(UUID), panel_sub_id, reserved_slot, renews_order_id, is_imported, import_meta(json), card_payment_receipt`.
- **transactions**: `user_id, order_id, amount, type(deposit|purchase|refund|withdrawal|referral_reward|manual adjustment...), status, description, metadata(json)`.
- **settings**: key/value عمومی — همه تنظیمات سایت و ربات (فهرست در §7).
- **discount_codes** + **discount_code_usages** (unique روی code+user+order).
- **ms_locations / ms_servers**: `type(xui|marzban|pasarguard), ip_address, port, username, password, path, inbound_id, capacity, current_users, link_type(single|subscription|tunnel), subscription_domain/path/port, tunnel_address/port/is_https, marzban_node_hostname, pasarguard_node_hostname`.
- **tickets / ticket_replies**، **blog_posts / blog_categories**، **faqs**، **notifications**، **sequential_naming_settings** (singleton: prefix/counter/is_enabled).
- **reseller_\***: `reseller_plans(type=quota|pay_as_you_go)`, `resellers`, `reseller_wallets`, `reseller_transactions`, `reseller_accounts`, `reseller_requests`, `vpn_servers`, `vpn_products`, `reseller_logs`.
- **agents / agent_servers / agent_transactions**: مدل قدیمی نمایندگی، فقط برای سازگاری؛ منطق فعال در ماژول Reseller است.

نکته: `orders.server_id` توسط hook `Order::saving` به‌صورت خودکار روی کم‌مصرف‌ترین سرور فعال با ظرفیت (فیلترشده بر `plan.server_type`) ست می‌شود.

## 4. جریان‌های اصلی (بدانید تا باگ نزنید)

### 4.1 خرید از وب
`POST /order/{plan}` → اگر ماژول MultiServer هست → `select-server` (لوکیشن/سرور/نام کاربری دلخواه) → ساخت order pending → `payment.show`
- **کیف پول**: `OrderController::processWalletPayment` — همه‌چیز داخل `DB::transaction`: کسر موجودی، ثبت discount usage، **provision در پنل**، آپدیت order، Transaction، `OrderPaid::dispatch`. خطا ⇒ rollback + نوتیفیکیشن `payment_failed`.
- **کارت‌به‌کارت**: `showCardPaymentPage` (کارت رندوم از `Setting::getRandomPaymentCard()`) → `submitCardReceipt` (ذخیره `receipts/` روی disk public + `notifyAdminsOfReceipt`) → ادمین تأیید می‌کند.
- **تأیید ادمین** دو مسیر موازی دارد و **هر دو باید هم‌زمان اصلاح شوند**:
  1. `OrderResource::table → Action approve/reject` (پنل Filament)
  2. `Modules\TelegramBot\...\WebhookController::handleAdminApproveCallback` (دکمه‌های زیر پیام رسید در تلگرام)
- **رد فیش**: `reject` → اگر سفارش paid بود، `disableVpnAccount` روی پنل (XUI: `enable=false` + expiry=now؛ Marzban/PasarGuard: `disableUser`)، وضعیت `rejected`، پیام `sendPaymentRejected` با دلیل.

### 4.2 خرید از تلگرام
`WebhookController` یک FSM دستی روی `users.bot_state` دارد:
`awaiting_username_for_order|<planId>[|selected_loc:<id>]`، `awaiting_discount_code|<orderId>`، `waiting_receipt_<orderId>`، `awaiting_deposit_amount`، `awaiting_import_subscription`، `awaiting_new_ticket_subject`، `awaiting_new_ticket_message|<subject>`، `awaiting_ticket_reply|<ticketId>`، `awaiting_admin_rejection_reason|<orderId>`.
جریان: پلن → مدت (`show_duration_*`) → پلن (`buy_plan_*`) → لوکیشن (اگر `enable_multilocation`) → نام کاربری (اگر sequential فعال باشد پرسیده نمی‌شود) → invoice → پرداخت کیف پول (`pay_wallet_order_*`) یا کارت (`pay_card_*`) → رسید عکس → تأیید ادمین.
- پرداخت کیف پول ربات: `processWalletPayment` با `lockForUpdate` روی کاربر، provision، `OrderPaid::dispatch` بعد از commit، سپس `TelegramOrderNotificationService::sendServiceActivated`.
- `provisionUserAccount` (ساخت) و `renewUserAccount` (تمدید) نسخه‌های مستقل ربات هستند و با `ManagesServiceProvisioning` / `OrderResource` سه‌بار تکرار شده‌اند.

### 4.3 تمدید
- وب: `POST /order/{order}/renew` با `plan_id` اختیاری → order جدید با `renews_order_id` → پرداخت → `expires_at` **از لحظه تمدید** = `now + duration_days` (نه افزودن به انقضای قبلی) و `data_limit` جایگزین می‌شود؛ مصرف ترافیک ریست می‌شود (best-effort).
- تلگرام: `renew_order_<id>` → انتخاب پکیج `renew_plan_<order>_<plan>` → `renew_pay_wallet_*` / `renew_pay_card_*`.
- `showServiceDetails` اگر تمدید موفق جدیدی وجود داشته باشد `plan_id` سفارش اصلی را sync می‌کند.
- Marzban در تمدید اگر `updateUser` شکست بخورد، کاربر دوباره **create** می‌شود (recreate) تا تمدید از دست نرود.

### 4.4 وارد کردن اشتراک قبلی (import)
`SubscriptionImportService::import($input, $user, $source)`:
1. `VlessParserService::detectInputType` → `vless` یا `url`
2. URL: `SubscriptionFetcherService::fetch` (SSRF guard: فقط http/https، بلاک localhost/private IP + DNS check، ۱۵s timeout، `verify=false`)
3. محتوا → base64 decode در صورت نیاز → **فقط UUID اولین VLESS**
4. duplicate check روی `orders.panel_client_id` (برای `source=telegram` idempotent است و همان order را برمی‌گرداند)
5. `PanelSearchService::searchByUuid` → همه `ms_servers` فعال، سپس تنظیمات legacy (xui/marzban/pasarguard)
6. ساخت order با `status=paid, payment_method=imported, is_imported=true, import_meta`
7. `syncOrderWithPanel` فوراً صدا زده می‌شود؛ داشبورد حداکثر هر ۲۴ ساعت یک‌بار برای هر order سینک می‌کند.
- `parseExpiryValue` واحدهای ثانیه/میلی‌ثانیه/میکروثانیه/رشته ISO را تشخیص می‌دهد؛ `0` و منفی = نامحدود.

### 4.5 اکانت تست
`handleTrialRequest`: چک `trial_enabled` و `trial_limit_per_user` → سرور `trial_server_id` یا اولین سرور با ظرفیت → ساخت کلاینت با `trial_volume_mb` و `trial_duration_hours` → لینک در `Cache` ۱۰ دقیقه (`trial_link_{userId}`) برای دکمه کپی/QR → `trial_accounts_taken++`.

### 4.6 دعوت (Referral)
`AppServiceProvider::boot` کد `REF-XXXXXX` را روی `User::creating` می‌سازد.
پاداش فقط از `App\Listeners\RewardReferrerListener` روی `OrderPaid` داده می‌شود: چک فعال بودن، `referrer_id`، فقط اولین خرید (`isFirstPaidOrder`)، idempotency روی `metadata->referral_reward`/`order_id`، حداقل مبلغ، پاداش ثابت + درصدی → `balance++` + Transaction + پیام تلگرام.
⚠️ هر مسیری که سفارش را paid می‌کند **باید** `OrderPaid::dispatch` کند (خرید کیف پول ربات قبلاً این را نداشت و پاداش نمی‌داد).

### 4.7 نمایندگی (Reseller)
- `reseller_requests` → تأیید در `ResellerRequestResource::approve` ⇒ ساخت `Reseller` + `ResellerWallet` (شارژ با `payment_amount`) + آپدیت `agents` قدیمی + پیام تلگرام.
- ساخت اکانت: `ResellerAccountService::createAccountRequest` (چک وضعیت/سهمیه/موجودی، کسر کیف پول، `ResellerAccount` pending) → `CreateResellerAccountJob` (۳ تلاش، backoff 10/30/60) → `VpnServiceFactory` (`sanaei|marzban|pasarguard`) → در شکست نهایی: `status=failed` + **refund** خودکار.
- API: `/api/v1/reseller/*` با `auth:sanctum`؛ `/api/v1/telegram/*` بدون احراز هویت (داخلی).

### 4.8 تیکت
سایت: `TicketController` (create/store/show/reply) + Event `TicketCreated`/`TicketReplied`.
تلگرام: `processTicketConversation` (ساخت تیکت، پاسخ، پیوست عکس) + دکمه‌های ادمین `admin_reply_ticket_*` و `admin_close_ticket_*` که از `Cache` استفاده می‌کنند چون ادمین ممکن است User نداشته باشد.
اعلان‌ها با `parse_mode=HTML` ارسال می‌شوند (`escapeTelegramHTML`) — تغییر به MarkdownV2 باعث خطای parse می‌شود.

## 5. لایه پنل VPN (Services)

| پنل | احراز | ساخت | تمدید | غیرفعال‌سازی | جستجوی UUID |
|---|---|---|---|---|---|
| Marzban | `POST /api/admin/token` (form) → Bearer | `POST /api/user` (JSON خام) | `PUT /api/user/{u}` + `POST /api/user/{u}/reset` | `PUT` با expire=now, data_limit=0 | `getAllUsers` صفحه‌بندی و اسکن `proxies[].id`/`password`/`links` |
| X-UI | `POST {panel}/login` با CookieJar | `POST /panel/api/inbounds/addClient` (سه endpoint جایگزین) | `updateClient` + `resetClientTraffic` | `updateClient` با `enable=false` | `getInbounds` → `getClients` هر inbound |
| PasarGuard | `POST /api/admin/token` → Bearer | `POST /api/user` + **الزام `group_ids`** (همه گروه‌های فعال) | `PUT /api/user/{u}` + reset | `PUT` status=disabled | `getAllUsers` و اسکن `proxy_settings` |

نکات حساس:
- Marzban `updateUser` فقط فیلدهای ارسال‌شده را عوض می‌کند و ارسال `proxies`/`inbounds` بقیه را **پاک می‌کند** ⇒ در تمدید فقط `expire` و `data_limit` بفرستید.
- PasarGuard کاربر بدون group هیچ inbound ندارد ⇒ اگر گروه‌ها لود نشوند ساخت کاربر باید fail شود (`pasarguard_groups_unavailable/empty`).
- X-UI: لینک‌ها سه حالت `single` / `subscription` (`/sub/{subId}`) / `tunnel` (vless روی `tunnel_address`)؛ تبدیل خودکار پورت `2053 → 2096` برای subscription وجود دارد.
- `getClientTraffics/{email}` منبع معتبر حجم/انقضاست؛ کپی داخل inbound settings ممکن است صفر باشد.

## 6. تنظیمات (settings table) — کلیدهای مهم

- عمومی: `active_theme` (۹ قالب: welcome/rocket/arcane/cyberpunk/dragon/phoenix/nebula/aurora/obsidian)، `site_login_url`، `payment_cards` (JSON repeater)، `payment_card_instructions`، `min_deposit_amount`.
- تک‌سرور (legacy): `panel_type` (`xui|marzban|pasarguard`)، `xui_host/user/pass/default_inbound_id/link_type/subscription_url_base`، `marzban_host/sudo_username/sudo_password/node_hostname`، `pasarguard_host/username/password/node_hostname`.
- چندسروری: `enable_multilocation`، `ms_show_capacity`، `ms_hide_full_locations`، `ms_full_location_message`.
- تلگرام: `telegram_bot_token`، `telegram_admin_chat_id` (JSON array یا تک‌مقدار)، `force_join_enabled`، `telegram_required_channel_id`، `telegram_private_channel_invite_link`، `tg_show_trial_button`، `tg_show_reseller_button`؛ متن‌ها در جدول `telegram_bot_settings` (welcome/start/tutorials/deposit_amounts).
- تست: `trial_enabled` (fallback: `test_account_enabled`)، `trial_volume_mb`، `trial_duration_hours`، `trial_limit_per_user`، `trial_server_id`.
- رفرال: `referral_enabled`, `referral_referrer_reward`, `referral_referrer_reward_percent`, `referral_welcome_gift`, `referral_min_purchase_for_reward`, `referral_reward_only_first_purchase`, `referral_ip_check`, `referral_telegram_notify_*`, سه قالب پیام.
- مقادیر ممکن است JSON-encode شده باشند ⇒ همیشه با `Setting::normalizeValue()` / `decodeArrayValue()` بخوانید. کش: `setting()` روی `Cache::rememberForever('settings')`؛ بعد از ذخیره `Cache::forget('settings')` الزامی است.

## 7. مسیرها

```
GET  /                              صفحه اصلی (قالب active_theme)
GET  /dashboard                     سرویس‌ها/سفارش‌ها/کیف پول/تیکت‌ها
GET|POST /subscription/import       ورود اشتراک قبلی (+ /api → JSON)
GET|POST /wallet/charge             شارژ کیف پول
POST /order/{plan}                  ساخت سفارش
GET  /order/{order}                 انتخاب روش پرداخت
POST /order/{order}/renew           تمدید (plan_id اختیاری)
GET  /payment/card/{order}          صفحه کارت‌به‌کارت
POST /payment/card/{order}/submit   ارسال رسید
POST /payment/wallet/{order}        پرداخت آنی کیف پول
POST /webhooks/telegram             ربات
POST /webhooks/nowpayments          NOWPayments (فقط status را paid می‌کند، provision نمی‌کند)
/admin                              Filament
/blog, /blog/{slug}
/tickets/*  (auth+verified)
```

## 8. دستورات و محیط

```bash
composer install && npm ci && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # ADMIN_EMAIL / ADMIN_PASSWORD از .env
php artisan storage:link
php artisan telegram:set-webhook    # نیاز به APP_URL عمومی + توکن در settings
php artisan referral:generate-codes # برای کاربران قدیمی بدون کد
php artisan backup:daily-telegram
php artisan test                    # Pest
```
Queue: Redis (`QUEUE_CONNECTION=redis`) — Worker لازم است برای broadcast، reseller job و backup.
پروکسی تلگرام: `TELEGRAM_PROXY` (در `AppServiceProvider` به SDK تزریق می‌شود). پروکسی X-UI: `HTTP_PROXY`.

## 9. نکات/تله‌های شناخته‌شده

1. **سه نسخه از منطق provision** (`OrderController`, `OrderResource`, `WebhookController`, `ManagesServiceProvisioning`). هر باگ پنل را در همه اصلاح کنید.
2. **MarkdownV2**: هر متن داینامیک باید `escape()` شود؛ در کد چند مسیر fallback به plain text وجود دارد (`sendOrEditMessage`). برای رسید/تیکت از HTML استفاده شده.
3. `WebhookController` تابع `escape()` مخصوص خودش را دارد؛ `escapeTelegramHTML()` در `app/helpers.php`.
4. پرداخت NOWPayments فقط وضعیت را paid می‌کند — **سرویس ساخته نمی‌شود** (باگ/نقص شناخته‌شده).
5. `Payment` کارت: تأیید از دو نقطه (Filament و تلگرام) — منطق تکراری و باید هم‌زمان تغییر کند.
6. `manage.sh`/`update.sh` ممکن است `route:cache` بزنند؛ پروژه Route Closure دارد ⇒ `route:cache` می‌شکند.
7. فایل‌های زائد در روت: `mapApiRoutes()`, `mapWebRoutes()`, `name`, `test_dump*.sql` (خالی), `debug-files.php`, `deploy-backup.php` — از نسخه‌های قبلی مانده‌اند.
8. `Modules/Referral` فقط گزارش است؛ منطق پاداش در `app/Listeners/RewardReferrerListener.php`.
9. `Modules/Ticketing` EventServiceProvider دستی در `AppServiceProvider::register` رجیستر شده (نه auto-discovery).
10. `Order::saving` سرور را خودکار انتخاب می‌کند — تست‌هایی که order می‌سازند ممکن است به ماژول MultiServer وابسته شوند.
11. پنل ادمین رنگ/دارک‌مود را از `SiteTheme` می‌گیرد؛ قالب جدید = افزودن به `SiteTheme::PALETTES` + `resources/views/themes/{name}.blade.php` + `public/themes/{name}`.
12. X-UI با `verify=false` و timeout 120s صدا زده می‌شود؛ عملیات provision داخل تراکنش DB انجام می‌شود ⇒ کندی پنل = قفل طولانی.
13. **`enable_multilocation` و `panel_type` فقط از فیلدهای Hidden صفحهٔ «تنظیمات سایت» نوشته می‌شوند** (پیش‌فرض `panel_type=xui`). نصبی که سرورها را فقط از منوی «مولتی سرور» مدیریت کرده باشد، این کلیدها را ندارد. هیچ مسیر provisionی نباید به وجود این کلیدها وابسته باشد (این باگ اکانت تست بود؛ رفع شد). مسیر درست: اگر `Modules\MultiServer\Models\Server` رکورد فعال دارد، از همان استفاده کن و نوع پنل را از `server.type` بگیر.
14. **تست‌ها روی SQLite اجرا نمی‌شوند**: مایگریشن‌ها `ALTER ... MODIFY COLUMN ... ENUM` خام دارند. با MySQL اجرا کنید:
```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=mehrgan_test DB_USERNAME=... DB_PASSWORD=... php artisan test
```
   تست‌های `tests/Unit` بدون `Tests\TestCase` نوشته شده‌اند (resolver خالی ⇒ خطا)؛ فقط Feature قابل اجراست.

## 10. تست‌های موجود (نقشه رفتار)

`tests/Unit`: `VlessParserServiceTest`, `SubscriptionExpiryTest`, `SubscriptionImportServiceTest`, `SubscriptionFetcherServiceTest` (SSRF), `SequentialNamingTest`, `PasarGuardServiceTest` (گروه‌ها/پیلود).
`tests/Feature`: `SubscriptionImportTest` (duplicate UUID)، `TelegramImportTest` (escape تاریخ شمسی/منو)، `TelegramOrderNotificationTest` (HTML + fallback + split)، `ReferralRewardTest`، `UserBanTest`، Auth/Profile.
