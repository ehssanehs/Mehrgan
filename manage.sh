#!/bin/bash

# ==============================================================================
# ===      اسکریپت مدیریت نمونه‌های VPNMarket                               ===
# ===  استفاده: bash manage.sh [status|list|restart|stop|start] [slug]        ===
# ==============================================================================

# --- رنگ‌ها ---
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# --- فهرست نمونه‌ها ---
find_instances() {
    for dir in /var/www/vpnmarket-*/; do
        if [ -f "${dir}.env" ]; then
            basename "$dir"
        fi
    done
    if [ -f "/var/www/vpnmarket/.env" ]; then
        echo "vpnmarket"
    fi
}

# --- نمایش فهرست ---
cmd_list() {
    echo -e "\n${CYAN}━━━ نمونه‌های نصب‌شده VPNMarket ━━━${NC}\n"
    local found=0
    while IFS= read -r slug; do
        local_path="/var/www/$slug"
        local domain=$(grep -E '^APP_URL=' "${local_path}/.env" 2>/dev/null | sed 's|^APP_URL=||;s|https\?://||;s|/.*||' | tr -d ' \t\r\n' || echo "نامشخص")
        local dbname=$(grep -E '^DB_DATABASE=' "${local_path}/.env" 2>/dev/null | cut -d'=' -f2 | tr -d ' \t\r\n' || echo "نامشخص")

        # بررسی وضعیت ورکر
        local worker_status="⚠️ نامشخص"
        if command -v supervisorctl &>/dev/null; then
            local sv_status=$(sudo supervisorctl status "${slug}-worker:0" 2>/dev/null | awk '{print $2}' || echo "")
            case "$sv_status" in
                RUNNING) worker_status="${GREEN}● در حال اجرا${NC}" ;;
                STOPPED) worker_status="${RED}■ متوقف${NC}" ;;
                STARTING) worker_status="${YELLOW}◐ در حال شروع${NC}" ;;
                *) worker_status="${YELLOW}⚪ $sv_status${NC}" ;;
            esac
        fi

        printf "  ${GREEN}●${NC}  %-22s  🌐 %-28s  🗃 %-18s  ⚙️ " "$slug" "$domain" "$dbname"
        echo -e "$worker_status"
        found=1
    done < <(find_instances)

    if [ $found -eq 0 ]; then
        echo -e "  ${YELLOW}(هیچ نمونه‌ای نصب نشده است)${NC}"
    fi
    echo
}

# --- وضعیت تفصیلی ---
cmd_status() {
    local slug="$1"
    if [ -z "$slug" ]; then
        cmd_list
        echo -e "\n${CYAN}━━━ وضعیت ورکرهای Supervisor ━━━${NC}"
        sudo supervisorctl status 2>/dev/null || echo -e "${YELLOW}Supervisor در دسترس نیست.${NC}"
        return
    fi

    local path="/var/www/$slug"
    if [ ! -d "$path" ]; then
        echo -e "${RED}نمونه '${slug}' یافت نشد.${NC}"
        return 1
    fi

    echo -e "\n${CYAN}━━━ وضعیت نمونه '${slug}' ━━━${NC}"
    echo -e "  مسیر:  ${path}"
    echo -e "  دامنه: $(grep -E '^APP_URL=' "${path}/.env" 2>/dev/null | sed 's|^APP_URL=||' || echo 'نامشخص')"
    echo -e "  دیتابیس: $(grep -E '^DB_DATABASE=' "${path}/.env" 2>/dev/null | cut -d'=' -f2 || echo 'نامشخص')"
    echo

    echo -e "  ${CYAN}ورکرهای صف:${NC}"
    sudo supervisorctl status "${slug}-worker:*" 2>/dev/null || echo -e "  ${YELLOW}ورکری یافت نشد.${NC}"
    echo

    # بررسی آخرین لاگ ورکر
    local log="/var/log/supervisor/${slug}-worker.log"
    if [ -f "$log" ]; then
        echo -e "  ${CYAN}آخرین لاگ ورکر (۵ خط):${NC}"
        tail -5 "$log" 2>/dev/null | sed 's/^/    /'
    fi
    echo
}

# --- ری‌استارت ---
cmd_restart() {
    local slug="$1"
    if [ -z "$slug" ]; then
        echo -e "${RED}نام نمونه (slug) را مشخص کنید.${NC}"
        echo -e "مثال: bash manage.sh restart vpnmarket-1"
        return 1
    fi

    echo -e "${YELLOW}ری‌استارت نمونه '${slug}'...${NC}"

    # ری‌استارت ورکرها
    sudo supervisorctl restart "${slug}-worker:*" 2>/dev/null && \
        echo -e "${GREEN}✔ ورکرهای صف ری‌استارت شدند.${NC}" || \
        echo -e "${RED}✖ ری‌استارت ورکرها ناموفق بود.${NC}"

    # پاکسازی کش
    local path="/var/www/$slug"
    if [ -f "${path}/artisan" ]; then
        cd "$path"
        sudo -u www-data php artisan optimize:clear 2>/dev/null && \
            echo -e "${GREEN}✔ کش‌ها پاک شدند.${NC}" || true
    fi
}

# --- توقف ---
cmd_stop() {
    local slug="$1"
    if [ -z "$slug" ]; then
        echo -e "${RED}نام نمونه (slug) را مشخص کنید.${NC}"
        return 1
    fi
    sudo supervisorctl stop "${slug}-worker:*" 2>/dev/null && \
        echo -e "${GREEN}✔ ورکرهای '${slug}' متوقف شدند.${NC}" || \
        echo -e "${RED}✖ توقف ناموفق بود.${NC}"
}

# --- شروع ---
cmd_start() {
    local slug="$1"
    if [ -z "$slug" ]; then
        echo -e "${RED}نام نمونه (slug) را مشخص کنید.${NC}"
        return 1
    fi
    sudo supervisorctl start "${slug}-worker:*" 2>/dev/null && \
        echo -e "${GREEN}✔ ورکرهای '${slug}' شروع شدند.${NC}" || \
        echo -e "${RED}✖ شروع ناموفق بود.${NC}"
}

# ══════════════════════════════════════════════════════════════
#  بدنه اصلی
# ══════════════════════════════════════════════════════════════

ACTION="${1:-list}"
SLUG="$2"

case "$ACTION" in
    list|ls)     cmd_list ;;
    status|st)   cmd_status "$SLUG" ;;
    restart|r)   cmd_restart "$SLUG" ;;
    stop)        cmd_stop "$SLUG" ;;
    start)       cmd_start "$SLUG" ;;
    *)
        echo -e "${CYAN}استفاده: bash manage.sh <command> [slug]${NC}\n"
        echo -e "  ${GREEN}list${NC}     فهرست نمونه‌های نصب‌شده"
        echo -e "  ${GREEN}status${NC}   وضعیت تفصیلی (با slug: وضعیت یک نمونه)"
        echo -e "  ${GREEN}restart${NC}  ری‌استارت ورکرهای یک نمونه"
        echo -e "  ${GREEN}stop${NC}     توقف ورکرهای یک نمونه"
        echo -e "  ${GREEN}start${NC}    شروع ورکرهای یک نمونه"
        echo
        echo -e "مثال:"
        echo -e "  bash manage.sh list"
        echo -e "  bash manage.sh status vpnmarket-1"
        echo -e "  bash manage.sh restart vpnmarket-2"
        ;;
esac
