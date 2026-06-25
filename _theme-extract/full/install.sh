#!/bin/bash
# ============================================
#   HYPER GAME PANEL - FULL INSTALLER
#   One command to install everything
# ============================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PANEL_DIR="/var/www/pterodactyl"
DB_NAME="panel"
DB_USER="pterodactyl"
DB_PASS="$(openssl rand -base64 32)"
APP_KEY="$(php -r 'echo "base64:" . base64_encode(random_bytes(32));' 2>/dev/null || echo "base64:$(head -c 32 /dev/urandom | base64)")"

echo -e "${CYAN}"
echo "============================================"
echo "   HYPER GAME PANEL - FULL INSTALLER"
echo "   Complete Panel + Theme Installation"
echo "============================================"
echo -e "${NC}"

# Check root
if [[ "$EUID" -ne 0 ]]; then
    echo -e "${RED}[ERROR] Run as root: sudo bash install.sh${NC}"
    exit 1
fi

# Check for Hyper.zip
if [[ ! -f "$SCRIPT_DIR/Hyper.zip" ]]; then
    echo -e "${RED}[ERROR] Hyper.zip not found in $SCRIPT_DIR${NC}"
    echo "Place Hyper.zip next to this install.sh"
    exit 1
fi

# Get panel URL
read -p "Enter your panel URL (e.g., https://panel.example.com): " PANEL_URL
if [[ -z "$PANEL_URL" ]]; then
    echo -e "${RED}[ERROR] Panel URL is required${NC}"
    exit 1
fi

# Get admin email
read -p "Enter admin email: " ADMIN_EMAIL
if [[ -z "$ADMIN_EMAIL" ]]; then
    echo -e "${RED}[ERROR] Admin email is required${NC}"
    exit 1
fi

# Get admin password
read -s -p "Enter admin password: " ADMIN_PASS
echo ""
if [[ -z "$ADMIN_PASS" ]]; then
    echo -e "${RED}[ERROR] Admin password is required${NC}"
    exit 1
fi

# Get admin name
read -p "Enter admin name [Admin]: " ADMIN_NAME
ADMIN_NAME=${ADMIN_NAME:-Admin}

echo ""
echo -e "${CYAN}[1/18]${NC} Updating system..."

export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y

echo -e "${CYAN}[2/18]${NC} Installing dependencies..."

apt-get install -y software-properties-common curl apt-transport-https ca-certificates gnupg unzip zip git redis-server mariadb-server

# PHP 8.4
echo -e "${CYAN}[3/18]${NC} Installing PHP 8.4..."

if ! command -v php8.4 >/dev/null 2>&1; then
    rm -f /etc/apt/trusted.gpg.d/php.gpg 2>/dev/null || true
    curl -sSLo /tmp/php.gpg https://packages.sury.org/php/apt.gpg 2>/dev/null || wget -qO /tmp/php.gpg https://packages.sury.org/php/apt.gpg 2>/dev/null || true
    if [[ -f /tmp/php.gpg ]]; then
        install -o root -g root -m 644 /tmp/php.gpg /usr/share/keyrings/deb.sury.org-php.gpg 2>/dev/null || true
        rm -f /tmp/php.gpg
    fi

    if ! grep -rq "ondrej/php\|packages.sury.org/php" /etc/apt/sources.list /etc/apt/sources.list.d/ 2>/dev/null; then
        echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -cs) main" \
            > /etc/apt/sources.list.d/php.list
    fi

    apt-get update -y
    apt-get install -y php8.4 php8.4-cli php8.4-fpm \
        php8.4-common php8.4-mbstring php8.4-xml php8.4-curl \
        php8.4-zip php8.4-gd php8.4-bcmath php8.4-mysql \
        php8.4-tokenizer php8.4-xmlwriter php8.4-fileinfo \
        php8.4-opcache php8.4-redis
fi

# Composer
echo -e "${CYAN}[4/18]${NC} Installing Composer..."

if ! command -v composer >/dev/null 2>&1; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# Nginx
echo -e "${CYAN}[5/18]${NC} Installing Nginx..."

if ! command -v nginx >/dev/null 2>&1; then
    apt-get install -y nginx
fi

# Database
echo -e "${CYAN}[6/18]${NC} Configuring database..."

systemctl start mariadb 2>/dev/null || service mariadb start 2>/dev/null || true
systemctl enable mariadb 2>/dev/null || true

mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME};
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

echo -e "${GREEN}  Database configured!${NC}"

# Redis
echo -e "${CYAN}[7/18]${NC} Configuring Redis..."

systemctl start redis-server 2>/dev/null || service redis-server start 2>/dev/null || true
systemctl enable redis-server 2>/dev/null || true

# Extract panel
echo -e "${CYAN}[8/18]${NC} Extracting Hyper panel..."

mkdir -p "$PANEL_DIR"
cd "$PANEL_DIR"

unzip -oq "$SCRIPT_DIR/Hyper.zip" -d "$PANEL_DIR"

# If files are inside a subdirectory, move them up
if [[ -d "$PANEL_DIR/pterodactyl" ]]; then
    mv "$PANEL_DIR/pterodactyl/"* "$PANEL_DIR/" 2>/dev/null || true
    mv "$PANEL_DIR/pterodactyl/".* "$PANEL_DIR/" 2>/dev/null || true
    rmdir "$PANEL_DIR/pterodactyl" 2>/dev/null || true
fi

echo -e "${GREEN}  Panel extracted!${NC}"

# Configure .env
echo -e "${CYAN}[9/18]${NC} Configuring environment..."

cp .env.example .env 2>/dev/null || true

if command -v php >/dev/null 2>&1; then
    php artisan key:generate --force 2>/dev/null || true
fi

sed -i "s|APP_URL=.*|APP_URL=\"${PANEL_URL}\"|g" .env
sed -i "s|APP_ENV=.*|APP_ENV=production|g" .env
sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|g" .env
sed -i "s|DB_HOST=.*|DB_HOST=127.0.0.1|g" .env
sed -i "s|DB_PORT=.*|DB_PORT=3306|g" .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|g" .env
sed -i "s|DB_USERNAME=.*|DB_USERNAME=${DB_USER}|g" .env
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=\"${DB_PASS}\"|g" .env
sed -i "s|CACHE_DRIVER=.*|CACHE_DRIVER=redis|g" .env
sed -i "s|SESSION_DRIVER=.*|SESSION_DRIVER=redis|g" .env
sed -i "s|QUEUE_CONNECTION=.*|QUEUE_CONNECTION=redis|g" .env
sed -i "s|REDIS_HOST=.*|REDIS_HOST=localhost|g" .env

echo -e "${GREEN}  Environment configured!${NC}"

# Install Composer dependencies
echo -e "${CYAN}[10/18]${NC} Installing Composer dependencies..."

export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction

# =============================================
# INSTALL IONCUBE LOADER
# =============================================
echo -e "${CYAN}[11/18]${NC} Installing IonCube Loader..."

PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "8.4")
PHP_EXT_DIR=$(php -r 'echo PHP_EXTENSION_DIR;' 2>/dev/null || echo "/usr/lib/php/20240924")

cd /tmp

# Detect architecture
ARCH=$(uname -m)
if [[ "$ARCH" == "x86_64" ]]; then
    IONCUBE_ARCH="x86-64"
elif [[ "$ARCH" == "aarch64" || "$ARCH" == "arm64" ]]; then
    IONCUBE_ARCH="aarch64"
else
    IONCUBE_ARCH="x86-64"
fi

# Download IonCube loader
IONCUBE_URL="https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_${IONCUBE_ARCH}.tar.gz"

if curl -fsSL --retry 3 --retry-delay 2 -o ioncube.tar.gz "$IONCUBE_URL" 2>/dev/null || \
   wget -q -O ioncube.tar.gz "$IONCUBE_URL" 2>/dev/null; then
    tar -xzf ioncube.tar.gz

    # Find the correct loader file
    IONCUBE_FILE=$(ls ioncube/ioncube_loader_lin_${PHP_VERSION}*.so 2>/dev/null | head -1 || true)

    if [[ -n "$IONCUBE_FILE" ]]; then
        cp "$IONCUBE_FILE" "$PHP_EXT_DIR/"
        echo "zend_extension=ioncube_loader_lin_${PHP_VERSION}.so" > /etc/php/${PHP_VERSION}/mods-available/ioncube.ini

        # Enable for CLI and FPM
        phpenmod -v "$PHP_VERSION" -s cli ioncube 2>/dev/null || true
        phpenmod -v "$PHP_VERSION" -s fpm ioncube 2>/dev/null || true

        echo -e "${GREEN}  IonCube Loader installed for PHP ${PHP_VERSION}!${NC}"
    else
        echo -e "${YELLOW}  Warning: IonCube loader for PHP ${PHP_VERSION} not found in package${NC}"
        echo -e "${YELLOW}  Available loaders: $(ls ioncube/ioncube_loader_lin_*.so 2>/dev/null | xargs -n1 basename | tr '\n' ' ')${NC}"
    fi

    rm -rf /tmp/ioncube* 2>/dev/null || true
else
    echo -e "${YELLOW}  Warning: Could not download IonCube Loader${NC}"
    echo -e "${YELLOW}  You may need to install it manually later${NC}"
fi

# =============================================
# DISABLE DGEN UPDATE SYSTEM
# =============================================
echo -e "${CYAN}[12/18]${NC} Disabling DGEN update system..."

# Remove/disable the DGEN update scripts
rm -f "$PANEL_DIR/hyper_fetch.sh" 2>/dev/null || true
rm -f "$PANEL_DIR/hyper_watcher.sh" 2>/dev/null || true
rm -f "$PANEL_DIR/hyper_auto_update.sh" 2>/dev/null || true

# Disable any supervisor DGEN processes
supervisorctl stop pterodactyl-hyper-watcher 2>/dev/null || true
rm -f /etc/supervisor/conf.d/pterodactyl-hyper-watcher.conf 2>/dev/null || true

# Remove IonCube key files (license bypass)
rm -rf /etc/hyperv2/ 2>/dev/null || true

echo -e "${GREEN}  DGEN update system disabled!${NC}"

# Migrate database
echo -e "${CYAN}[13/18]${NC} Migrating database..."

php artisan migrate --force 2>/dev/null || true
php artisan db:seed --class=AdministratorSeeder --force 2>/dev/null || true

# Create admin user
echo -e "${CYAN}[14/18]${NC} Creating admin user..."

php artisan p:user:make --email="$ADMIN_EMAIL" --name-first="$ADMIN_NAME" --name-last="" --password="$ADMIN_PASS" --admin=1 --no-interaction 2>/dev/null || \
php artisan tinker --execute="
\$user = new \Pterodactyl\Models\User;
\$user->email = '$ADMIN_EMAIL';
\$user->name_first = '$ADMIN_NAME';
\$user->name_last = '';
\$user->password = bcrypt('$ADMIN_PASS');
\$user->root_admin = 1;
\$user->email_verified_at = now();
\$user->save();
echo 'Admin user created';
" 2>/dev/null || echo "Warning: Create admin user manually"

# Set permissions
echo -e "${CYAN}[15/18]${NC} Setting permissions..."

chown -R www-data:www-data "$PANEL_DIR"/*
chmod -R 755 "$PANEL_DIR"/storage/* "$PANEL_DIR"/bootstrap/cache/

# Configure Nginx
echo -e "${CYAN}[16/18]${NC} Configuring Nginx..."

FPM_SOCK="/run/php/php8.4-fpm.sock"
DOMAIN=$(echo "$PANEL_URL" | sed 's|https\?://||' | sed 's|/.*||')

cat > /etc/nginx/sites-available/pterodactyl.conf <<NGINX
server {
    listen 80;
    server_name ${DOMAIN};

    root /var/www/pterodactyl/public;
    index index.html index.htm index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    access_log  /var/log/nginx/pterodactyl.app-access.log;
    error_log   /var/log/nginx/pterodactyl.app-error.log error;

    client_max_body_size 100m;
    client_body_timeout 120s;

    location ~ \.php\$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)\$;
        fastcgi_pass unix:${FPM_SOCK};
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param PHP_VALUE "upload_max_filesize = 100M \n post_max_size=100M";
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "http";
        fastcgi_intercept_errors off;
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINX

ln -sf /etc/nginx/sites-available/pterodactyl.conf /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

nginx -t && systemctl restart nginx

# Start PHP-FPM
systemctl restart php8.4-fpm 2>/dev/null || service php8.4-fpm restart 2>/dev/null || true

# Clear cache
echo -e "${CYAN}[17/18]${NC} Clearing cache..."

php artisan config:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true
php artisan queue:restart 2>/dev/null || true

# Setup queue worker
cat > /etc/supervisor/conf.d/pterodactyl-worker.conf <<EOF
[program:pterodactyl-worker]
command=php /var/www/pterodactyl/artisan queue:work --queue=high,standard,default,low --sleep=3 --tries=3 --timeout=90 --memory=256
directory=/var/www/pterodactyl
user=www-data
autostart=true
autorestart=true
startretries=3
stopwaitsecs=360
stopasgroup=true
killasgroup=true
stderr_logfile=/var/log/pterodactyl/worker.err.log
stdout_logfile=/var/log/pterodactyl/worker.out.log
EOF

mkdir -p /var/log/pterodactyl
chown www-data:www-data /var/log/pterodactyl
supervisorctl reread 2>/dev/null || true
supervisorctl update 2>/dev/null || true
supervisorctl start pterodactyl-worker 2>/dev/null || true

# Setup scheduler
cat > /etc/supervisor/conf.d/pterodactyl-scheduler.conf <<EOF
[program:pterodactyl-scheduler]
command=php /var/www/pterodactyl/artisan schedule:work
directory=/var/www/pterodactyl
user=www-data
autostart=true
autorestart=true
startretries=3
stopasgroup=true
killasgroup=true
stderr_logfile=/var/log/pterodactyl/scheduler.err.log
stdout_logfile=/dev/null
EOF

supervisorctl reread 2>/dev/null || true
supervisorctl update 2>/dev/null || true
supervisorctl start pterodactyl-scheduler 2>/dev/null || true

# Save credentials
CRED_FILE="$SCRIPT_DIR/credentials.txt"
cat > "$CRED_FILE" <<CREDS
============================================
  HYPER GAME PANEL - CREDENTIALS
============================================
Panel URL:    $PANEL_URL
Admin Email:  $ADMIN_EMAIL
Admin Pass:   $ADMIN_PASS
DB Name:      $DB_NAME
DB User:      $DB_USER
DB Pass:      $DB_PASS
============================================
KEEP THIS FILE SAFE!
============================================
CREDS

chmod 600 "$CRED_FILE"

# Final status
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}   INSTALLATION COMPLETE!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo -e "  Panel URL:    ${CYAN}$PANEL_URL${NC}"
echo -e "  Admin Email:  ${CYAN}$ADMIN_EMAIL${NC}"
echo -e "  Admin Pass:   ${CYAN}$ADMIN_PASS${NC}"
echo ""
echo -e "  Credentials saved to: ${YELLOW}$CRED_FILE${NC}"
echo ""

# Check IonCube status
if php -m 2>/dev/null | grep -qi ioncube; then
    echo -e "  IonCube Loader: ${GREEN}ACTIVE${NC}"
else
    echo -e "  IonCube Loader: ${YELLOW}NOT DETECTED - Install manually if needed${NC}"
    echo -e "  Download from: https://www.ioncube.com/loaders.php"
fi

echo ""
echo -e "  ${GREEN}Login and start managing your servers!${NC}"
echo ""
