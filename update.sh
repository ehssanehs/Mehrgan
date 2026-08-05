#!/bin/bash

# ==============================================================================
# ===          اسکریپت آپدیت هوشمند برای یک نمونه Mehrgan                  ===
# ===  اجرا از داخل پوشه نمونه: cd /var/www/mehrgan-1 && sudo bash update.sh
# ===  هر نمونه مستقل آپدیت می‌شود بدون تأثیر بر نمونه‌های دیگر              ===
# ==============================================================================

set -e

# --- رنگ‌ها ---
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
RED='\033[0;31m'
NC='\033[0m'

WEB_USER="www-data"

# --- تشخیص خودکار مسیر پروژه ---
PROJECT_PATH="$(cd "$(dirname "$0")" && pwd)"

# اگر از خط فرمان مسیر دیگری داده شد
while [[ $# -gt 0 ]]; do
    case "$1" in
        --path=*) PROJECT_PATH="${1#*=}" ;;
        --path|-p) PROJECT_PATH="$2"; shift ;;
        *) echo "پارامتر ناشناخته: $1"; exit 1 ;;
    esac
    shift
done

INSTANCE_SLUG=$(basename "$PROJECT_PATH")

echo
echo -e "${CYAN}--- آپدیت نمونه '${INSTANCE_SLUG}' ---${NC}"
echo -e "مسیر: ${PROJECT_PATH}"
echo

# --- بررسی‌های اولیه ---
if [ ! -d "$PROJECT_PATH" ]; then
    echo -e "${RED}خطا: پوشه '${PROJECT_PATH}' وجود ندارد.${NC}"
    exit 1
fi

if [ ! -f "$PROJECT_PATH/.env" ]; then
    echo -e "${RED}خطا: فایل .env در '${PROJECT_PATH}' یافت نشد!${NC}"
    exit 1
fi

cd "$PROJECT_PATH"

# --- مرحله ۰: تشخیص برنچ ---
CURRENT_BRANCH=$(sudo git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "main")
if [ -z "$CURRENT_BRANCH" ] || [ "$CURRENT_BRANCH" = "HEAD" ]; then
    CURRENT_BRANCH="main"
fi
echo -e "برنچ فعال: ${GREEN}${CURRENT_BRANCH}${NC}"
echo

# --- مرحله ۱: آماده‌سازی ---
echo -e "${YELLOW}مرحله ۱ از ۸: آماده‌سازی محیط و حالت تعمیر...${NC}"

sudo mkdir -p /var/www/.npm
sudo chown -R $WEB_USER:$WEB_USER /var/www/.npm

sudo cp .env ".env.bak.$(date +%Y-%m-%d_%H-%M-%S)"
echo "نسخه پشتیبان از .env ساخته شد."

sudo -u $WEB_USER php artisan down --retry=60 || true

# --- مرحله ۲: دریافت آخرین کدها ---
echo -e "${YELLOW}مرحله ۲ از ۸: دریافت آخرین تغییرات (برنچ ${CURRENT_BRANCH})...${NC}"

sudo git fetch origin --prune
if sudo git diff --quiet && sudo git diff --cached --quiet; then
    sudo git reset --hard "origin/${CURRENT_BRANCH}"
else
    echo -e "${YELLOW}تغییرات محلی شناسایی شد. stash و سپس pull...${NC}"
    sudo git stash
    sudo git reset --hard "origin/${CURRENT_BRANCH}"
fi

COMMIT_HASH=$(sudo git rev-parse --short HEAD 2>/dev/null || echo "unknown")
echo -e "نسخه فعلی: ${GREEN}${COMMIT_HASH}${NC}"

# --- مرحله ۳: دسترسی فایل‌ها ---
echo -e "${YELLOW}مرحله ۳ از ۸: تنظیم دسترسی‌های فایل...${NC}"
sudo chown -R $WEB_USER:$WEB_USER .
sudo chmod -R 775 storage bootstrap/cache
sudo chmod -R 775 database

# --- مرحله ۴: آپدیت Composer ---
echo -e "${YELLOW}مرحله ۴ از ۸: آپدیت پکیج‌های PHP...${NC}"
sudo -u $WEB_USER composer install --no-dev --optimize-autoloader --no-interaction

# --- مرحله ۵: آپدیت NPM ---
echo -e "${YELLOW}مرحله ۵ از ۸: آپدیت پکیج‌های Node.js و کامپایل assets...${NC}"
sudo -u $WEB_USER HOME=/var/www npm install --no-audit --no-fund
sudo -u $WEB_USER HOME=/var/www npm run build

# --- مرحله ۶: مهاجرت دیتابیس و ری‌استارت ورکرها ---
echo -e "${YELLOW}مرحله ۶ از ۸: مهاجرت دیتابیس و ری‌استارت سرویس‌ها...${NC}"

sudo -u $WEB_USER php artisan storage:link 2>/dev/null || true
sudo -u $WEB_USER php artisan migrate --force

# ری‌استارت ورکرهای این نمونه فقط
if command -v supervisorctl &>/dev/null; then
    sudo supervisorctl restart "${INSTANCE_SLUG}-worker:*" 2>/dev/null || \
        echo -e "${YELLOW}⚠️ ری‌استارت ورکرهای '${INSTANCE_SLUG}' ناموفق بود.${NC}"
fi

# --- مرحله ۷: بهینه‌سازی ---
echo -e "${YELLOW}مرحله ۷ از ۸: بهینه‌سازی و کش‌گذاری...${NC}"

sudo -u $WEB_USER php artisan optimize:clear
sudo -u $WEB_USER php artisan config:cache
sudo -u $WEB_USER php artisan route:cache
sudo -u $WEB_USER php artisan view:cache
sudo -u $WEB_USER php artisan event:cache

# --- مرحله ۸: فعال‌سازی ---
echo -e "${YELLOW}مرحله ۸ از ۸: فعال‌سازی مجدد سایت...${NC}"
sudo -u $WEB_USER php artisan up

echo
echo -e "${GREEN}=====================================================${NC}"
echo -e "${GREEN}✅ نمونه '${INSTANCE_SLUG}' با موفقیت آپدیت شد!${NC}"
echo -e "${GREEN}   برنچ: ${CURRENT_BRANCH} | کامیت: ${COMMIT_HASH}${NC}"
echo -e "${GREEN}   مسیر: ${PROJECT_PATH}${NC}"
echo -e "${GREEN}   تاریخ: $(date '+%Y-%m-%d %H:%M:%S')${NC}"
echo -e "${GREEN}=====================================================${NC}"
