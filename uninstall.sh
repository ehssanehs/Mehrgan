#!/bin/bash

# ==================================================================================
# ===        اسکریپت حذف کامل پروژه VPNMarket — آماده‌سازی برای نصب مجدد         ===
# ==================================================================================
# این اسکریپت همه‌چیز را پاک می‌کند:
#   • پوشه پروژه
#   • دیتابیس و کاربر دیتابیس
#   • کانفیگ‌های Nginx و Supervisor
#   • گواهی SSL
#   • کش‌های Composer و NPM
#   • cron job های مرتبط
# ==================================================================================

set -e

# --- رنگ‌ها ---
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
NC='\033[0m'

PROJECT_PATH="/var/www/vpnmarket"

# --- بررسی دسترسی root ---
if [[ $EUID -ne 0 ]]; then
    echo -e "${RED}این اسکریپت باید با دسترسی root اجرا شود (sudo).${NC}"
    exit 1
fi

# --- پارامترها ---
FORCE=false
DOMAIN=""
while [[ $# -gt 0 ]]; do
    case "$1" in
        --force|-f) FORCE=true ;;
        --domain=*) DOMAIN="${1#*=}" ;;
        --domain|-d) DOMAIN="$2"; shift ;;
        *) echo "پارامتر ناشناخته: $1"; exit 1 ;;
    esac
    shift
done

echo
echo -e "${CYAN}=====================================================${NC}"
echo -e "${CYAN}   اسکریپت حذف کامل پروژه VPNMarket${NC}"
echo -e "${CYAN}=====================================================${NC}"
echo
echo -e "${RED}⚠️  هشدار: این عملیات غیرقابل بازگشت است.${NC}"
echo -e "${RED}   تمام فایل‌ها، دیتابیس، کانفیگ‌ها و گواهی SSL حذف خواهند شد.${NC}"
echo
echo -e "مسیر پروژه: ${YELLOW}${PROJECT_PATH}${NC}"
echo

# --- خواندن اطلاعات از env. ---
DB_NAME=""
DB_USER=""
if [ -f "$PROJECT_PATH/.env" ]; then
    DB_NAME=$(grep -E '^DB_DATABASE=' "$PROJECT_PATH/.env" 2>/dev/null | cut -d'=' -f2 | tr -d ' \t\r\n' || echo "")
    DB_USER=$(grep -E '^DB_USERNAME=' "$PROJECT_PATH/.env" 2>/dev/null | cut -d'=' -f2 | tr -d ' \t\r\n' || echo "")

    if [ -z "$DOMAIN" ]; then
        DOMAIN=$(grep -E '^APP_URL=' "$PROJECT_PATH/.env" 2>/dev/null | sed 's|^APP_URL=||;s|https\?://||;s|/.*||' | tr -d ' \t\r\n' || echo "")
    fi
fi

if [ -n "$DB_NAME" ]; then
    echo -e "دیتابیس:     ${YELLOW}${DB_NAME}${NC}"
fi
if [ -n "$DB_USER" ]; then
    echo -e "کاربر دیتابیس: ${YELLOW}${DB_USER}${NC}"
fi
if [ -n "$DOMAIN" ]; then
    echo -e "دامنه:        ${YELLOW}${DOMAIN}${NC}"
fi

# اگر نمی‌توانستیم از env بخوانیم، دستی بپرسیم
if [ -z "$DB_NAME" ]; then
    read -p "نام دیتابیس را وارد کنید: " DB_NAME
fi
if [ -z "$DB_NAME" ] && [ "$FORCE" != true ]; then
    echo -e "${RED}نام دیتابیس مشخص نشد. عملیات متوقف شد.${NC}"
    exit 1
fi

echo

# --- تأیید نهایی ---
if [ "$FORCE" != true ]; then
    read -p "آیا از حذف کامل مطمئن هستید؟ (y/n): " CONFIRM
    if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then
        echo -e "${YELLOW}عملیات لغو شد.${NC}"
        exit 0
    fi
fi

echo
echo -e "${YELLOW}در حال حذف کامل پروژه...${NC}"
echo

# ═══════════════════════════════════════════════════════════
# مرحله ۱: توقف تمام سرویس‌ها
# ═══════════════════════════════════════════════════════════
echo -e "${YELLOW}[1/8] توقف سرویس‌ها...${NC}"

# توقف worker های Supervisor
if command -v supervisorctl &>/dev/null; then
    supervisorctl stop vpnmarket-worker:* 2>/dev/null || true
fi

# توقف Nginx
systemctl stop nginx 2>/dev/null || true

# توقف PHP-FPM (اگر نام استاندارد باشد)
systemctl stop php*-fpm 2>/dev/null || true

echo -e "  ${GREEN}✓ سرویس‌ها متوقف شدند.${NC}"

# ═══════════════════════════════════════════════════════════
# مرحله ۲: حذف کانفیگ‌های Nginx
# ═══════════════════════════════════════════════════════════
echo -e "${YELLOW}[2/8] حذف کانفیگ‌های Nginx...${NC}"
rm -f /etc/nginx/sites-available/vpnmarket     2>/dev/null || true
rm -f /etc/nginx/sites-enabled/vpnmarket       2>/dev/null || true
rm -f /etc/nginx/conf.d/vpnmarket.conf          2>/dev/null || true
echo -e "  ${GREEN}✓ کانفیگ‌های Nginx حذف شدند.${NC}"

# ═══════════════════════════════════════════════════════════
# مرحله ۳: حذف کانفیگ‌های Supervisor
# ═══════════════════════════════════════════════════════════
echo -e "${YELLOW}[3/8] حذف کانفیگ‌های Supervisor...${NC}"
rm -f /etc/supervisor/conf.d/vpnmarket-worker.conf 2>/dev/null || true
if command -v supervisorctl &>/dev/null; then
    supervisorctl reread  2>/dev/null || true
    supervisorctl update  2>/dev/null || true
fi
echo -e "  ${GREEN}✓ کانفیگ‌های Supervisor حذف شدند.${NC}"

# ═══════════════════════════════════════════════════════════
# مرحله ۴: حذف cron job های مرتبط
# ═══════════════════════════════════════════════════════════
echo -e "${YELLOW}[4/8] حذف cron job های پروژه...${NC}"
if [ -f /etc/cron.d/vpnmarket ]; then
    rm -f /etc/cron.d/vpnmarket 2>/dev/null || true
fi
# همچنین خطوط vpnmarket را از crontab root حذف کن
if crontab -l 2>/dev/null | grep -qi 'vpnmarket'; then
    crontab -l 2>/dev/null | grep -vi 'vpnmarket' | crontab - 2>/dev/null || true
fi
echo -e "  ${GREEN}✓ cron job ها حذف شدند.${NC}"

# ═══════════════════════════════════════════════════════════
# مرحله ۵: حذف گواهی SSL
# ═══════════════════════════════════════════════════════════
echo -e "${YELLOW}[5/8] حذف گواهی SSL...${NC}"
if [ -n "$DOMAIN" ] && command -v certbot &>/dev/null; then
    certbot delete --cert-name "$DOMAIN" --non-interactive 2>/dev/null || {
        # Fallback: try deleting by domain match
        certbot certificates 2>/dev/null | grep -q "$DOMAIN" && certbot delete --cert-name "$DOMAIN" --non-interactive 2>/dev/null || true
    }
    echo -e "  ${GREEN}✓ گواهی SSL برای ${DOMAIN} حذف شد.${NC}"
else
    echo -e "  ${YELLOW}⊘ گواهی SSL برای حذف یافت نشد (یا certbot نصب نیست).${NC}"
fi

# ═══════════════════════════════════════════════════════════
# مرحله ۶: حذف دیتابیس و کاربر
# ═══════════════════════════════════════════════════════════
echo -e "${YELLOW}[6/8] حذف دیتابیس و کاربر MySQL...${NC}"
if [ -n "$DB_NAME" ]; then
    mysql -e "DROP DATABASE IF EXISTS \`$DB_NAME\`;" 2>/dev/null || {
        echo -e "  ${YELLOW}⚠️  حذف دیتابیس با خطا مواجه شد (ممکن است قبلاً حذف شده باشد).${NC}"
    }
    echo -e "  ${GREEN}✓ دیتابیس '${DB_NAME}' حذف شد.${NC}"
fi

if [ -n "$DB_USER" ]; then
    mysql -e "DROP USER IF EXISTS '$DB_USER'@'localhost';" 2>/dev/null || true
    mysql -e "DROP USER IF EXISTS '$DB_USER'@'%';"         2>/dev/null || true
    mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true
    echo -e "  ${GREEN}✓ کاربر '${DB_USER}' حذف شد.${NC}"
fi

# ═══════════════════════════════════════════════════════════
# مرحله ۷: حذف کامل فایل‌های پروژه
# ═══════════════════════════════════════════════════════════
echo -e "${YELLOW}[7/8] حذف کامل پوشه پروژه...${NC}"
if [ -d "$PROJECT_PATH" ]; then
    rm -rf "$PROJECT_PATH"
    echo -e "  ${GREEN}✓ پوشه ${PROJECT_PATH} به طور کامل حذف شد.${NC}"
else
    echo -e "  ${YELLOW}⊘ پوشه پروژه قبلاً حذف شده است.${NC}"
fi

# پاکسازی پوشه کش NPM اگر وجود داشته باشد
rm -rf /var/www/.npm 2>/dev/null || true

# ═══════════════════════════════════════════════════════════
# مرحله ۸: ری‌استارت و تمیزکاری نهایی
# ═══════════════════════════════════════════════════════════
echo -e "${YELLOW}[8/8] تمیزکاری و ری‌استارت Nginx...${NC}"
systemctl restart nginx 2>/dev/null || systemctl start nginx 2>/dev/null || true

# پاکسازی کش apt (در صورت نصب بودن certbot از طریق apt)
if command -v apt-get &>/dev/null; then
    apt-get autoremove --purge -y certbot python3-certbot-nginx 2>/dev/null || true
fi

echo -e "  ${GREEN}✓ Nginx ری‌استارت شد.${NC}"

# ═══════════════════════════════════════════════════════════
# پایان
# ═══════════════════════════════════════════════════════════
echo
echo -e "${GREEN}=====================================================${NC}"
echo -e "${GREEN}✅  حذف کامل پروژه با موفقیت انجام شد.${NC}"
echo -e "${GREEN}    سرور برای نصب مجدد کاملاً آماده است.${NC}"
echo -e "${GREEN}=====================================================${NC}"
echo
echo -e "برای نصب مجدد:"
echo -e "  ${CYAN}bash install.sh${NC}"
echo
