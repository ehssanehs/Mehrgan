#!/bin/bash

# ==================================================================================
# === اسکریپت نصب چندنمونه‌ای برای پروژه VPNMarket روی Ubuntu 22.04             ===
# === پشتیبانی از نصب همزمان چندین نمونه با پوشه‌ها، دیتابیس و دامنه‌های مستقل   ===
# === هر نمونه: وب‌سایت + ربات تلگرام + ورکر صف مستقل — اجرای موازی            ===
# === https://github.com/ehssanehs/vpn-market                                    ===
# ==================================================================================

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m'

GITHUB_REPO="https://github.com/ehssanehs/vpn-market.git"
PHP_VERSION="8.3"

# ══════════════════════════════════════════════════════════════
#  توابع کمکی
# ══════════════════════════════════════════════════════════════

# تبدیل نام پوشه به شناسه امن (حروف کوچک، اعداد، خط تیره)
slugify() {
    echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/-/g' | sed 's/-\+/-/g' | sed 's/^-//;s/-$//'
}

# بررسی وجود یک نمونه قبلی
instance_exists() {
    local slug="$1"
    [ -d "/var/www/$slug" ] && return 0
    return 1
}

# فهرست نمونه‌های نصب‌شده
list_instances() {
    echo -e "\n${CYAN}━━━ نمونه‌های نصب‌شده VPNMarket ━━━${NC}\n"
    local found=0
    for envfile in /var/www/vpnmarket-*/; do
        if [ -f "${envfile}.env" ]; then
            local slug=$(basename "$envfile")
            local domain=$(grep -E '^APP_URL=' "${envfile}.env" 2>/dev/null | sed 's|^APP_URL=||;s|https\?://||;s|/.*||' | tr -d ' \t\r\n' || echo "نامشخص")
            local dbname=$(grep -E '^DB_DATABASE=' "${envfile}.env" 2>/dev/null | cut -d'=' -f2 | tr -d ' \t\r\n' || echo "نامشخص")
            printf "  ${GREEN}●${NC}  %-25s  🌐 %-30s  🗃 %s\n" "$slug" "$domain" "$dbname"
            found=1
        fi
    done
    # همچنین بررسی پوشه قدیمی (vpnmarket بدون شماره)
    if [ -f "/var/www/vpnmarket/.env" ]; then
        local domain=$(grep -E '^APP_URL=' "/var/www/vpnmarket/.env" 2>/dev/null | sed 's|^APP_URL=||;s|https\?://||;s|/.*||' | tr -d ' \t\r\n' || echo "نامشخص")
        local dbname=$(grep -E '^DB_DATABASE=' "/var/www/vpnmarket/.env" 2>/dev/null | cut -d'=' -f2 | tr -d ' \t\r\n' || echo "نامشخص")
        printf "  ${GREEN}●${NC}  %-25s  🌐 %-30s  🗃 %s\n" "vpnmarket" "$domain" "$dbname"
        found=1
    fi
    if [ $found -eq 0 ]; then
        echo -e "  ${YELLOW}(هیچ نمونه‌ای نصب نشده است)${NC}"
    fi
    echo
}

# ══════════════════════════════════════════════════════════════
#  نصب پیش‌نیازهای سرور (فقط یک‌بار)
# ══════════════════════════════════════════════════════════════

install_prerequisites() {
    echo -e "${YELLOW}📦 بررسی و نصب پیش‌نیازهای سرور ...${NC}"

    # حذف PHP های قدیمی
    sudo apt-get remove -y php* 2>/dev/null || true
    sudo apt autoremove -y 2>/dev/null || true

    export DEBIAN_FRONTEND=noninteractive
    sudo apt-get update -y
    sudo apt-get install -y git curl unzip software-properties-common gpg nginx mysql-server redis-server supervisor ufw certbot python3-certbot-nginx

    # Node.js LTS
    if ! command -v node &>/dev/null; then
        echo -e "${YELLOW}📦 نصب Node.js ...${NC}"
        curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
        sudo apt-get install -y nodejs build-essential
    else
        echo -e "${GREEN}✔ Node.js قبلاً نصب است: $(node -v)${NC}"
    fi

    # PHP 8.3
    if ! command -v php${PHP_VERSION} &>/dev/null; then
        echo -e "${YELLOW}☕ نصب PHP ${PHP_VERSION} ...${NC}"
        sudo add-apt-repository -y ppa:ondrej/php
        sudo apt-get update -y
        sudo apt-get install -y \
            php${PHP_VERSION} php${PHP_VERSION}-fpm php${PHP_VERSION}-cli \
            php${PHP_VERSION}-mysql php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml \
            php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-bcmath \
            php${PHP_VERSION}-intl php${PHP_VERSION}-gd php${PHP_VERSION}-dom \
            php${PHP_VERSION}-redis
    else
        echo -e "${GREEN}✔ PHP ${PHP_VERSION} قبلاً نصب است${NC}"
    fi

    # تنظیم محدودیت آپلود PHP
    PHP_INI_PATH="/etc/php/${PHP_VERSION}/fpm/php.ini"
    sudo sed -i 's/upload_max_filesize = .*/upload_max_filesize = 10M/' "$PHP_INI_PATH"
    sudo sed -i 's/post_max_size = .*/post_max_size = 12M/' "$PHP_INI_PATH"

    # Composer
    if ! command -v composer &>/dev/null; then
        echo -e "${YELLOW}📦 نصب Composer ...${NC}"
        php${PHP_VERSION} -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        php${PHP_VERSION} composer-setup.php --install-dir=/usr/local/bin --filename=composer
        rm -f composer-setup.php
    else
        echo -e "${GREEN}✔ Composer قبلاً نصب است${NC}"
    fi

    # فعال‌سازی سرویس‌ها
    sudo systemctl enable --now php${PHP_VERSION}-fpm nginx mysql redis-server supervisor

    # فایروال
    sudo ufw allow 'OpenSSH' 2>/dev/null || true
    sudo ufw allow 'Nginx Full' 2>/dev/null || true
    echo "y" | sudo ufw enable 2>/dev/null || true
    sudo ufw disable 2>/dev/null || true

    echo -e "${GREEN}✔ پیش‌نیازها آماده‌اند.${NC}\n"
}

# ══════════════════════════════════════════════════════════════
#  نصب یک نمونه از VPNMarket
# ══════════════════════════════════════════════════════════════

install_instance() {
    local INSTANCE_NUM="$1"

    echo
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║  نصب نمونه ${BOLD}#${INSTANCE_NUM}${NC}${CYAN} از VPNMarket                              ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════╝${NC}"
    echo

    # --- نام پوشه پروژه ---
    local DEFAULT_SLUG="vpnmarket-${INSTANCE_NUM}"
    read -p "📂 نام پوشه پروژه [پیش‌فرض: ${DEFAULT_SLUG}]: " FOLDER_NAME
    FOLDER_NAME=$(echo "$FOLDER_NAME" | xargs)  # trim whitespace
    if [ -z "$FOLDER_NAME" ]; then
        FOLDER_NAME="$DEFAULT_SLUG"
    fi
    INSTANCE_SLUG=$(slugify "$FOLDER_NAME")

    if instance_exists "$INSTANCE_SLUG"; then
        echo -e "${RED}✖ نمونه‌ای با نام '${INSTANCE_SLUG}' قبلاً در /var/www/${INSTANCE_SLUG} وجود دارد.${NC}"
        read -p "آیا می‌خواهید دوباره نصب کنید؟ (حذف و از نو) (y/n): " REINSTALL
        if [[ "$REINSTALL" =~ ^[Yy]$ ]]; then
            echo -e "${YELLOW}🗑 حذف نمونه قبلی ...${NC}"
            bash "$(dirname "$0")/uninstall.sh" --slug="$INSTANCE_SLUG" --force 2>/dev/null || true
        else
            echo -e "${YELLOW}رد شد.${NC}"
            return 1
        fi
    fi

    PROJECT_PATH="/var/www/${INSTANCE_SLUG}"

    # --- دامنه ---
    read -p "🌐 دامنه (مثل vpn1.example.com): " DOMAIN
    DOMAIN=$(echo "$DOMAIN" | sed 's|http[s]*://||g' | sed 's|/.*||g')
    if [ -z "$DOMAIN" ]; then
        echo -e "${RED}دامنه نمی‌تواند خالی باشد.${NC}"
        return 1
    fi

    # --- دیتابیس ---
    local DEFAULT_DB="${INSTANCE_SLUG//-/_}_db"
    read -p "🗃 نام دیتابیس [پیش‌فرض: ${DEFAULT_DB}]: " DB_NAME
    DB_NAME=$(echo "$DB_NAME" | xargs)
    [ -z "$DB_NAME" ] && DB_NAME="$DEFAULT_DB"

    local DEFAULT_DB_USER="${INSTANCE_SLUG//-/_}_user"
    read -p "👤 نام کاربری دیتابیس [پیش‌فرض: ${DEFAULT_DB_USER}]: " DB_USER
    DB_USER=$(echo "$DB_USER" | xargs)
    [ -z "$DB_USER" ] && DB_USER="$DEFAULT_DB_USER"

    while true; do
        read -s -p "🔑 رمز عبور دیتابیس: " DB_PASS
        echo
        [ ! -z "$DB_PASS" ] && break
        echo -e "${RED}رمز عبور نباید خالی باشد.${NC}"
    done

    # --- حساب ادمین (برای ورود به پنل مدیریت) ---
    echo -e "${CYAN}━━━ اطلاعات کاربر ادمین (ورود به پنل مدیریت) ━━━${NC}"
    read -p "✉️ ایمیل ادمین [پیش‌فرض: admin@example.com]: " ADMIN_EMAIL
    ADMIN_EMAIL=$(echo "$ADMIN_EMAIL" | xargs)
    [ -z "$ADMIN_EMAIL" ] && ADMIN_EMAIL="admin@example.com"

    while true; do
        read -s -p "🔑 رمز عبور ادمین [پیش‌فرض: password (خالی = پیش‌فرض)]: " ADMIN_PASS
        echo
        if [ -z "$ADMIN_PASS" ]; then
            ADMIN_PASS="password"
            break
        fi
        read -s -p "🔑 تکرار رمز عبور ادمین: " ADMIN_PASS_CONFIRM
        echo
        if [ "$ADMIN_PASS" = "$ADMIN_PASS_CONFIRM" ]; then
            break
        fi
        echo -e "${RED}رمز عبور و تکرار آن یکسان نیستند؛ دوباره وارد کنید.${NC}"
    done

    # --- ایمیل SSL (برای certbot) ---
    read -p "✉️ ایمیل برای گواهینامه SSL [پیش‌فرض: همان ایمیل ادمین]: " SSL_EMAIL
    SSL_EMAIL=$(echo "$SSL_EMAIL" | xargs)
    [ -z "$SSL_EMAIL" ] && SSL_EMAIL="$ADMIN_EMAIL"
    echo

    # --- شروع نصب ---
    echo
    echo -e "${CYAN}━━━ شروع نصب نمونه '${INSTANCE_SLUG}' ━━━${NC}"
    echo -e "  پوشه:   ${PROJECT_PATH}"
    echo -e "  دامنه:  ${DOMAIN}"
    echo -e "  دیتابیس: ${DB_NAME}"
    echo -e "  ادمین:  ${ADMIN_EMAIL}"
    echo

    # --- دانلود پروژه ---
    echo -e "${YELLOW}⬇️ دانلود سورس ...${NC}"
    sudo rm -rf "$PROJECT_PATH"
    sudo git clone $GITHUB_REPO "$PROJECT_PATH"
    sudo chown -R www-data:www-data "$PROJECT_PATH"
    cd "$PROJECT_PATH"

    # --- ساخت دیتابیس ---
    echo -e "${YELLOW}🗃 ساخت دیتابیس ...${NC}"
    sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`;"
    sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';" 2>/dev/null || \
        sudo mysql -e "ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
    sudo mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';"
    sudo mysql -e "FLUSH PRIVILEGES;"

    # --- تنظیم ENV ---
    echo -e "${YELLOW}⚙️ تنظیم فایل .env ...${NC}"
    sudo -u www-data cp .env.example .env
    sudo sed -i "s|APP_NAME=.*|APP_NAME=VPNMarket-${INSTANCE_NUM}|" .env
    sudo sed -i "s|DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" .env
    sudo sed -i "s|DB_USERNAME=.*|DB_USERNAME=$DB_USER|" .env
    sudo sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env
    sudo sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env
    sudo sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
    sudo sed -i "s|QUEUE_CONNECTION=.*|QUEUE_CONNECTION=redis|" .env
    # پیشوند یکتای Redis برای جلوگیری از تداخل بین نمونه‌ها
    if grep -q "^CACHE_PREFIX=" .env; then
        sudo sed -i "s|CACHE_PREFIX=.*|CACHE_PREFIX=${INSTANCE_SLUG}_|I" .env
    else
        echo "CACHE_PREFIX=${INSTANCE_SLUG}_" | sudo tee -a .env > /dev/null
    fi

    # --- اعتبار کاربر ادمین (خوانده می‌شود توسط DatabaseSeeder هنگام seed) ---
    if grep -q "^ADMIN_EMAIL=" .env; then
        sudo sed -i "s|^ADMIN_EMAIL=.*|ADMIN_EMAIL=$ADMIN_EMAIL|" .env
    else
        echo "ADMIN_EMAIL=$ADMIN_EMAIL" | sudo tee -a .env > /dev/null
    fi
    if grep -q "^ADMIN_PASSWORD=" .env; then
        sudo sed -i "s|^ADMIN_PASSWORD=.*|ADMIN_PASSWORD=$ADMIN_PASS|" .env
    else
        echo "ADMIN_PASSWORD=$ADMIN_PASS" | sudo tee -a .env > /dev/null
    fi

    # --- نصب وابستگی‌ها ---
    echo -e "${YELLOW}🧰 نصب پکیج‌های Composer ...${NC}"
    sudo -u www-data composer install --no-dev --optimize-autoloader

    echo -e "${YELLOW}📦 نصب پکیج‌های Node.js ...${NC}"
    sudo -u www-data rm -rf node_modules package-lock.json
    sudo -u www-data npm cache clean --force 2>/dev/null || true

    NPM_CACHE_DIR="/var/www/.npm"
    sudo mkdir -p "$NPM_CACHE_DIR"
    sudo chown -R www-data:www-data "$NPM_CACHE_DIR"
    sudo chown -R www-data:www-data "$PROJECT_PATH"

    sudo -u www-data npm install --cache "$NPM_CACHE_DIR" --legacy-peer-deps
    sudo -u www-data npm run build

    sudo -u www-data php artisan key:generate
    sudo -u www-data php artisan migrate --seed --force
    sudo -u www-data php artisan storage:link

    # --- پیکربندی Nginx ---
    echo -e "${YELLOW}🌐 پیکربندی Nginx ...${NC}"
    PHP_FPM_SOCK_PATH="/run/php/php${PHP_VERSION}-fpm.sock"

    sudo tee /etc/nginx/sites-available/${INSTANCE_SLUG} >/dev/null <<EOF
server {
    listen 80;
    server_name $DOMAIN;
    root $PROJECT_PATH/public;

    client_max_body_size 10M;

    index index.php;
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location ~ \.php\$ {
        fastcgi_pass unix:$PHP_FPM_SOCK_PATH;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}
EOF

    sudo ln -sf /etc/nginx/sites-available/${INSTANCE_SLUG} /etc/nginx/sites-enabled/
    sudo nginx -t && sudo systemctl reload nginx

    # --- Supervisor (ورکرهای صف مستقل) ---
    echo -e "${YELLOW}⚙️ تنظیم Supervisor برای ورکرهای صف ...${NC}"
    sudo tee /etc/supervisor/conf.d/${INSTANCE_SLUG}-worker.conf >/dev/null <<EOF
[program:${INSTANCE_SLUG}-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_PATH/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/${INSTANCE_SLUG}-worker.log
EOF

    sudo supervisorctl reread
    sudo supervisorctl update
    sudo supervisorctl start "${INSTANCE_SLUG}-worker:*" 2>/dev/null || true

    # --- Cron job ---
    echo -e "${YELLOW}⏰ تنظیم cron job ...${NC}"
    sudo tee /etc/cron.d/${INSTANCE_SLUG} >/dev/null <<EOF
* * * * * www-data cd $PROJECT_PATH && php artisan schedule:run >> /dev/null 2>&1
EOF

    # --- Cache ---
    sudo -u www-data php artisan config:cache
    sudo -u www-data php artisan route:cache
    sudo -u www-data php artisan view:cache

    # --- SSL ---
    read -p "🔒 فعال‌سازی SSL برای ${DOMAIN}؟ (y/n): " ENABLE_SSL
    if [[ "$ENABLE_SSL" =~ ^[Yy]$ ]]; then
        sudo certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$SSL_EMAIL" 2>/dev/null || \
            echo -e "${YELLOW}⚠️ فعال‌سازی SSL ناموفق بود. بعداً دستی اجرا کنید: certbot --nginx -d $DOMAIN${NC}"
    fi

    # --- نمایش نتیجه ---
    echo
    echo -e "${GREEN}╔══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  ✅ نمونه '${INSTANCE_SLUG}' با موفقیت نصب شد!            ║${NC}"
    echo -e "${GREEN}╠══════════════════════════════════════════════════════════╣${NC}"
    echo -e "${GREEN}║  🌂 وب‌سایت:  https://${DOMAIN}${NC}"
    echo -e "${GREEN}║  🔑 پنل مدیریت: https://${DOMAIN}/admin${NC}"
    echo -e "${GREEN}║  📂 مسیر:  ${PROJECT_PATH}${NC}"
    echo -e "${GREEN}║  🗃 دیتابیس: ${DB_NAME}${NC}"
    echo -e "${GREEN}║  ⚙️ ورکر صف: ${INSTANCE_SLUG}-worker (۲ پروسه)${NC}"
    echo -e "${GREEN}║  ⏰ Cron: /etc/cron.d/${INSTANCE_SLUG}${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════════════╝${NC}"
    echo
    echo -e "${GREEN}🔑 ورود به پنل مدیریت (https://${DOMAIN}/admin):${NC}"
    echo -e "   ایمیل ادمین: ${YELLOW}${ADMIN_EMAIL}${NC}"
    if [ "$ADMIN_PASS" = "password" ]; then
        echo -e "   ${RED}⚠️ از رمز عبور پیش‌فرض «password» استفاده شد!${NC}"
        echo -e "   ${RED}   حتماً پس از اولین ورود، رمز عبور را تغییر دهید.${NC}"
    else
        echo -e "   رمز عبور: ${YELLOW}(رمزی که در زمان نصب وارد کردید)${NC}"
    fi
    echo
}

# ══════════════════════════════════════════════════════════════
#  بدنه اصلی اسکریپت
# ══════════════════════════════════════════════════════════════

echo
echo -e "${CYAN}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║  ${BOLD}نصب چندنمونه‌ای VPNMarket${NC}                                ${CYAN}║${NC}"
echo -e "${CYAN}║  پشتیبانی از نصب همزمان چندین نمونه مستقل              ║${NC}"
echo -e "${CYAN}║  هر نمونه: وب‌سایت + ربات تلگرام + ورکر صف مستقل       ║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════════════════════╝${NC}"
echo

# --- فهرست نمونه‌های موجود ---
list_instances

# --- نصب پیش‌نیازها (فقط یک‌بار) ---
read -p "🔧 آیا پیش‌نیازهای سرور (Nginx, PHP, MySQL, Redis, ...) نصب است؟ (y/n): " PREREQ_INSTALLED
if [[ ! "$PREREQ_INSTALLED" =~ ^[Yy]$ ]]; then
    install_prerequisites
else
    echo -e "${GREEN}✔ رد شدن از نصب پیش‌نیازها.${NC}\n"
fi

# --- حلقه نصب نمونه‌ها ---
INSTANCE_NUM=1
while true; do
    install_instance "$INSTANCE_NUM"

    read -p "➕ آیا می‌خواهید نمونه دیگری نصب کنید؟ (y/n): " INSTALL_MORE
    if [[ ! "$INSTALL_MORE" =~ ^[Yy]$ ]]; then
        break
    fi
    INSTANCE_NUM=$((INSTANCE_NUM + 1))
done

# --- نمایش فهرست نهایی ---
echo
echo -e "${CYAN}━━━ فهرست نهایی نمونه‌های نصب‌شده ━━━${NC}"
list_instances

echo -e "${GREEN}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  ✅ تمام نمونه‌ها با موفقیت نصب شدند!                   ║${NC}"
echo -e "${GREEN}║  همه وب‌سایت‌ها و ربات‌ها به‌صورت موازی در حال اجرا‌اند   ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════╝${NC}"
echo
echo -e "💡 مدیریت نمونه‌ها:"
echo -e "   ${CYAN}برای آپدیت:${NC}     cd /var/www/<نام> && sudo bash update.sh"
echo -e "   ${CYAN}برای حذف:${NC}       sudo bash uninstall.sh --slug=<نام>"
echo -e "   ${CYAN}ورکرهای صف:${NC}    sudo supervisorctl status"
echo -e "   ${CYAN}ری‌استارت ورکر:${NC} sudo supervisorctl restart <نام>-worker:*"
echo
