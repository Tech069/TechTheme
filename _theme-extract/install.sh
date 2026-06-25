#!/bin/bash
set -e
RED='\033[0;31m'; GREEN='\033[0;32m'; CYAN='\033[0;36m'; YELLOW='\033[1;33m'; NC='\033[0m'
SCRIPT_DIR="/tmp/vexy-installer"; mkdir -p "$SCRIPT_DIR"
PANEL_DIR="/var/www/pterodactyl"
RELEASE_URL="https://github.com/CodeByCruel/AvtixTheme/releases/download/v1.0/Hyper-NoLicense-Installer.zip"
DB_NAME="panel"; DB_USER="pterodactyl"; DB_PASS="AvtixPanel2026"
LICENSE_API="https://vt-panel-api.vercel.app"
echo -e "${CYAN}"; echo "============================================"; echo "   VEXYTHEMES PTERODACTYL INSTALLER"; echo "   Premium Theme by VexyThemes"; echo "============================================"; echo -e "${NC}"
[[ "$EUID" -ne 0 ]] && echo -e "${RED}Run as root${NC}" && exit 1

# Theme is free to install — license key is required after install via the panel settings
echo -e "${CYAN}[0/12]${NC} Checking license server..."
MY_IP=$(curl -s --max-time 5 https://api.ipify.org 2>/dev/null || curl -s --max-time 5 https://ifconfig.me 2>/dev/null || echo "unknown")
echo "  Your IP: $MY_IP"
CHECK_RESPONSE=$(curl -s --max-time 10 -X POST "${LICENSE_API}/api/index" -H "Content-Type: application/json" -d "{\"_endpoint\":\"check\",\"ip\":\"${MY_IP}\"}" 2>/dev/null || echo '{}')
if echo "$CHECK_RESPONSE" | grep -q '"allowed":true\|"allowed": false'; then
    echo -e "  ${GREEN}Server reachable — install will proceed${NC}"
else
    echo -e "  ${YELLOW}License server not reachable — install will proceed (license check deferred to panel)${NC}"
fi

ACTION="${1:-install}"
if [[ "$ACTION" == "uninstall" ]]; then
    read -p "Remove panel? (y/N): " C; [[ "$C" != "y" && "$C" != "Y" ]] && exit 0
    systemctl stop pterodactyl-worker pterodactyl-scheduler 2>/dev/null || true
    rm -rf "$PANEL_DIR" /etc/nginx/sites-available/pterodactyl.conf /etc/nginx/sites-enabled/pterodactyl.conf
    rm -f /etc/supervisor/conf.d/pterodactyl-*.conf; supervisorctl reread 2>/dev/null; supervisorctl update 2>/dev/null
    systemctl restart nginx 2>/dev/null || true
    echo -e "${GREEN}Panel removed.${NC}"; exit 0
fi
if [[ "$ACTION" == "repair" ]]; then
    [[ ! -f "$PANEL_DIR/artisan" ]] && echo -e "${RED}Panel not found${NC}" && exit 1
    cd "$PANEL_DIR"
    # Fix directory permissions
    find resources -type d -exec chmod 755 {} + 2>/dev/null || true
    find storage -type d -exec chmod 755 {} + 2>/dev/null || true
    find bootstrap/cache -type d -exec chmod 755 {} + 2>/dev/null || true
    chown -R www-data:www-data "$PANEL_DIR"
    for c in config:clear view:clear cache:clear config:cache; do php artisan $c 2>/dev/null || true; done
    php artisan migrate --force 2>/dev/null || true
    systemctl restart php8.4-fpm nginx 2>/dev/null || true
    supervisorctl restart pterodactyl-worker pterodactyl-scheduler 2>/dev/null || true
    echo -e "${GREEN}Repaired!${NC}"; exit 0
fi
if [[ "$ACTION" == "update" ]]; then
    echo -e "${YELLOW}Coming soon! Use uninstall + install for now.${NC}"; exit 0
fi
# INPUT: use env vars if set, otherwise prompt only if terminal is interactive
ADMIN_USER="${ADMIN_USER:-admin}"
if [[ -n "$PANEL_URL" && -n "$ADMIN_EMAIL" && -n "$ADMIN_PASS" ]]; then
    : # all set from env vars, no prompting needed
elif [[ -t 0 ]] || [[ -t /dev/tty ]]; then
    read -p "Panel URL (e.g. https://panel.example.com): " PANEL_URL < /dev/tty
    read -p "Admin email [admin@admin.com]: " ADMIN_EMAIL < /dev/tty
    ADMIN_EMAIL="${ADMIN_EMAIL:-admin@admin.com}"
    read -s -p "Admin password [admin]: " ADMIN_PASS < /dev/tty; echo ""
    ADMIN_PASS="${ADMIN_PASS:-admin}"
    read -p "Admin username [$ADMIN_USER]: " tmp_user < /dev/tty
    ADMIN_USER="${tmp_user:-$ADMIN_USER}"
else
    echo -e "${RED}Non-interactive: set PANEL_URL, ADMIN_EMAIL, ADMIN_PASS env vars${NC}"
    echo "Example: PANEL_URL=https://panel.example.com ADMIN_EMAIL=admin@example.com ADMIN_PASS=secret bash install.sh"
    exit 1
fi
[[ -z "$PANEL_URL" || -z "$ADMIN_EMAIL" || -z "$ADMIN_PASS" ]] && echo -e "${RED}All fields required${NC}" && exit 1
DOMAIN=$(echo "$PANEL_URL" | sed 's|https\?://||' | sed 's|/.*||')
echo -e "${CYAN}[1/12]${NC} Updating system..."
export DEBIAN_FRONTEND=noninteractive; apt-get update -y && apt-get upgrade -y
echo -e "${CYAN}[2/12]${NC} Installing dependencies..."
apt-get install -y software-properties-common curl apt-transport-https ca-certificates gnupg unzip zip git redis-server mariadb-server nginx
echo -e "${CYAN}[3/12]${NC} Installing PHP 8.4..."
if ! command -v php8.4 >/dev/null 2>&1; then
    curl -sSLo /tmp/php.gpg https://packages.sury.org/php/apt.gpg 2>/dev/null || wget -qO /tmp/php.gpg https://packages.sury.org/php/apt.gpg 2>/dev/null || true
    install -o root -g root -m 644 /tmp/php.gpg /usr/share/keyrings/deb.sury.org-php.gpg 2>/dev/null || true; rm -f /tmp/php.gpg
    grep -rq "ondrej/php" /etc/apt/sources.list /etc/apt/sources.list.d/ 2>/dev/null || echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -cs) main" > /etc/apt/sources.list.d/php.list
    apt-get update -y
    apt-get install -y php8.4 php8.4-cli php8.4-fpm php8.4-common php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-mysql php8.4-tokenizer php8.4-xmlwriter php8.4-fileinfo php8.4-opcache php8.4-redis
fi
echo -e "${CYAN}[4/12]${NC} Installing Composer..."
command -v composer >/dev/null 2>&1 || curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
echo -e "${CYAN}[5/12]${NC} Configuring database..."
systemctl start mariadb 2>/dev/null || service mariadb start 2>/dev/null || true; systemctl enable mariadb 2>/dev/null || true
mysql -u root -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME}; CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}'; CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}'; ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}'; ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}'; GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1'; GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"
echo -e "${GREEN}  Database ready!${NC}"
echo -e "${CYAN}[6/12]${NC} Configuring Redis..."
systemctl start redis-server 2>/dev/null || true; systemctl enable redis-server 2>/dev/null || true
echo -e "${CYAN}[7/12]${NC} Installing Pterodactyl..."
rm -rf "$PANEL_DIR"
git clone https://github.com/pterodactyl/panel.git "$PANEL_DIR" --depth 1 2>/dev/null || { mkdir -p "$PANEL_DIR"; curl -fsSL -o /tmp/pt.tar.gz "https://github.com/pterodactyl/panel/archive/refs/heads/1.0-develop.tar.gz" 2>/dev/null; tar -xzf /tmp/pt.tar.gz -C "$PANEL_DIR" --strip-components=1; rm -f /tmp/pt.tar.gz; }
[[ ! -f "$PANEL_DIR/artisan" ]] && echo -e "${RED}Panel download failed${NC}" && exit 1
echo -e "${CYAN}[8/12]${NC} Composer install..."
cd "$PANEL_DIR"; export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || composer update --no-dev --optimize-autoloader --no-interaction
echo -e "${CYAN}[9/12]${NC} Environment..."
cp .env.example .env 2>/dev/null || true; php artisan key:generate --force 2>/dev/null || true
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
sed -i "s|APP_NAME=.*|APP_NAME=\"VexyThemes Panel\"|g" .env
echo -e "${CYAN}[10/12]${NC} Downloading theme..."
[[ ! -f "$SCRIPT_DIR/Hyper-NoLicense-Installer.zip" ]] && curl -fsSL --retry 3 -o "$SCRIPT_DIR/Hyper-NoLicense-Installer.zip" "$RELEASE_URL" 2>/dev/null || true
if [[ -f "$SCRIPT_DIR/Hyper-NoLicense-Installer.zip" ]]; then
    rm -rf "$SCRIPT_DIR/te" "$SCRIPT_DIR/ti"; mkdir -p "$SCRIPT_DIR/te" "$SCRIPT_DIR/ti"
    unzip -oq "$SCRIPT_DIR/Hyper-NoLicense-Installer.zip" -d "$SCRIPT_DIR/te/" 2>/dev/null
    [[ -f "$SCRIPT_DIR/te/Hyper-modified.zip" ]] && unzip -oq "$SCRIPT_DIR/te/Hyper-modified.zip" -d "$SCRIPT_DIR/ti/" 2>/dev/null
    T="$SCRIPT_DIR/ti"
    cp -r "$T/public/assets" "$PANEL_DIR/public/" 2>/dev/null || true
    for d in DGEN favicons logo themes js; do [[ -d "$T/public/$d" ]] && cp -r "$T/public/$d" "$PANEL_DIR/public/" 2>/dev/null; done
    cp -f "$T/public/service-worker.js" "$PANEL_DIR/public/" 2>/dev/null || true
    rm -rf "$PANEL_DIR/resources/views" && cp -r "$T/resources/views" "$PANEL_DIR/resources/" 2>/dev/null || true
    cp -r "$T/resources/lang" "$PANEL_DIR/resources/" 2>/dev/null || true
    # Rebrand title only (don't replace all Pterodactyl - breaks PHP strings)
    sed -i 's|<title>.*</title>|<title>VexyThemes Panel</title>|g' "$PANEL_DIR/resources/views/templates/wrapper.blade.php" 2>/dev/null || true
    rm -rf "$SCRIPT_DIR/te" "$SCRIPT_DIR/ti"
fi
echo -e "${CYAN}[10b/12]${NC} Applying i18n & theme fixes..."
cd "$PANEL_DIR"
# 1. Rename hyper.css -> avtix.css and update wrapper reference
[[ -f public/assets/css/hyper.css ]] && mv public/assets/css/hyper.css public/assets/css/avtix.css
sed -i 's|hyper\.css|avtix.css|g' resources/views/templates/wrapper.blade.php 2>/dev/null || true
sed -i 's|id="hyper-theme-vars"|id="avtix-theme-vars"|g' resources/views/templates/wrapper.blade.php 2>/dev/null || true
# 2. Generate static i18n dict from lang file
php -r "\$l='public/DGEN/themes/Hyperv2/lang/en.json';if(file_exists(\$l)){file_put_contents('public/i18n-dict.json',json_encode(json_decode(file_get_contents(\$l),true),JSON_UNESCAPED_UNICODE));echo 'OK: '.strlen(file_get_contents('public/i18n-dict.json')).\" bytes\n\";}else echo 'LANG NOT FOUND\n';" 2>/dev/null || true
# 3. Fix wrapper i18n: replace Blade @if with pure PHP, remove stray endif
php -r "
\$w=file_get_contents('resources/views/templates/wrapper.blade.php');
\$old='@endphp
            <script data-cfasync=\"false\">
                window.__I18N_LANG__ = {!! json_encode(\$userLang) !!};
                @if(\$inlineDictJson !== null)
                window.__I18N_DICT__ = {!! \$inlineDictJson !!};
                window.__I18N_LANG_INLINE__ = {!! json_encode(\$userLang, JSON_HEX_TAG) !!};
                @endif
                @if(\$inlineLocaleResourcesJson !== null)
                window.__I18N_RESOURCES__ = {!! \$inlineLocaleResourcesJson !!};
                @endif
            </script>';
\$new='@endphp
            <script data-cfasync=\"false\">
                window.__I18N_LANG__ = <?php echo json_encode(\$userLang); ?>;
                window.__I18N_DICT__ = <?php echo @file_get_contents(public_path(\"i18n-dict.json\")); ?>;
                window.__I18N_LANG_INLINE__ = <?php echo json_encode(\$userLang, JSON_HEX_TAG); ?>;
            </script>
            <?php if (!empty(\$inlineLocaleResourcesJson)): ?>
            <script data-cfasync=\"false\">
                window.__I18N_RESOURCES__ = <?php echo \$inlineLocaleResourcesJson; ?>;
            </script>
            <?php endif; ?>';
\$w=str_replace(\$old,\$new,\$w);
// Remove stray endif (leftover from old @if dict block)
\$w=str_replace('<?php endif; ?>' . PHP_EOL . '<?php endif; ?>' . PHP_EOL . PHP_EOL . '@if(isset(\$errors)', '<?php endif; ?>' . PHP_EOL . PHP_EOL . '@if(isset(\$errors)', \$w);
file_put_contents('resources/views/templates/wrapper.blade.php', \$w);
echo 'Wrapper i18n fixed';
" 2>/dev/null || true
# 4. Fix directory permissions (critical!)
find resources -type d -exec chmod 755 {} + 2>/dev/null || true
find resources -type f -exec chmod 644 {} + 2>/dev/null || true
echo -e "${GREEN}  Theme fixes applied!${NC}"
# 5. Install VexyThemes license gate system
cat > "$PANEL_DIR/public/vexythemes-license-check.php" << 'VEXYCHECK'
<?php
if (!defined('VEXYTHEMES_RUNTIME')) define('VEXYTHEMES_RUNTIME', true);

// === VexyThemes Integrity Layer ===
function _vxt_verify_key_format($key) {
    if (strpos($key, 'VEXY-') !== 0) return false;
    $parts = explode('-', $key);
    if (count($parts) !== 5) return false;
    foreach ($parts as $p) { if (strlen($p) !== 4 || !ctype_alnum($p)) return false; }
    return true;
}

function _vxt_gen_checksum($data) {
    return hash_hmac('sha256', $data, 'vxt_hmac_secret_2026');
}

function _vxt_verify_checksum($data, $checksum) {
    return hash_equals(_vxt_gen_checksum($data), $checksum);
}

// Anti-tamper: check critical files haven't been modified
function _vxt_check_integrity() {
    $base = dirname(__DIR__);
    $checks = [
        'public/index.php' => 'VEXYTHEMES_RUNTIME',
        'resources/views/vexythemes/license-gate.blade.php' => 'VexyThemes',
    ];
    foreach ($checks as $file => $marker) {
        $path = $base . '/' . $file;
        if (file_exists($path)) {
            $content = file_get_contents($path);
            if (strpos($content, $marker) === false && $marker !== 'VEXYTHEMES_RUNTIME') {
                return false;
            }
        }
    }
    return true;
}

function vexythemes_check_license() {
    $cacheFile = sys_get_temp_dir().'/vxt_'.md5($_SERVER['SERVER_ADDR']??'local');
    @mkdir(dirname($cacheFile), 0755, true);
    
    if (file_exists($cacheFile)) {
        $c = json_decode(@file_get_contents($cacheFile), true);
        if ($c && time()-($c['t']??0) < 86400 && isset($c['v'])) return (bool)$c['v'];
    }
    
    $key = getenv('VEXYTHEMES_LICENSE_KEY') ?: '';
    
    if (!$key) {
        try {
            $pdo = new PDO('mysql:host=127.0.0.1;dbname=panel', 'pterodactyl', hex2bin('417674697850616e656c32303236'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = ? LIMIT 1');
            $stmt->execute([hex2bin('766578797468656d65735f6c6963656e73655f6b6579')]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['value'])) $key = $row['value'];
        } catch (Throwable $e) {}
    }
    
    if (!$key || !_vxt_verify_key_format($key)) {
        @file_put_contents($cacheFile, json_encode(['v'=>0,'t'=>time()]));
        return false;
    }
    
    $ip = '127.0.0.1';
    $ipRes = @file_get_contents('https://api.ipify.org', false, stream_context_create(['http'=>['timeout'=>3]]));
    if ($ipRes) $ip = trim($ipRes);
    else $ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    
    $apiUrl = hex2bin('68747470733a2f2f76742d70616e656c2d6170692e76657263656c2e6170702f6170692f696e646578');
    $payload = json_encode(['_endpoint'=>'license','action'=>'validate','key'=>$key,'ip'=>$ip]);
    $ctx = stream_context_create([
        'http' => ['method'=>'POST','header'=>'Content-Type: application/json','content'=>$payload,'timeout'=>5],
        'ssl' => ['verify_peer'=>false]
    ]);
    $resp = @file_get_contents($apiUrl, false, $ctx);
    
    $valid = false;
    if ($resp) {
        $data = json_decode($resp, true);
        $valid = $data['valid'] ?? false;
        if ($valid && isset($data['checksum'])) {
            @file_put_contents($cacheFile, json_encode(['v'=>1,'t'=>time(),'ip'=>$ip,'cs'=>$data['checksum']]));
        } else {
            @file_put_contents($cacheFile, json_encode(['v'=>0,'t'=>time()]));
        }
    }
    
    return $valid;
}

$uri = $_SERVER['REQUEST_URI'] ?? '/';
if (strpos($uri, '/api/') === false && strpos($uri, '/assets/') === false && strpos($uri, '/vexythemes-license') === false && strpos($uri, '/vexy/') === false) {
    if (!vexythemes_check_license()) {
        header('Location: /vexythemes-license');
        exit;
    }
}
VEXYCHECK

# Add license check to index.php (before autoloader)
sed -i "s|require __DIR__ . '/vendor/autoload.php';|require __DIR__ . '/vexythemes-license-check.php';\nrequire __DIR__ . '/vendor/autoload.php';|" "$PANEL_DIR/public/index.php" 2>/dev/null || true
echo -e "  VexyThemes license gate installed"

# Create the license prompt page
mkdir -p "$PANEL_DIR/resources/views/vexythemes"
cat > "$PANEL_DIR/resources/views/vexythemes/license-gate.blade.php" << 'LICENSEGATE'
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VexyThemes — License Required</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#09090b;color:#fafafa;min-height:100vh;display:flex;align-items:center;justify-content:center}
        .card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:40px;max-width:440px;width:90%;text-align:center}
        .logo{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);margin:0 auto 24px;box-shadow:0 0 40px -6px rgba(99,102,241,.5)}
        h1{font-size:24px;font-weight:700;margin-bottom:8px}
        h1 span{background:linear-gradient(135deg,#6366f1,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        p{color:#a1a1aa;font-size:14px;margin-bottom:24px}
        input{width:100%;padding:12px 16px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fafafa;font-size:15px;font-family:'SF Mono','Fira Code',monospace;letter-spacing:2px;text-transform:uppercase;text-align:center;outline:none;transition:border-color .2s}
        input:focus{border-color:#6366f1}
        input::placeholder{color:#52525b;letter-spacing:normal;text-transform:none}
        button{width:100%;padding:12px;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;transition:opacity .2s;margin-top:12px}
        .btn-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;box-shadow:0 8px 24px -8px rgba(99,102,241,.5)}
        .btn-primary:hover{opacity:.9}
        .btn-primary:disabled{opacity:.5;cursor:not-allowed}
        .error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:10px 14px;color:#fca5a5;font-size:13px;margin-bottom:16px;display:none}
        .success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);border-radius:8px;padding:10px 14px;color:#86efac;font-size:13px;margin-bottom:16px;display:none}
        .spinner{display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
        .footer{margin-top:24px;font-size:12px;color:#52525b}
    </style>
</head>
<body>
    <div class="card">
        <div class="logo"></div>
        <h1>Vexy<span>Themes</span></h1>
        <p>Enter your license key to activate this panel theme.</p>
        <div id="error" class="error"></div>
        <div id="success" class="success"></div>
        <form id="licenseForm">
            <input type="text" id="key" placeholder="VEXY-XXXX-XXXX-XXXX-XXXX" maxlength="26" autocomplete="off" spellcheck="false">
            <button type="submit" id="submitBtn" class="btn-primary">
                <span id="btnText">Activate License</span>
                <span id="btnSpinner" class="spinner" style="display:none"></span>
            </button>
        </form>
        <p class="footer">Don't have a key? Contact us on Discord.</p>
    </div>
    <script>
    const API='{{ config("app.url") }}';
    const keyInput=document.getElementById('key');
    keyInput.addEventListener('input',function(e){let v=e.target.value.replace(/[^A-Za-z0-9]/g,'').toUpperCase();let f='';for(let i=0;i<v.length&&i<20;i++){if(i>0&&i%4===0)f+='-';f+=v[i]}e.target.value='VEXY-'+f;if(e.target.value==='VEXY-')e.target.value=''});
    document.getElementById('licenseForm').addEventListener('submit',async function(e){
        e.preventDefault();
        const key=keyInput.value.trim();
        if(!key||key.length<20){showError('Please enter a valid license key.');return}
        const btn=document.getElementById('submitBtn'),txt=document.getElementById('btnText'),spin=document.getElementById('btnSpinner');
        btn.disabled=true;txt.textContent='Activating...';spin.style.display='inline-block';hideError();hideSuccess();
        try{
            const ipRes=await fetch('https://api.ipify.org?format=json');const ipData=await ipRes.json();
            const res=await fetch(API+'/vexythemes-license',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','Accept':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'save',key:key})});
            const data=await res.json();
            if(data.success){showSuccess('License activated! Redirecting...');setTimeout(()=>{window.location.href='/admin'},1500)}
            else{showError(data.error||'Invalid license key.')}
        }catch(err){showError('Failed to activate. Try again.')}
        finally{btn.disabled=false;txt.textContent='Activate License';spin.style.display='none'}
    });
    function showError(m){const e=document.getElementById('error');e.textContent=m;e.style.display='block'}
    function hideError(){document.getElementById('error').style.display='none'}
    function showSuccess(m){const e=document.getElementById('success');e.textContent=m;e.style.display='block'}
    function hideSuccess(){document.getElementById('success').style.display='none'}
    </script>
</body>
</html>
LICENSEGATE
echo -e "${CYAN}[11/12]${NC} Applying patches..."
cd "$PANEL_DIR"
mkdir -p app/Repositories/Eloquent app/Services app/Traits app/Http/Middleware
mkdir -p app/Http/Controllers/Api/Application app/Http/Controllers/Api/Client/AddonControllers
# SettingsRepository (standalone - implements interface directly, no EloquentRepository dependency)
cat > app/Repositories/Eloquent/SettingsRepository.php << 'EOF'
<?php
namespace Pterodactyl\Repositories\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Models\Setting;

class SettingsRepository implements SettingsRepositoryInterface
{
    public function model(): string { return Setting::class; }
    public function getModel(): Model { return new Setting(); }
    public function getBuilder(): Builder { return Setting::query(); }
    public function getColumns(): array { return ['*']; }
    public function setColumns(array|string $columns = ['*']): static { return $this; }
    public function withoutFreshModel(): static { return $this; }
    public function withFreshModel(): static { return $this; }
    public function setFreshModel(bool $fresh = true): static { return $this; }
    public function create(array $fields, bool $validate = true, bool $force = false): mixed { return Setting::create($fields); }
    public function find(int $id): mixed { return Setting::find($id); }
    public function findWhere(array $fields): Collection { return Setting::where($fields)->get(); }
    public function findFirstWhere(array $fields): mixed { return Setting::where($fields)->first(); }
    public function findCountWhere(array $fields): int { return Setting::where($fields)->count(); }
    public function delete(int $id): int { return Setting::destroy($id); }
    public function deleteWhere(array $attributes): int { return Setting::where($attributes)->delete(); }
    public function update(int $id, array $fields, bool $validate = true, bool $force = false): mixed { $m = Setting::findOrFail($id); $m->update($fields); return $m; }
    public function updateWhereIn(string $column, array $values, array $fields): int { return Setting::whereIn($column, $values)->update($fields); }
    public function updateOrCreate(array $where, array $fields, bool $validate = true, bool $force = false): mixed { return Setting::updateOrCreate($where, $fields); }
    public function all(): Collection { return Setting::all(); }
    public function paginated(int $perPage): LengthAwarePaginator { return Setting::paginate($perPage); }
    public function insert(array $data): bool { return (bool) Setting::insert($data); }
    public function insertIgnore(array $values): bool { foreach ($values as $v) { Setting::updateOrCreate(['key' => $v['key']], $v); } return true; }
    public function count(): int { return Setting::count(); }
    public function set(string $key, ?string $value = null) { try { return Setting::updateOrCreate(['key' => $key], ['value' => $value]); } catch (\Throwable $e) { return null; } }
    public function get(string $key, mixed $default = null): mixed { try { return Setting::where('key', $key)->first()?->value ?? $default; } catch (\Throwable $e) { return $default; } }
    public function forget(string $key) { try { Setting::where('key', $key)->delete(); } catch (\Throwable $e) {} }
}
EOF
# Middleware stubs
for f in HyperV2LicenseGate EnforceHyperV2PanelAccess HyperV2SecurityMonitor; do
cat > app/Http/Middleware/${f}.php << EOF
<?php namespace Pterodactyl\Http\Middleware; use Closure; use Illuminate\Http\Request;
class ${f} { public function handle(Request \$r, Closure \$n) { return \$n(\$r); } }
EOF
done
# Traits
cat > app/Traits/ValidatesSecureLicense.php << 'EOF'
<?php namespace Pterodactyl\Traits;
trait ValidatesSecureLicense { public function isLicenseValid(): bool { return true; } protected function validateLicense(): bool { return true; } }
EOF
# License stubs
cat > app/Services/HyperV2LicenseService.php << 'EOF'
<?php namespace Pterodactyl\Services;
class HyperV2LicenseService {
    public function getLicenseStatus(): array { return ['valid'=>true,'type'=>'enterprise','tier'=>'enterprise','expires'=>null,'holder'=>'Akshit','message'=>'Avtix Game Panel - Free Theme by Akshit']; }
    public function validateLicense(?string $key = null): bool { return true; }
    public function isLicenseValid(): bool { return true; }
    public function getLicenseKey(): ?string { return 'AVTIX-FREE-THEME-2026'; }
    public function isTrialMode(): bool { return false; }
}
EOF
cat > app/Services/LicenseValidationService.php << 'EOF'
<?php namespace Pterodactyl\Services;
class LicenseValidationService {
    public function validate(?string $key = null): bool { return true; }
    public function isValid(): bool { return true; }
    public function getDetails(): array { return ['status'=>'valid','type'=>'enterprise','holder'=>'Akshit']; }
}
EOF
cat > app/Http/Controllers/Api/Application/LicenseController.php << 'EOF'
<?php namespace Pterodactyl\Http\Controllers\Api\Application; use Illuminate\Http\JsonResponse;
class LicenseController {
    public function check(): JsonResponse { return response()->json(['valid'=>true,'type'=>'enterprise']); }
    public function update(): JsonResponse { return response()->json(['valid'=>true]); }
}
EOF
cat > app/Http/Controllers/Api/Client/AvtixLicenseController.php << 'AVTIXCTRL'
<?php
namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class AvtixLicenseController extends Controller
{
    public function verify(): JsonResponse { return response()->json(['valid' => true]); }
    public function status(): JsonResponse { return response()->json(['status' => 'active', 'theme' => 'hyperv2']); }
    public function clear(): JsonResponse { return response()->json(['success' => true]); }
    public function clearAllCache(): JsonResponse { return response()->json(['success' => true]); }
    public function version(): JsonResponse { return response()->json(['version' => '3.0.0']); }
    public function ssoInfo(): JsonResponse { return response()->json(['enabled' => false]); }
    public function ssoExchange(): JsonResponse { return response()->json(['success' => false, 'message' => 'Not configured']); }
    public function ssoDisconnect(): JsonResponse { return response()->json(['success' => true]); }
    public function update(): JsonResponse { return response()->json(['success' => true]); }
    public function notifications(): JsonResponse { return response()->json(['success' => true]); }
}
AVTIXCTRL
cat > app/Http/Controllers/Api/Client/VexyThemesLicenseController.php << 'VEXYCTRL'
<?php
namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class VexyThemesLicenseController extends Controller
{
    const LICENSE_API = 'https://vt-panel-api.vercel.app';

    public function handle(Request $request): JsonResponse
    {
        $action = $request->input('action', '');

        if ($action === 'status') return $this->getStatus();
        if ($action === 'save') return $this->save($request);
        if ($action === 'remove') return $this->remove($request);

        return response()->json(['error' => 'Unknown action'], 400);
    }

    private function getStatus(): JsonResponse
    {
        try {
            $key = DB::table('settings')->where('key', 'vexythemes_license_key')->first();
            $hasKey = $key && !empty($key->value);
            return response()->json([
                'has_key' => $hasKey,
                'masked_key' => $hasKey ? $this->maskKey($key->value) : null,
                'valid' => $this->checkCachedValidity(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['has_key' => false, 'valid' => false]);
        }
    }

    private function save(Request $request): JsonResponse
    {
        $key = strtoupper(trim($request->input('key', '')));
        if (empty($key) || strlen($key) < 20) {
            return response()->json(['error' => 'Invalid license key format'], 400);
        }

        $ip = $this->getServerIp();

        $response = @file_get_contents(self::LICENSE_API . '/api/index', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode(['_endpoint' => 'license', 'action' => 'activate', 'key' => $key, 'ip' => $ip]),
                'timeout' => 10,
            ],
            'ssl' => ['verify_peer' => false],
        ]));

        if (!$response) {
            return response()->json(['error' => 'Failed to connect to license server'], 500);
        }

        $data = json_decode($response, true);
        if (!($data['success'] ?? false)) {
            return response()->json(['error' => $data['error'] ?? 'License validation failed'], 400);
        }

        DB::table('settings')->updateOrCreate(
            ['key' => 'vexythemes_license_key'],
            ['value' => $key, 'updated_at' => now()]
        );
        DB::table('settings')->where('key', 'vexythemes_license_cache')->delete();

        return response()->json([
            'success' => true,
            'masked_key' => $this->maskKey($key),
            'message' => 'License activated successfully',
        ]);
    }

    private function remove(Request $request): JsonResponse
    {
        try {
            $key = DB::table('settings')->where('key', 'vexythemes_license_key')->first();
            if ($key && !empty($key->value)) {
                $ip = $this->getServerIp();
                @file_get_contents(self::LICENSE_API . '/api/index', false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => 'Content-Type: application/json',
                        'content' => json_encode(['_endpoint' => 'license', 'action' => 'deactivate', 'key' => $key->value, 'ip' => $ip]),
                        'timeout' => 10,
                    ],
                    'ssl' => ['verify_peer' => false],
                ]));
                DB::table('settings')->where('key', 'vexythemes_license_key')->delete();
                DB::table('settings')->where('key', 'vexythemes_license_cache')->delete();
            }
            return response()->json(['success' => true, 'message' => 'License removed']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to remove license'], 500);
        }
    }

    private function maskKey(string $key): string {
        if (strlen($key) > 5) return substr($key, 0, 5) . str_repeat('•', strlen($key) - 5);
        return str_repeat('•', strlen($key));
    }

    private function checkCachedValidity(): bool
    {
        try {
            $cache = DB::table('settings')->where('key', 'vexythemes_license_cache')->first();
            if ($cache && $cache->value) {
                $cached = json_decode($cache->value, true);
                if ($cached && isset($cached['valid']) && isset($cached['time'])) {
                    if (time() - $cached['time'] < 86400) return $cached['valid'];
                }
            }
        } catch (\Throwable $e) {}
        return false;
    }

    private function getServerIp(): string
    {
        try {
            $ip = @file_get_contents('https://api.ipify.org', false, stream_context_create(['http' => ['timeout' => 3]]));
            if ($ip) return trim($ip);
        } catch (\Throwable $e) {}
        return $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    }
}
VEXYCTRL

for pair in "ThemeController:defaults:theme,hyperv2,dark_mode,true,accent_color,#6366f1|addons:[]|addonSettings:settings,[]|authAddonSettings:settings,[]|pwaManifest:name,Avtix Game Panel,short_name,Avtix,start_url,/,display,standalone|pwaSwConfig:enabled,false" "BillingController:balance,balance,0,currency,USD|discount,discount,0|services,services,[]|storeCategories,categories,[]|orderCreate,success,true,message,Billing disabled|topUp,success,true|verify,verified,true|promoValidate,valid,false,message,No promotions|referral,referral_code,null,referral_count,0,referral_earnings,0|referralCode,code,null|referralWithdraw,success,false,message,No funds" "SubdomainController:fetchAllSubdomains,subdomains,[]|fetchDomains,domains,[]|testConnection,connected,false,message,Not configured" "DiscordBotController:botStatus,online,false,message,Bot not configured|stats,guilds,0,users,0|restart,success,false|sync,success,false" "DiscordVerificationController:index,verified,false|account,verified,false|refresh,verified,false|accountRefresh,verified,false" "DdosAlertController:attacks,attacks,[]|charts,charts,[]|summary,total_attacks,0,blocked,0,active,0|syncNow,success,true" "NodeStatusController:index,nodes,[]|monitors,monitors,[]|createMonitor,success,true" "DgenStatsController:serverStats,cpu,0,memory,0,disk,0,status,running" "ExportRawController:index,export,[]" "ReverseProxyController:search,proxies,[]|whitelist,whitelist,[]|addToWhitelist,success,true" "FastDLController:setup,success,false,message,Not configured|remove,success,true" "ServerSplitterController:legacySplits,splits,[]|search,servers,[]|users,users,[]|whitelist,whitelist,[]|hook,success,true|migrateSplits,success,true,migrated,0|addToWhitelist,success,true" "ServerTypeChangerController:whitelist,whitelist,[]|searchWhitelist,results,[]|addToWhitelist,success,true|allNestsEggs,nests,[]" "ServerWiperController:index,servers,[]|wipe,success,true" "StaffRequestController:myServers,servers,[]|ownerRequests,requests,[]|requests,requests,[]|requestCount,count,0|servers,servers,[]|createRequest,success,true" "ServerImporterController:testConnection,connected,false,message,Not available" "UploadFromUrlController:query,valid:false,message,Not available" "WingsController:checkStatus,online:true,message,Node operational" "GithubController:account,connected:false|connectAccount,success:false,message,Not configured|disconnectAccount,success,true|repositories,repositories,[]" "AdminDgenController:billingCategories,categories,[]|billingGames,games,[]|billingPromocodes,promocodes,[]|billingSubcategories,subcategories,[]|members,members,[]|roles,roles,[]|globalStorageBackends,backends,[]|testStorageBackend,success,true"

# Generate addon controllers from the data above
for spec in "${pair}"; do
    :
done

# Simpler: write each controller directly
cat > app/Http/Controllers/Api/Client/AddonControllers/ThemeController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class ThemeController extends Controller {
    public function defaults(): JsonResponse { return response()->json(['theme'=>'hyperv2','dark_mode'=>true,'accent_color'=>'#6366f1']); }
    public function addons(): JsonResponse { return response()->json(['addons'=>[]]); }
    public function addonSettings(): JsonResponse { return response()->json(['settings'=>[]]); }
    public function authAddonSettings(): JsonResponse { return response()->json(['settings'=>[]]); }
    public function pwaManifest(): JsonResponse { return response()->json(['name'=>config('app.name'),'short_name'=>'Avtix','start_url'=>'/','display'=>'standalone','background_color'=>'#0c0a09','theme_color'=>'#6366f1']); }
    public function pwaSwConfig(): JsonResponse { return response()->json(['enabled'=>false]); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/BillingController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class BillingController extends Controller {
    public function balance(): JsonResponse { return response()->json(['balance'=>0,'currency'=>'USD']); }
    public function discount(): JsonResponse { return response()->json(['discount'=>0]); }
    public function services(): JsonResponse { return response()->json(['services'=>[]]); }
    public function storeCategories(): JsonResponse { return response()->json(['categories'=>[]]); }
    public function orderCreate(): JsonResponse { return response()->json(['success'=>true,'message'=>'Billing disabled']); }
    public function topUp(): JsonResponse { return response()->json(['success'=>true]); }
    public function verify(): JsonResponse { return response()->json(['verified'=>true]); }
    public function promoValidate(): JsonResponse { return response()->json(['valid'=>false,'message'=>'No promotions']); }
    public function referral(): JsonResponse { return response()->json(['referral_code'=>null,'referral_count'=>0,'referral_earnings'=>0]); }
    public function referralCode(): JsonResponse { return response()->json(['code'=>null]); }
    public function referralWithdraw(): JsonResponse { return response()->json(['success'=>false,'message'=>'No funds']); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/SubdomainController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class SubdomainController extends Controller {
    public function fetchAllSubdomains(): JsonResponse { return response()->json(['subdomains'=>[]]); }
    public function fetchDomains(): JsonResponse { return response()->json(['domains'=>[]]); }
    public function testConnection(): JsonResponse { return response()->json(['connected'=>false,'message'=>'Not configured']); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/DiscordBotController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class DiscordBotController extends Controller {
    public function botStatus(): JsonResponse { return response()->json(['online'=>false,'message'=>'Not configured']); }
    public function stats(): JsonResponse { return response()->json(['guilds'=>0,'users'=>0]); }
    public function restart(): JsonResponse { return response()->json(['success'=>false]); }
    public function sync(): JsonResponse { return response()->json(['success'=>false]); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/DiscordVerificationController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class DiscordVerificationController extends Controller {
    public function check(): JsonResponse { return response()->json(['verified'=>false]); }
    public function accountCheck(): JsonResponse { return response()->json(['verified'=>false]); }
    public function refresh(): JsonResponse { return response()->json(['verified'=>false]); }
    public function accountRefresh(): JsonResponse { return response()->json(['verified'=>false]); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/DdosAlertController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class DdosAlertController extends Controller {
    public function attacks(): JsonResponse { return response()->json(['attacks'=>[]]); }
    public function charts(): JsonResponse { return response()->json(['charts'=>[]]); }
    public function summary(): JsonResponse { return response()->json(['total_attacks'=>0,'blocked'=>0,'active'=>0]); }
    public function syncNow(): JsonResponse { return response()->json(['success'=>true]); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/NodeStatusController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class NodeStatusController extends Controller {
    public function index(): JsonResponse { return response()->json(['nodes'=>[]]); }
    public function monitors(): JsonResponse { return response()->json(['monitors'=>[]]); }
    public function createMonitor(): JsonResponse { return response()->json(['success'=>true]); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/DgenStatsController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class DgenStatsController extends Controller {
    public function serverStats(): JsonResponse { return response()->json(['cpu'=>0,'memory'=>0,'disk'=>0,'status'=>'running']); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/ExportRawController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class ExportRawController extends Controller {
    public function index(): JsonResponse { return response()->json(['export'=>[]]); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/ReverseProxyController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class ReverseProxyController extends Controller {
    public function search(): JsonResponse { return response()->json(['proxies'=>[]]); }
    public function whitelist(): JsonResponse { return response()->json(['whitelist'=>[]]); }
    public function addToWhitelist(): JsonResponse { return response()->json(['success'=>true]); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/FastDLController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class FastDLController extends Controller {
    public function setup(): JsonResponse { return response()->json(['success'=>false,'message'=>'Not configured']); }
    public function remove(): JsonResponse { return response()->json(['success'=>true]); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/ServerSplitterController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class ServerSplitterController extends Controller {
    public function legacySplits(): JsonResponse { return response()->json(['splits'=>[]]); }
    public function search(): JsonResponse { return response()->json(['servers'=>[]]); }
    public function users(): JsonResponse { return response()->json(['users'=>[]]); }
    public function whitelist(): JsonResponse { return response()->json(['whitelist'=>[]]); }
    public function hook(): JsonResponse { return response()->json(['success'=>true]); }
    public function migrateSplits(): JsonResponse { return response()->json(['success'=>true,'migrated'=>0]); }
    public function addToWhitelist(): JsonResponse { return response()->json(['success'=>true]); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/ServerTypeChangerController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class ServerTypeChangerController extends Controller {
    public function whitelist(): JsonResponse { return response()->json(['whitelist'=>[]]); }
    public function searchWhitelist(): JsonResponse { return response()->json(['results'=>[]]); }
    public function addToWhitelist(): JsonResponse { return response()->json(['success'=>true]); }
    public function allNestsEggs(): JsonResponse { return response()->json(['nests'=>[]]); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/ServerWiperController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class ServerWiperController extends Controller {
    public function index(): JsonResponse { return response()->json(['servers'=>[]]); }
    public function wipe(): JsonResponse { return response()->json(['success'=>true]); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/StaffRequestController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class StaffRequestController extends Controller {
    public function myServers(): JsonResponse { return response()->json(['servers'=>[]]); }
    public function ownerRequests(): JsonResponse { return response()->json(['requests'=>[]]); }
    public function requests(): JsonResponse { return response()->json(['requests'=>[]]); }
    public function requestCount(): JsonResponse { return response()->json(['count'=>0]); }
    public function servers(): JsonResponse { return response()->json(['servers'=>[]]); }
    public function createRequest(): JsonResponse { return response()->json(['success'=>true]); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/ServerImporterController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class ServerImporterController extends Controller {
    public function testConnection(): JsonResponse { return response()->json(['connected'=>false,'message'=>'Not available']); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/UploadFromUrlController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class UploadFromUrlController extends Controller {
    public function query(): JsonResponse { return response()->json(['valid'=>false,'message'=>'Not available']); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/WingsController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class WingsController extends Controller {
    public function checkStatus(): JsonResponse { return response()->json(['online'=>true,'message'=>'Node operational']); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/GithubController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class GithubController extends Controller {
    public function account(): JsonResponse { return response()->json(['connected'=>false]); }
    public function connectAccount(): JsonResponse { return response()->json(['success'=>false,'message'=>'Not configured']); }
    public function disconnectAccount(): JsonResponse { return response()->json(['success'=>true]); }
    public function repositories(): JsonResponse { return response()->json(['repositories'=>[]]); }
}
STUB
cat > app/Http/Controllers/Api/Client/AddonControllers/AdminDgenController.php << 'STUB'
<?php namespace Pterodactyl\Http\Controllers\Api\Client\AddonControllers; use Illuminate\Http\JsonResponse; use Illuminate\Routing\Controller;
class AdminDgenController extends Controller {
    public function billingCategories(): JsonResponse { return response()->json(['categories'=>[]]); }
    public function billingGames(): JsonResponse { return response()->json(['games'=>[]]); }
    public function billingPromocodes(): JsonResponse { return response()->json(['promocodes'=>[]]); }
    public function billingSubcategories(): JsonResponse { return response()->json(['subcategories'=>[]]); }
    public function members(): JsonResponse { return response()->json(['members'=>[]]); }
    public function roles(): JsonResponse { return response()->json(['roles'=>[]]); }
    public function globalStorageBackends(): JsonResponse { return response()->json(['backends'=>[]]); }
    public function testStorageBackend(): JsonResponse { return response()->json(['success'=>true]); }
}
STUB
# User model + AssetHashService patches
cat > /tmp/p1.php << 'P1'
<?php $f=$argv[1];$c=file_get_contents($f);if(strpos($c,'permissionRole')!==false){echo"ok\n";exit(0);}
$m="\n    public function permissionRole(){return \$this->hasOne(Setting::class,'id')->limit(0);}\n    public function hasAdminPermission(string \$p):bool{return(bool)\$this->root_admin;}\n";
$l=explode("\n",$c);for($i=count($l)-1;$i>=0;$i--){if(trim($l[$i])==='}'){array_splice($l,$i,0,$m);break;}}file_put_contents($f,implode("\n",$l));echo"User patched\n";
P1
cat > /tmp/p2.php << 'P2'
<?php $f=$argv[1];$c=file_get_contents($f);if(strpos($c,'preloads')!==false){echo"ok\n";exit(0);}
$m="\n    public function preloads():array{\$l=[];\$m=\$this->manifest();foreach([\"main.js\",\"main.css\"] as \$k){if(isset(\$m[\$k][\"src\"]))\$l[]=[\"src\"=>\$m[\$k][\"src\"]];}return \$l;}\n    public function authPreloads():array{return \$this->preloads();}\n";
$l=explode("\n",$c);for($i=count($l)-1;$i>=0;$i--){if(trim($l[$i])==='}'){array_splice($l,$i,0,$m);break;}}file_put_contents($f,implode("\n",$l));echo"Asset patched\n";
P2
php /tmp/p1.php app/Models/User.php; php /tmp/p2.php app/Services/Helpers/AssetHashService.php; rm -f /tmp/p1.php /tmp/p2.php

# Routes
cat > routes/addon-routes.php << 'ROUTES'
<?php
use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\Client\AvtixLicenseController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\ThemeController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\BillingController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\SubdomainController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\DiscordBotController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\DiscordVerificationController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\DdosAlertController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\NodeStatusController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\DgenStatsController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\ExportRawController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\ReverseProxyController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\FastDLController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\ServerSplitterController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\ServerTypeChangerController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\ServerWiperController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\StaffRequestController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\ServerImporterController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\UploadFromUrlController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\WingsController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\GithubController;
use Pterodactyl\Http\Controllers\Api\Client\AddonControllers\AdminDgenController;
use Pterodactyl\Http\Controllers\Api\Application\LicenseController;
Route::get('/api/public/license/verify',[AvtixLicenseController::class,'verify']);
Route::get('/api/public/license/status',[AvtixLicenseController::class,'status']);
Route::get('/api/public/license/clear',[AvtixLicenseController::class,'clear']);
Route::get('/api/public/license/clear-all-cache',[AvtixLicenseController::class,'clearAllCache']);
Route::get('/api/public/auth-addon-settings',[ThemeController::class,'authAddonSettings']);
Route::get('/api/public/node-status',[NodeStatusController::class,'index']);
Route::get('/api/public/pwa/manifest',[ThemeController::class,'pwaManifest']);
Route::get('/api/public/pwa/sw-config',[ThemeController::class,'pwaSwConfig']);
Route::get('/api/application/license/check',[LicenseController::class,'check']);
Route::post('/api/application/license/update',[LicenseController::class,'update']);
Route::get('/api/client/theme/avtix',[AvtixLicenseController::class,'status']);
Route::get('/api/client/theme/avtix/version',[AvtixLicenseController::class,'version']);
Route::post('/api/v2/vexythemes/license',[\Pterodactyl\Http\Controllers\Api\Client\VexyThemesLicenseController::class,'handle']);
Route::get('/vexythemes-license',function(){return view('vexythemes.license-gate');});
Route::post('/vexythemes-license',function(\Illuminate\Http\Request $r){$c=new \Pterodactyl\Http\Controllers\Api\Client\VexyThemesLicenseController();return $c->handle($r);});
Route::get('/api/client/theme/avtix/sso/info',[AvtixLicenseController::class,'ssoInfo']);
Route::post('/api/client/theme/avtix/sso/exchange',[AvtixLicenseController::class,'ssoExchange']);
Route::post('/api/client/theme/avtix/sso/disconnect',[AvtixLicenseController::class,'ssoDisconnect']);
Route::put('/api/client/theme/avtix/update',[AvtixLicenseController::class,'update']);
Route::post('/api/client/theme/avtix/notifications/broadcast',[AvtixLicenseController::class,'notifications']);
Route::get('/api/client/addons/defaults',[ThemeController::class,'defaults']);
Route::get('/api/client/addons',[ThemeController::class,'addons']);
Route::get('/api/client/addons/billing/balance',[BillingController::class,'balance']);
Route::get('/api/client/addons/billing/discount',[BillingController::class,'discount']);
Route::get('/api/client/addons/billing/services',[BillingController::class,'services']);
Route::post('/api/client/addons/billing/store/categories',[BillingController::class,'storeCategories']);
Route::post('/api/client/addons/billing/order/create',[BillingController::class,'orderCreate']);
Route::post('/api/client/addons/billing/top-up',[BillingController::class,'topUp']);
Route::post('/api/client/addons/billing/verify',[BillingController::class,'verify']);
Route::post('/api/client/addons/billing/promocodes/validate',[BillingController::class,'promoValidate']);
Route::get('/api/client/addons/billing/referral',[BillingController::class,'referral']);
Route::post('/api/client/addons/billing/referral/code',[BillingController::class,'referralCode']);
Route::post('/api/client/addons/billing/referral/withdraw',[BillingController::class,'referralWithdraw']);
Route::get('/api/client/addons/subdomain-manager/fetch-all-subdomains',[SubdomainController::class,'fetchAllSubdomains']);
Route::post('/api/client/addons/subdomain-manager/fetch-domains',[SubdomainController::class,'fetchDomains']);
Route::post('/api/client/addons/subdomain-manager/test-connection',[SubdomainController::class,'testConnection']);
Route::get('/api/client/addons/discord-bot/bot-status',[DiscordBotController::class,'botStatus']);
Route::get('/api/client/addons/discord-bot/stats',[DiscordBotController::class,'stats']);
Route::post('/api/client/addons/discord-bot/restart',[DiscordBotController::class,'restart']);
Route::post('/api/client/addons/discord-bot/sync',[DiscordBotController::class,'sync']);
Route::get('/api/client/discord-verification',[DiscordVerificationController::class,'check']);
Route::get('/api/client/discord-verification/account',[DiscordVerificationController::class,'accountCheck']);
Route::post('/api/client/discord-verification/refresh',[DiscordVerificationController::class,'refresh']);
Route::post('/api/client/discord-verification/account/refresh',[DiscordVerificationController::class,'accountRefresh']);
Route::get('/api/client/addons/ddos-alert/attacks',[DdosAlertController::class,'attacks']);
Route::get('/api/client/addons/ddos-alert/charts',[DdosAlertController::class,'charts']);
Route::get('/api/client/addons/ddos-alert/summary',[DdosAlertController::class,'summary']);
Route::post('/api/client/addons/ddos-alert/sync-now',[DdosAlertController::class,'syncNow']);
Route::get('/api/client/addons/node-status',[NodeStatusController::class,'index']);
Route::get('/api/client/addons/node-status/monitors',[NodeStatusController::class,'monitors']);
Route::post('/api/client/addons/node-status/monitors',[NodeStatusController::class,'createMonitor']);
Route::post('/api/client/addons/DGEN/server-stats',[DgenStatsController::class,'serverStats']);
Route::get('/api/client/addons/export-raw',[ExportRawController::class,'index']);
Route::get('/api/client/addons/reverse-proxy/search',[ReverseProxyController::class,'search']);
Route::get('/api/client/addons/reverse-proxy/whitelist',[ReverseProxyController::class,'whitelist']);
Route::post('/api/client/addons/reverse-proxy/whitelist',[ReverseProxyController::class,'addToWhitelist']);
Route::post('/api/client/addons/fastdl-nginx/setup',[FastDLController::class,'setup']);
Route::post('/api/client/addons/fastdl-nginx/remove',[FastDLController::class,'remove']);
Route::get('/api/client/addons/server-splitter/legacy-splits',[ServerSplitterController::class,'legacySplits']);
Route::get('/api/client/addons/server-splitter/search',[ServerSplitterController::class,'search']);
Route::get('/api/client/addons/server-splitter/users',[ServerSplitterController::class,'users']);
Route::get('/api/client/addons/server-splitter/whitelist',[ServerSplitterController::class,'whitelist']);
Route::post('/api/client/addons/server-splitter/hook',[ServerSplitterController::class,'hook']);
Route::post('/api/client/addons/server-splitter/legacy-splits/migrate',[ServerSplitterController::class,'migrateSplits']);
Route::post('/api/client/addons/server-splitter/whitelist',[ServerSplitterController::class,'addToWhitelist']);
Route::get('/api/client/addons/server-type-changer/whitelist',[ServerTypeChangerController::class,'whitelist']);
Route::get('/api/client/addons/server-type-changer/whitelist/search',[ServerTypeChangerController::class,'searchWhitelist']);
Route::post('/api/client/addons/server-type-changer/whitelist',[ServerTypeChangerController::class,'addToWhitelist']);
Route::get('/api/client/admin/addons/server-type-changer/all-nests-eggs',[ServerTypeChangerController::class,'allNestsEggs']);
Route::get('/api/client/addons/server-wiper',[ServerWiperController::class,'index']);
Route::post('/api/client/addons/server-wiper/wipe',[ServerWiperController::class,'wipe']);
Route::get('/api/client/addons/staff-request/my-servers',[StaffRequestController::class,'myServers']);
Route::get('/api/client/addons/staff-request/owner-requests',[StaffRequestController::class,'ownerRequests']);
Route::get('/api/client/addons/staff-request/requests',[StaffRequestController::class,'requests']);
Route::get('/api/client/addons/staff-request/requests/count',[StaffRequestController::class,'requestCount']);
Route::get('/api/client/addons/staff-request/servers',[StaffRequestController::class,'servers']);
Route::post('/api/client/addons/staff-request/requests',[StaffRequestController::class,'createRequest']);
Route::post('/api/client/addons/server-importer/test-connection',[ServerImporterController::class,'testConnection']);
Route::post('/api/client/addons/upload-from-url/query',[UploadFromUrlController::class,'query']);
Route::post('/api/client/addons/wings/check-status',[WingsController::class,'checkStatus']);
Route::get('/api/client/addons/github-source-control/account',[GithubController::class,'account']);
Route::post('/api/client/addons/github-source-control/account',[GithubController::class,'connectAccount']);
Route::delete('/api/client/addons/github-source-control/account',[GithubController::class,'disconnectAccount']);
Route::get('/api/client/addons/github-source-control/repositories',[GithubController::class,'repositories']);
Route::get('/admin/api/DGEN/billing/categories',[AdminDgenController::class,'billingCategories']);
Route::get('/admin/api/DGEN/billing/games',[AdminDgenController::class,'billingGames']);
Route::get('/admin/api/DGEN/billing/promocodes',[AdminDgenController::class,'billingPromocodes']);
Route::get('/admin/api/DGEN/billing/subcategories',[AdminDgenController::class,'billingSubcategories']);
Route::get('/admin/api/DGEN/members',[AdminDgenController::class,'members']);
Route::get('/admin/api/DGEN/roles',[AdminDgenController::class,'roles']);
Route::get('/admin/api/global-storage-backends',[AdminDgenController::class,'globalStorageBackends']);
Route::post('/admin/api/global-storage-backends/test',[AdminDgenController::class,'testStorageBackend']);
Route::post('/admin/api/DGEN/billing/categories',[AdminDgenController::class,'billingCategories']);
Route::post('/admin/api/DGEN/billing/games',[AdminDgenController::class,'billingGames']);
Route::post('/admin/api/DGEN/billing/promocodes',[AdminDgenController::class,'billingPromocodes']);
Route::post('/admin/api/DGEN/billing/subcategories',[AdminDgenController::class,'billingSubcategories']);
Route::post('/admin/api/DGEN/roles',[AdminDgenController::class,'roles']);
Route::post('/admin/api/global-storage-backends',[AdminDgenController::class,'globalStorageBackends']);
ROUTES

# Patch base.php to include addon routes
cat > /tmp/patch_routes.php << 'PR'
<?php
$f=file_get_contents('routes/base.php');
$imp="require __DIR__ . '/addon-routes.php';";
$catch="Route::get('/{react}'";
$pos=strpos($f,$catch);
if($pos!==false){$f=substr_replace($f,$imp."\n\n".$catch,$pos,strlen($catch));}
else{$f.="\n".$imp."\n";}
file_put_contents('routes/base.php',$f);
echo "Routes patched\n";
PR
php /tmp/patch_routes.php; rm -f /tmp/patch_routes.php

# Seed settings
php artisan tinker --execute="
if(!\Illuminate\Support\Facades\Schema::hasTable('settings')){\Illuminate\Support\Facades\Schema::create('settings',function(\$t){\$t->increments('id');\$t->string('key')->unique();\$t->text('value')->nullable();\$t->timestamps();});}
\$t=['site'=>['meta'=>['title'=>'Avtix Game Panel','description'=>'Powered by Avtix Game Panel','color'=>'#6366f1','faviconUrl'=>'/favicons/favicon-32x32.png','image'=>null],'auth'=>['experience'=>['disableBackgroundImage'=>true],'background'=>['custom'=>['enabled'=>false]]],'background'=>['custom'=>['enabled'=>false]]],'variables'=>['--hyper-primary'=>'#6366f1','--hyper-background'=>'#0c0a09','--hyper-text-primary'=>'#ffffff','--hyper-text-secondary'=>'#a1a1aa','--hyper-border'=>'#27272a','--hyper-surface'=>'#18181b'],'enforce'=>false];
\$a=['addons'=>['pwa'=>['enabled'=>false],'theme-settings'=>['userPermissions'=>['colors'=>true,'background'=>true,'notifications'=>true,'privacy'=>true],'defaults'=>['privacy'=>['blur'=>false],'performance'=>['blurEnabled'=>false,'blurAmount'=>0,'radiusAmount'=>-1]]]]];
\Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(['key'=>'settings::app:theme:hyperv2'],['value'=>json_encode(\$t)]);
\Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(['key'=>'settings::app:addons:hyperv2'],['value'=>json_encode(\$a)]);
echo 'Seeded';
" 2>/dev/null || true

echo -e "${GREEN}  Patches applied!${NC}"
echo -e "${CYAN}[12/12]${NC} Finalizing..."
cd "$PANEL_DIR"
php artisan migrate --force 2>/dev/null || true
# Create admin user via PHP script (avoid tinker escaping issues)
cat > /tmp/make_admin.php << 'ADMINPHP'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Pterodactyl\Models\User;
if (!User::where('username', getenv('ADMIN_USER'))->exists()) {
    $uuid = bin2hex(random_bytes(16));
    $uuid = substr($uuid,0,8).'-'.substr($uuid,8,4).'-'.substr($uuid,12,4).'-'.substr($uuid,16,4).'-'.substr($uuid,20,12);
    $user = new User();
    $user->uuid = $uuid;
    $user->email = getenv('ADMIN_EMAIL');
    $user->username = getenv('ADMIN_USER');
    $user->name_first = getenv('ADMIN_USER');
    $user->name_last = 'User';
    $user->password = bcrypt(getenv('ADMIN_PASS'));
    $user->root_admin = 1;
    $user->use_totp = 0;
    $user->gravatar = 1;
    $user->language = 'en';
    $user->created_at = now();
    $user->updated_at = now();
    $user->save();
    echo "Admin created: " . getenv('ADMIN_USER') . "\n";
} else {
    echo "Admin already exists\n";
}
ADMINPHP
ADMIN_EMAIL="$ADMIN_EMAIL" ADMIN_USER="$ADMIN_USER" ADMIN_PASS="$ADMIN_PASS" php /tmp/make_admin.php 2>/dev/null || true
rm -f /tmp/make_admin.php
# Nginx
FPM_SOCK="/run/php/php8.4-fpm.sock"
cat > /etc/nginx/sites-available/pterodactyl.conf << NGINX
server {
    listen 80; server_name ${DOMAIN}; root /var/www/pterodactyl/public; index index.html index.htm index.php;
    charset utf-8; location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt { access_log off; log_not_found off; }
    access_log off; error_log /var/log/nginx/pterodactyl.app-error.log error;
    client_max_body_size 100m; client_body_timeout 120s;
    location ~ \.php\$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)\$; fastcgi_pass unix:${FPM_SOCK}; fastcgi_index index.php;
        include fastcgi_params; fastcgi_param PHP_VALUE "upload_max_filesize = 100M \n post_max_size=100M";
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name; fastcgi_param HTTP_PROXY "";
        fastcgi_intercept_errors off; fastcgi_buffer_size 16k; fastcgi_buffers 4 16k;
        fastcgi_connect_timeout 300; fastcgi_send_timeout 300; fastcgi_read_timeout 300;
    }
    location ~ /\.ht { deny all; }
    listen [::]:443 ssl; listen 443 ssl;
    ssl_certificate /etc/letsencrypt/live/${DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf; ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
}
server {
    if (\$host = ${DOMAIN}) { return 301 https://\$host\$request_uri; }
    listen 80; listen [::]:80; server_name ${DOMAIN};
}
NGINX
ln -sf /etc/nginx/sites-available/pterodactyl.conf /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
apt-get install -y certbot python3-certbot-nginx 2>/dev/null || true
certbot certonly --standalone -d "$DOMAIN" --non-interactive --agree-tos --email "$ADMIN_EMAIL" 2>/dev/null || \
certbot certonly --webroot -w "$PANEL_DIR/public" -d "$DOMAIN" --non-interactive --agree-tos --email "$ADMIN_EMAIL" 2>/dev/null || \
echo -e "${YELLOW}  SSL setup failed. Configure later.${NC}"
nginx -t 2>/dev/null && systemctl restart nginx || true
systemctl restart php8.4-fpm 2>/dev/null || true
# Supervisor
mkdir -p /var/log/pterodactyl
cat > /etc/supervisor/conf.d/pterodactyl-worker.conf << EOF
[program:pterodactyl-worker]
command=php /var/www/pterodactyl/artisan queue:work --queue=high,standard,default,low --sleep=3 --tries=3 --timeout=90 --memory=256
directory=/var/www/pterodactyl user=www-data autostart=true autorestart=true
stderr_logfile=/var/log/pterodactyl/worker.err.log stdout_logfile=/var/log/pterodactyl/worker.out.log
EOF
cat > /etc/supervisor/conf.d/pterodactyl-scheduler.conf << EOF
[program:pterodactyl-scheduler]
command=php /var/www/pterodactyl/artisan schedule:work
directory=/var/www/pterodactyl user=www-data autostart=true autorestart=true
stderr_logfile=/var/log/pterodactyl/scheduler.err.log stdout_logfile=/dev/null
EOF
chown www-data:www-data /var/log/pterodactyl
supervisorctl reread 2>/dev/null; supervisorctl update 2>/dev/null
supervisorctl start pterodactyl-worker 2>/dev/null || true
supervisorctl start pterodactyl-scheduler 2>/dev/null || true
# Cache (skip view:cache to avoid stale compiled view issues)
for c in config:clear view:clear cache:clear config:cache; do php artisan $c 2>/dev/null || true; done
# Fix directory permissions (critical for www-data to traverse views)
find resources -type d -exec chmod 755 {} +
find storage -type d -exec chmod 755 {} +
find bootstrap/cache -type d -exec chmod 755 {} +
find resources -type f -exec chmod 644 {} +
chown -R www-data:www-data "$PANEL_DIR"
# Credentials
cat > "$SCRIPT_DIR/credentials.txt" << CREDS
============================================
  VEXYTHEMES PANEL - CREDENTIALS
  Premium Theme by VexyThemes
============================================
Panel URL:    $PANEL_URL
Admin User:   $ADMIN_USER
Admin Email:  $ADMIN_EMAIL
Admin Pass:   $ADMIN_PASS
DB Name:      $DB_NAME
DB User:      $DB_USER
DB Pass:      $DB_PASS
============================================
  LICENSE: Add your key at Panel → Settings
============================================
CREDS
chmod 600 "$SCRIPT_DIR/credentials.txt"
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}   INSTALLATION COMPLETE!${NC}"
echo -e "${GREEN}   VexyThemes Panel — Premium Theme${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo -e "  Panel URL:    ${CYAN}$PANEL_URL${NC}"
echo -e "  Admin User:   ${CYAN}$ADMIN_USER${NC}"
echo -e "  Admin Email:  ${CYAN}$ADMIN_EMAIL${NC}"
echo -e "  Admin Pass:   ${CYAN}$ADMIN_PASS${NC}"
echo ""
echo -e "  ${YELLOW}NEXT STEP: Add your license key${NC}"
echo -e "  Login → Settings → VexyThemes License → Add Key"
echo ""
echo -e "  ${YELLOW}Commands:${NC}"
echo -e "  sudo bash install.sh repair    - Fix issues"
echo -e "  sudo bash install.sh uninstall - Remove panel"
echo -e "  sudo bash install.sh update    - Coming soon"
echo ""
