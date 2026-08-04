#!/bin/bash

# ==================================================================================
# ===    اسکریپت حذف یک نمونه از VPNMarket — بدون تأثیر بر نمونه‌های دیگر       ===
# ===  استفاده: sudo bash uninstall.sh --slug=vpnmarket-1                        ===
# ===  یا:       sudo bash uninstall.sh  (حذف تعاملی با انتخاب نمونه)             ===
# ==================================================================================

set -e

# --- رنگ‌ها ---
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# --- بررسی دسترسی root ---
if [[ $EUID -ne 0 ]]; then
    echo -e "${RED}این اسکریپت باید با دسترسی root اجرا شود (sudo).${NC}"
    exit 1
fi

# --- پارامترها ---
FORCE=false
INSTANCE_SLUG=""
while [[ $# -gt 0 ]]; do
    case "$1" in
        --force|-f) FORCE=true ;;
        --slug=*) INSTANCE_SLUG="${1#*=}" ;;
        --slug|-s) INSTANCE_SLUG="$2"; shift ;;
        --all) UNINSTALL_ALL=true ;;
        *) echo "پارامتر ناشناخته: $1"; exit 1 ;;
    esac
    shift
done

echo
echo -e "${CYAN}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║  ${BOLD}حذف نمونه VPNMarket${NC}                                      ${CYAN}║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════════════════════╝${NC}"
echo

# --- فهرست نمونه‌های نصب‌شده ---
find_instances() {
    local instances=()
    for dir in /var/www/vpnmarket-*/; do
        if [ -f "${dir}.env" ]; then
            instances+=("$(basename "$dir")")
        fi
    done
    # بررسی پوشه قدیمی
    if [ -f "/var/www/vpnmarket/.env" ]; then
        instances+=("vpnmarket")
    fi
    printf '%s\n' "${instances[@]}"
}

# --- اگر slug مشخص نشده، به‌صورت تعاملی بپرس ---
if [ -z "$INSTANCE_SLUG" ]; then
    echo -e "${CYAN}نمونه‌های نصب‌شده:${NC}\n"

    instances=()
    i=1
    while IFS= read -r slug; do
        local_path="/var/www/$slug"
        local domain=$(grep -E '^APP_URL=' "${local_path}/.env" 2>/dev/null | sed 's|^APP_URL=||;s|https\?://||;s|/.*||' | tr -d ' \t\r\n' || echo "نامشخص")
        printf "  ${GREEN}%d${NC}) %-25s 🌐 %s\n" "$i" "$slug" "$domain"
        instances+=("$slug")
        i=$((i + 1))
    done < <(find_instances)

    if [ ${#instances[@]} -eq 0 ]; then
        echo -e "${YELLOW}هیچ نمونه‌ای نصب نشده است.${NC}"
        exit 0
    fi

    echo
    echo -e "  ${RED}0) حذف تمام نمونه‌ها${NC}"
    echo
    read -p "شماره نمونه‌ای که می‌خواهید حذف کنید: " CHOICE

    if [ "$CHOICE" = "0" ]; then
        UNINSTALL_ALL=true
    elif [[ "$CHOICE" =~ ^[0-9]+$ ]] && [ "$CHOICE" -ge 1 ] && [ "$CHOICE" -le ${#instances[@]} ]; then
        INSTANCE_SLUG="${instances[$((CHOICE - 1))]}"
    else
        echo -e "${RED}انتخاب نامعتبر.${NC}"
        exit 1
    fi
fi

# --- حذف تمام نمونه‌ها ---
if [ "${UNINSTALL_ALL:-false}" = true ]; then
    echo -e "${RED}⚠️  حذف تمام نمونه‌های VPNMarket!${NC}"
    if [ "$FORCE" != true ]; then
        read -p "آیا مطمئن هستید؟ (y/n): " CONFIRM
        [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]] && echo -e "${YELLOW}لغو شد.${NC}" && exit 0
    fi

    while IFS= read -r slug; do
        echo -e "\n${YELLOW}--- حذف نمونه '${slug}' ---${NC}"
        bash "$0" --slug="$slug" --force
    done < <(find_instances)

    exit 0
fi

# --- حذف یک نمونه ---
PROJECT_PATH="/var/www/${INSTANCE_SLUG}"

if [ ! -d "$PROJECT_PATH" ]; then
    echo -e "${RED}پوشه '${PROJECT_PATH}' وجود ندارد.${NC}"
    exit 1
fi

echo -e "${RED}⚠️  هشدار: حذف نمونه '${INSTANCE_SLUG}' غیرقابل بازگشت است.${NC}"
echo -e "مسیر: ${YELLOW}${PROJECT_PATH}${NC}\n"

# --- خواندن اطلاعات از .env ---
DB_NAME=""
DB_USER=""
DOMAIN=""
if [ -f "$PROJECT_PATH/.env" ]; then
    DB_NAME=$(grep -E '^DB_DATABASE=' "$PROJECT_PATH/.env" 2>/dev/null | cut -d'=' -f2 | tr -d ' \t\r\n' || echo "")
    DB_USER=$(grep -E '^DB_USERNAME=' "$PROJECT_PATH/.env" 2>/dev/null | cut -d'=' -f2 | tr -d ' \t\r\n' || echo "")
    DOMAIN=$(grep -E '^APP_URL=' "$PROJECT_PATH/.env" 2>/dev/null | sed 's|^APP_URL=||;s|https\?://||;s|/.*||' | tr -d ' \t\r\n' || echo "")
fi

[ -n "$DB_NAME" ]  && echo -e "دیتابیس:     ${YELLOW}${DB_NAME}${NC}"
[ -n "$DB_USER" ]  && echo -e "کاربر دیتابیس: ${YELLOW}${DB_USER}${NC}"
[ -n "$DOMAIN" ]   && echo -e "دامنه:        ${YELLOW}${DOMAIN}${NC}"
echo

# --- تأیید ---
if [ "$FORCE" != true ]; then
    read -p "آیا از حذف کامل مطمئن هستید؟ (y/n): " CONFIRM
    if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then
        echo -e "${YELLOW}عملیات لغو شد.${NC}"
        exit 0
    fi
fi

echo
echo -e "${YELLOW}در حال حذف نمونه '${INSTANCE_SLUG}'...${NC}\n"

# ═══ مرحله ۱: توقف سرویس‌ها ═══
echo -e "${YELLOW}[1/8] توقف سرویس‌ها...${NC}"
if command -v supervisorctl &>/dev/null; then
    supervisorctl stop "${INSTANCE_SLUG}-worker:*" 2>/dev/null || true
fi
echo -e "  ${GREEN}✓ سرویس‌ها متوقف شدند.${NC}"

# ═══ مرحله ۲: حذف کانفیگ Nginx ═══
echo -e "${YELLOW}[2/8] حذف کانفیگ Nginx...${NC}"
rm -f "/etc/nginx/sites-available/${INSTANCE_SLUG}"  2>/dev/null || true
rm -f "/etc/nginx/sites-enabled/${INSTANCE_SLUG}"    2>/dev/null || true
rm -f "/etc/nginx/conf.d/${INSTANCE_SLUG}.conf"       2>/dev/null || true
echo -e "  ${GREEN}✓ کانفیگ Nginx حذف شد.${NC}"

# ═══ مرحله ۳: حذف کانفیگ Supervisor ═══
echo -e "${YELLOW}[3/8] حذف کانفیگ Supervisor...${NC}"
rm -f "/etc/supervisor/conf.d/${INSTANCE_SLUG}-worker.conf" 2>/dev/null || true
if command -v supervisorctl &>/dev/null; then
    supervisorctl reread  2>/dev/null || true
    supervisorctl update  2>/dev/null || true
fi
echo -e "  ${GREEN}✓ کانفیگ Supervisor حذف شد.${NC}"

# ═══ مرحله ۴: حذف cron job ═══
echo -e "${YELLOW}[4/8] حذف cron job...${NC}"
rm -f "/etc/cron.d/${INSTANCE_SLUG}" 2>/dev/null || true
echo -e "  ${GREEN}✓ cron job حذف شد.${NC}"

# ═══ مرحله ۵: حذف گواهی SSL ═══
echo -e "${YELLOW}[5/8] حذف گواهی SSL...${NC}"
if [ -n "$DOMAIN" ] && command -v certbot &>/dev/null; then
    certbot delete --cert-name "$DOMAIN" --non-interactive 2>/dev/null || true
    echo -e "  ${GREEN}✓ گواهی SSL حذف شد.${NC}"
else
    echo -e "  ${YELLOW}⊘ گواهی SSL یافت نشد.${NC}"
fi

# ═══ مرحله ۶: حذف دیتابیس ═══
echo -e "${YELLOW}[6/8] حذف دیتابیس...${NC}"
if [ -n "$DB_NAME" ]; then
    mysql -e "DROP DATABASE IF EXISTS \`$DB_NAME\`;" 2>/dev/null || true
    echo -e "  ${GREEN}✓ دیتابیس '${DB_NAME}' حذف شد.${NC}"
fi
if [ -n "$DB_USER" ]; then
    mysql -e "DROP USER IF EXISTS '$DB_USER'@'localhost';" 2>/dev/null || true
    mysql -e "DROP USER IF EXISTS '$DB_USER'@'%';"         2>/dev/null || true
    mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true
    echo -e "  ${GREEN}✓ کاربر '${DB_USER}' حذف شد.${NC}"
fi

# ═══ مرحله ۷: حذف فایل‌های پروژه ═══
echo -e "${YELLOW}[7/8] حذف پوشه پروژه...${NC}"
if [ -d "$PROJECT_PATH" ]; then
    rm -rf "$PROJECT_PATH"
    echo -e "  ${GREEN}✓ پوشه ${PROJECT_PATH} حذف شد.${NC}"
else
    echo -e "  ${YELLOW}⊘ پوشه قبلاً حذف شده است.${NC}"
fi

# ═══ مرحله ۸: ری‌استارت Nginx ═══
echo -e "${YELLOW}[8/8] ری‌استارت Nginx...${NC}"
systemctl reload nginx 2>/dev/null || systemctl restart nginx 2>/dev/null || true
echo -e "  ${GREEN}✓ Nginx ری‌استارت شد.${NC}"

# ═══ پایان ═══
echo
echo -e "${GREEN}=====================================================${NC}"
echo -e "${GREEN}✅ نمونه '${INSTANCE_SLUG}' با موفقیت حذف شد.${NC}"
echo -e "${GREEN}=====================================================${NC}"
echo

# نمایش فهرست نمونه‌های باقی‌مانده
remaining=$(find_instances | wc -l)
if [ "$remaining" -gt 0 ]; then
    echo -e "${CYAN}نمونه‌های باقی‌مانده:${NC}"
    while IFS= read -r slug; do
        echo -e "  ${GREEN}●${NC} $slug"
    done < <(find_instances)
else
    echo -e "${YELLOW}هیچ نمونه‌ای باقی نمانده است.${NC}"
fi
echo
