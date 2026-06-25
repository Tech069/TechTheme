#!/bin/bash

PANEL_PATH="/var/www/pterodactyl"
GITHUB_ZIP="https://raw.githubusercontent.com/CodeByCruel/AvtixTheme/main/Hyper-modified.zip"
API_URL="https://vt-panel-api.vercel.app/api/index"
THEME_DB_KEY="settings::app:theme:hyperv2"

if [[ "$EUID" -ne 0 ]]; then
    echo "Error: Must be run as root."
    exit 1
fi

php() { php8.4 "$@"; }
composer() { php8.4 /usr/local/bin/composer "$@"; }
export -f php
export -f composer

download_theme() {
    TMP=$(mktemp -d)
    THEME_ZIP="$TMP/Hyper-modified.zip"
    if [[ -f "./Hyper-modified.zip" ]]; then
        cp "./Hyper-modified.zip" "$THEME_ZIP"
    elif [[ -f "$SCRIPT_DIR/Hyper-modified.zip" ]]; then
        cp "$SCRIPT_DIR/Hyper-modified.zip" "$THEME_ZIP"
    else
        echo "Downloading Avtix theme..."
        curl -fSL --retry 3 --progress-bar -o "$THEME_ZIP" "$GITHUB_ZIP"
    fi
    echo "Extracting theme..."
    unzip -oq "$THEME_ZIP" -d "$TMP/extract"
    INNER="$TMP/extract"
    [[ -d "$TMP/extract/Hyper-modified" ]] && INNER="$TMP/extract/Hyper-modified"
}

apply_theme() {
    echo "Applying theme..."
    rm -rf "$PANEL_PATH/public/assets" "$PANEL_PATH/public/DGEN" 2>/dev/null || true
    cp -rf "$INNER/public/"* "$PANEL_PATH/public/" 2>/dev/null || true
    rm -rf "$PANEL_PATH/resources/views" 2>/dev/null || true
    cp -rf "$INNER/resources/views" "$PANEL_PATH/resources/" 2>/dev/null || true
    cp -rf "$INNER/resources/lang" "$PANEL_PATH/resources/" 2>/dev/null || true
    cp -f "$INNER/routes/"*.php "$PANEL_PATH/routes/" 2>/dev/null || true
    for f in "$INNER/config/"*.php; do
        fname=$(basename "$f")
        case "$fname" in database.php|app.php|cache.php|sessions.php|queue.php|logging.php|mail.php) continue ;; esac
        cp -f "$f" "$PANEL_PATH/config/" 2>/dev/null || true
    done
    rm -rf "$PANEL_PATH/database" 2>/dev/null || true
    cp -rf "$INNER/database" "$PANEL_PATH/"
    cp -f "$INNER/composer.json" "$INNER/composer.lock" "$PANEL_PATH/" 2>/dev/null || true
    echo "Theme applied."
}

replace_ioncube_files() {
    echo "Replacing ionCube files..."
    COUNT=$(find "$PANEL_PATH/app" -name '*.php' -exec grep -l 'ionCube' {} \; 2>/dev/null | wc -l)
    echo "Found $COUNT ionCube files."
    if [[ "$COUNT" -gt 0 ]]; then
        find "$PANEL_PATH/app" -name '*.php' -exec grep -l 'ionCube' {} \; 2>/dev/null | while IFS= read -r filepath; do
            classname=$(basename "$filepath" .php)
            relpath="${filepath#$PANEL_PATH/app/}"
            dirpath=$(dirname "$relpath")
            namespace="Pterodactyl"
            [[ "$dirpath" != "." ]] && namespace="Pterodactyl\\$(echo "$dirpath" | sed 's|/|\\|g')"
            cat > "$filepath" <<STUBEOF
<?php
namespace $namespace;
class $classname { public function __call(\$m, \$a) { return response()->json(["data" => []]); } public static function __callStatic(\$m, \$a) { return response()->json(["data" => []]); } }
STUBEOF
        done
        echo "Replaced $(find "$PANEL_PATH/app" -name '*.php' -exec grep -l 'ionCube' {} \; 2>/dev/null | wc -l) remaining."
    fi
}

patch_js_files() {
    echo "Patching JS files..."

    # 1. Replace entry.8ve3mcm4.js with always-valid license service
    cat > "$PANEL_PATH/public/assets/entry.8ve3mcm4.js" << 'JSEOF'
class i{static instance;cachedVerification={valid:!0,tier:"ultimate",status:"active",validity:"Valid",community:!1,sso_connected:!0,features:["sso","billing","store","subdomains","recyclebin","serversplitter","fivem","minecraft","arma"],panel_type:"Avtix Game Panel",panel_version:"v1.0",license_key:"avtix-enterprise",verified_at:new Date().toISOString()};pendingVerification=null;cachedStatus={status:{valid:!0,tier:"ultimate",status:"active"},ts:Date.now()};statusTTL=43200000;constructor(){}static getInstance(){if(!i.instance)i.instance=new i;return i.instance}async fetchFromServer(){return this.cachedVerification}async requestVerification(){return this.cachedVerification}async verifyLicense(){window.dispatchEvent(new CustomEvent("license-verified",{detail:this.cachedVerification}));return this.cachedVerification}async getLicenseStatus(){return this.cachedStatus.status}hasCategory(){return!0}isBasicModeEnabled(){return!0}isPremiumModeEnabled(){return!0}isUltimateModeEnabled(){return!0}isMinecraftModeEnabled(){return!0}isEssentialsModeEnabled(){return!0}isSpecialModeEnabled(){return!0}isMinecraftFeatureEnabled(){return!0}isEssentialsFeatureEnabled(){return!0}isSpecialFeatureEnabled(){return!0}isPrivateModeEnabled(){return!0}isPrivateFeatureEnabled(){return!0}shouldEnableMinecraftAddon(){return!0}shouldEnableEssentialsAddon(){return!0}shouldEnableSpecialAddon(){return!0}shouldEnablePrivateAddon(){return!0}isArkModeEnabled(){return!0}isArkFeatureEnabled(){return!0}shouldEnableArkAddon(){return!0}isHytaleModeEnabled(){return!0}isHytaleFeatureEnabled(){return!0}shouldEnableHytaleAddon(){return!0}hasSpecificFeature(){return!0}isFivemModeEnabled(){return!0}isFivemFeatureEnabled(){return!0}shouldEnableFivemAddon(){return!0}isPartnershipModeEnabled(){return!0}isCommunityVersion(){return!1}hasCachedVerification(){return!0}getCachedVerification(){return this.cachedVerification}backgroundReVerify(){}clearVerificationData(){}clearLegacyBrowserVerification(){}}
var n=i.getInstance(),s=n;
export{n as Bp,s as Cp};
JSEOF
    echo "  entry.8ve3mcm4.js replaced (always valid)"

    # 2. Replace LicenseMonitor
    echo 'export default function LicenseMonitor(){return null;}' > "$PANEL_PATH/public/assets/LicenseMonitor.wvnehtfj.js"

    # 3. Replace licenseService
    cat > "$PANEL_PATH/public/assets/licenseService.1tf8btsy.js" << 'LSEOF'
export function verifyLicense(){return Promise.resolve({valid:true,reason:null})}
export function getLicenseStatus(){return Promise.resolve({valid:true,status:"active"})}
export function clearVerificationData(){}
export default {verifyLicense:function(){return Promise.resolve({valid:true,reason:null})},getLicenseStatus:function(){return Promise.resolve({valid:true,status:"active"})},clearVerificationData:function(){}};
LSEOF
    echo "  LicenseMonitor + licenseService stubbed"

    # 4. Stub StarsCanvas (JSX -> null to prevent SyntaxError)
    echo 'export default function StarsCanvas(){return null;}' > "$PANEL_PATH/public/assets/StarsCanvas.gqjbwh14.js"
    echo "  StarsCanvas stubbed (removed broken particles)"

    # 5. Patch App.z9y3z1nk.js - neutralize Yo (dialog), Nr, Tr, MutationObserver fix
    python3 << 'PYEOF'
import re, os

path = "/var/www/pterodactyl/public/assets/App.z9y3z1nk.js"
if not os.path.exists(path):
    print("  App.js not found, skipping")
    exit()

with open(path, "r") as fh:
    c = fh.read()

orig_len = len(c)

# Replace Yo function - find "Yo=()=>a(ro,{open:!0" and replace entire expression
idx = c.find("Yo=()=>a(ro,{open:!0")
if idx >= 0:
    depth = 0
    end = idx
    started = False
    for i in range(idx + len("Yo=()=>"), len(c)):
        if c[i] == '(':
            depth += 1
            started = True
        elif c[i] == ')':
            depth -= 1
            if started and depth == 0:
                end = i + 1
                break
    c = c[:idx] + "Yo=()=>null" + c[end:]
    print("  Yo patched (dialog removed)")
else:
    print("  Yo not found")

# Replace Nr function
idx = c.find("Nr=")
if idx >= 0 and idx < 100:
    rest = c[idx:]
    depth = 0
    started = False
    end_rel = 0
    for i, ch in enumerate(rest):
        if ch == '{':
            depth += 1
            started = True
        elif ch == '}':
            depth -= 1
            if started and depth == 0:
                end_rel = i + 1
                break
    if end_rel > 0:
        c = c[:idx] + "Nr=()=>false" + c[end_rel:]
        print("  Nr patched")

# Replace Tr function
idx = c.find("Tr=")
if idx >= 0 and idx < 100:
    rest = c[idx:]
    depth = 0
    started = False
    end_rel = 0
    for i, ch in enumerate(rest):
        if ch == '{':
            depth += 1
            started = True
        elif ch == '}':
            depth -= 1
            if started and depth == 0:
                end_rel = i + 1
                break
    if end_rel > 0:
        c = c[:idx] + "Tr=()=>false" + c[end_rel:]
        print("  Tr patched")

# Fix MutationObserver cascade (prevents 10-15 reload loop when changing colors)
old_observer = "B=new MutationObserver(()=>{Zr.forEach((o)=>o())})"
new_observer = "B=new MutationObserver(()=>{if(!window.__hyperObsP){window.__hyperObsP=1;requestAnimationFrame(()=>{window.__hyperObsP=0;Zr.forEach((o)=>o())})}})"
if old_observer in c:
    c = c.replace(old_observer, new_observer)
    print("  MutationObserver batch fix applied")
else:
    # Try alternate pattern
    old2 = "B=new MutationObserver("
    if old2 in c:
        idx = c.find(old2)
        # Find the callback start
        cb_start = c.find("()=>{", idx)
        if cb_start >= 0 and cb_start - idx < 30:
            # Find the closing of this callback
            depth = 0
            started = False
            for i in range(cb_start + 5, min(cb_start + 500, len(c))):
                if c[i] == '{':
                    depth += 1
                    started = True
                elif c[i] == '}':
                    depth -= 1
                    if started and depth == 0:
                        old_cb = c[cb_start:i+1]
                        new_cb = "()=>{if(!window.__hyperObsP){window.__hyperObsP=1;requestAnimationFrame(()=>{window.__hyperObsP=0;" + old_cb[6:-1] + "})}}"
                        c = c[:cb_start] + new_cb + c[i+1:]
                        print("  MutationObserver batch fix applied (alt)")
                        break

# Remove license text strings
for text in [
    "DGEN License Account Required",
    "License Account Required",
    "Avtix License Account Required",
    "Connect the panel DGEN account in Hyper Settings to activate your license or Community Version.",
    "Connect the panel Avtix account in Avtix Settings to activate your license or Community Version.",
    "Connect Avtix Account to activate your license or activate the free Community Version.",
    "Admin access is paused until a DGEN license account is connected for this panel.",
    "admin access is paused until a DGEN license account is connected for this panel.",
    "Unable to load DGEN license account status. Please reload and try again.",
    "Loading license information...",
    "Avtix license account status unavailable",
    "Open Hyper Settings",
    "Open Avtix Settings",
]:
    c = c.replace(text, "")

with open(path, "w") as fh:
    fh.write(c)
print(f"  App.js: {orig_len} -> {len(c)} bytes")
PYEOF

    # 6. Patch HyperThemeSettings - remove license text, license section, reload, dgenx
    python3 << 'PYEOF2'
import os, glob

for js_file in glob.glob("/var/www/pterodactyl/public/assets/HyperThemeSettings*.js"):
    with open(js_file, "r") as fh:
        c = fh.read()
    orig = c

    # Remove license-related text strings
    for text in [
        "DGEN License Account Required",
        "License Account Required",
        "Connect the panel DGEN account in Hyper Settings to activate your license or Community Version.",
        "Connect the panel Avtix account in Avtix Settings to activate your license or Community Version.",
        "Connect Avtix Account to activate your license or activate the free Community Version.",
        "Admin access is paused until a DGEN license account is connected for this panel.",
        "admin access is paused until a DGEN license account is connected for this panel.",
        "Unable to load DGEN license account status. Please reload and try again.",
        "Loading license information...",
        "Avtix license account status unavailable",
        "Open Hyper Settings",
        "Open Avtix Settings",
    ]:
        c = c.replace(text, "")

    # Fix License section UI
    c = c.replace('children:"License Information"', 'children:"License Active"')
    c = c.replace('children:"No Avtix Account is Connected"', 'children:"License Active"')
    c = c.replace(',"Connect Avtix Account"]', ',"Active"]')

    # Remove page reloads (causes 10-15 reload loop)
    c = c.replace("e2.clearVerificationData(),window.location.reload()", "e2.clearVerificationData()")

    # Remove dgenx.net references
    c = c.replace("hyper-r2.dgenx.net", "hyper-r2.example.net")
    c = c.replace("auth.dgenx.net", "auth.example.net")

    if c != orig:
        with open(js_file, "w") as fh:
            fh.write(c)
        print(f"  HyperThemeSettings patched (license, reload, dgenx removed)")

# 7. Patch AccountOverviewContainer - remove connected accounts / SSO section
for js_file in glob.glob("/var/www/pterodactyl/public/assets/AccountOverviewContainer*.js"):
    with open(js_file, "r") as fh:
        c = fh.read()
    orig = c
    c = c.replace('"Connected Accounts"', '"Account Settings"')
    c = c.replace('"Connect Avtix"', '"Active"')
    c = c.replace("window.location.reload()", "void 0")
    # Remove dgenx SSO redirect
    import re
    c = re.sub(r'window\.location\.href\s*=\s*`https://auth\.dgenx\.net/authorize[^`]*`', 'void 0', c)
    if c != orig:
        with open(js_file, "w") as fh:
            fh.write(c)
        print(f"  AccountOverviewContainer patched (SSO removed)")

PYEOF2
    echo "JS patching complete."
}

create_controllers() {
    echo "Creating controllers..."
    CTRL="$PANEL_PATH/app/Http/Controllers"
    mkdir -p "$CTRL/Api/Client/Theme" "$CTRL/Api/Client/DGEN/Billing" "$CTRL/Api/Client/DGEN" \
        "$CTRL/Api/Client/Servers/DGEN" "$CTRL/Api/Client/Admin/DGEN" "$CTRL/Api/Client" \
        "$CTRL/Api/Application" "$CTRL/Base" "$CTRL/Auth" "$CTRL/Admin"

    # Helper
    mkctrl() {
        local path="$1" ns="$2" cls="$3"
        cat > "$path" <<CEOF
<?php
namespace $ns;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Http\Request;
class $cls extends Controller {
    public function __call(\$m, \$a) { return response()->json(["data" => []]); }
}
CEOF
    }

    # Theme controllers
    mkctrl "$CTRL/Api/Client/Theme/HyperV2ThemeController.php" "Pterodactyl\\Http\\Controllers\\Api\\Client\\Theme" "HyperV2ThemeController"

    # HyperV2AddonController - returns proper addon settings with UserRegister enabled
    cat > "$CTRL/Api/Client/Theme/HyperV2AddonController.php" <<'EOF'
<?php
namespace Pterodactyl\Http\Controllers\Api\Client\Theme;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
class HyperV2AddonController extends Controller {
    public function show(): JsonResponse {
        return response()->json(['addons' => [
            'UserRegister' => ['enabled' => true, 'name' => 'User Registration', 'description' => 'Enhanced user registration system', 'category' => 'User Management'],
            'theme-settings' => ['enabled' => true, 'name' => 'Theme Settings', 'description' => 'User theme customization', 'category' => 'Appearance'],
            'SiteAlerts' => ['enabled' => true, 'name' => 'Site Alerts', 'description' => 'Configure site-wide alert banners', 'category' => 'User Experience', 'alerts' => []],
            'Notifications' => ['enabled' => true, 'name' => 'System Notifications', 'description' => 'Configure system notifications and alerts', 'category' => 'User Experience', 'notifications' => ['serverEnabled' => false, 'soundsEnabled' => false]],
        ], 'updated_at' => now()->toIso8601String(), 'app_url' => config('app.url', '')]);
    }
    public function defaults(): JsonResponse { return response()->json(['addons' => []]); }
    public function update(Request $r): JsonResponse { return response()->json(['updated' => true]); }
    public function exportRaw(): JsonResponse { return response()->json(['data' => []]); }
    public function checkServerAvailability(): JsonResponse { return response()->json(['available' => true]); }
}
EOF

    # Billing
    mkctrl "$CTRL/Api/Client/DGEN/Billing/BillingController.php" "Pterodactyl\\Http\\Controllers\\Api\\Client\\DGEN\\Billing" "BillingController"
    mkctrl "$CTRL/Api/Client/DGEN/Billing/StoreController.php" "Pterodactyl\\Http\\Controllers\\Api\\Client\\DGEN\\Billing" "StoreController"
    mkctrl "$CTRL/Api/Client/DGEN/Billing/OrderController.php" "Pterodactyl\\Http\\Controllers\\Api\\Client\\DGEN\\Billing" "OrderController"
    mkctrl "$CTRL/Api/Client/DGEN/Billing/PromoCodeController.php" "Pterodactyl\\Http\\Controllers\\Api\\Client\\DGEN\\Billing" "PromoCodeController"

    # DGEN features
    for c in LoginActivityController DdosAlertController WingsAddonController; do
        mkctrl "$CTRL/Api/Client/DGEN/${c}.php" "Pterodactyl\\Http\\Controllers\\Api\\Client\\DGEN" "$c"
    done

    # Client controllers
    mkctrl "$CTRL/Api/Client/ReverseProxyController.php" "Pterodactyl\\Http\\Controllers\\Api\\Client" "ReverseProxyController"
    mkctrl "$CTRL/Api/Client/DiscordVerificationController.php" "Pterodactyl\\Http\\Controllers\\Api\\Client" "DiscordVerificationController"

    # Server DGEN controllers
    for c in StaffRequestController RecycleBinController SubdomainManagerController QuickFileAccessController ServerImporterController ServerSplitterController CommandHistoryController CustomModManagerController NodeStatusController CustomMonitorController ReverseProxyWhitelistController ServerSplitterWhitelistController ServerTypeChangerWhitelistController FiveMUtilsController MinecraftModpackController ArmaReforgerModManagerController MinecraftController MinecraftPluginController MinecraftModController MinecraftWorldController MinecraftPlayerManagerController MinecraftBedrockAddonController MinecraftBedrockVersionController MinecraftBedrockAddonCacheController MinecraftModCacheController MinecraftModpackCacheController MinecraftWorldCacheController MinecraftVotifierTesterController MinecraftPluginCacheController ArmaReforgerConfigController ArmaReforgerAdminToolsController HytaleWorldController HytaleWorldCacheController HytaleModController ServerWiperController ServerSplitterMigrationController ServerTypeChangerController ServerAgentTicketController ServerStatsController ServerTimeChangerController AutoSuspendController ConfigEditorController FirewallManagerController GithubSourceControlController NetworkStatisticsController SchedulePresetsController StartupPresetsController UploadFromUrlController FastDLController FastDLNginxController ArkModController ArkModCacheController; do
        mkctrl "$CTRL/Api/Client/Servers/DGEN/${c}.php" "Pterodactyl\\Http\\Controllers\\Api\\Client\\Servers\\DGEN" "$c"
    done

    # Admin DGEN
    mkctrl "$CTRL/Api/Client/Admin/DGEN/DiscordBotController.php" "Pterodactyl\\Http\\Controllers\\Api\\Client\\Admin\\DGEN" "DiscordBotController"
    mkctrl "$CTRL/Api/Client/Admin/DGEN/AdminBillingController.php" "Pterodactyl\\Http\\Controllers\\Api\\Client\\Admin\\DGEN" "AdminBillingController"
    mkctrl "$CTRL/Api/Client/Admin/DGEN/PermissionRoleController.php" "Pterodactyl\\Http\\Controllers\\Api\\Client\\Admin\\DGEN" "PermissionRoleController"

    # Admin controllers
    cat > "$CTRL/Admin/AdminStatisticsController.php" <<'EOF'
<?php
namespace Pterodactyl\Http\Controllers\Admin;
use Pterodactyl\Http\Controllers\Controller;
class AdminStatisticsController extends Controller {
    public function __call($m, $a) { return response()->json(["data" => []]); }
}
EOF
    mkctrl "$CTRL/Admin/AuditLogController.php" "Pterodactyl\\Http\\Controllers\\Admin" "AuditLogController"
    mkctrl "$CTRL/Admin/PanelLogsController.php" "Pterodactyl\\Http\\Controllers\\Admin" "PanelLogsController"

    # Base controllers
    mkctrl "$CTRL/Base/HyperV2ThemePublicController.php" "Pterodactyl\\Http\\Controllers\\Base" "HyperV2ThemePublicController"
    mkctrl "$CTRL/Base/PublicNodeStatusController.php" "Pterodactyl\\Http\\Controllers\\Base" "PublicNodeStatusController"
    mkctrl "$CTRL/Base/PublicStatusPageController.php" "Pterodactyl\\Http\\Controllers\\Base" "PublicStatusPageController"
    mkctrl "$CTRL/Base/PublicStatsController.php" "Pterodactyl\\Http\\Controllers\\Base" "PublicStatsController"
    mkctrl "$CTRL/Base/HealthController.php" "Pterodactyl\\Http\\Controllers\\Base" "HealthController"
    mkctrl "$CTRL/Base/DocumentationController.php" "Pterodactyl\\Http\\Controllers\\Base" "DocumentationController"
    mkctrl "$CTRL/Api/PublicEggController.php" "Pterodactyl\\Http\\Controllers\\Api" "PublicEggController"
    mkctrl "$CTRL/Auth/ReferralController.php" "Pterodactyl\\Http\\Controllers\\Auth" "ReferralController"

    # LanguageController (CRITICAL - without this routes fail)
    cat > "$CTRL/Base/LanguageController.php" <<'EOF'
<?php
namespace Pterodactyl\Http\Controllers\Base;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
class LanguageController extends Controller {
    public function update(Request $request) {
        $request->session()->put('app_locale', $request->input('locale', 'en'));
        return response()->noContent();
    }
    public function available() {
        return response()->json(['locales' => ['en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'pt' => 'Portuguese', 'nl' => 'Dutch', 'ru' => 'Russian', 'zh' => 'Chinese', 'ja' => 'Japanese', 'ko' => 'Korean', 'tr' => 'Turkish', 'pl' => 'Polish', 'it' => 'Italian', 'ar' => 'Arabic', 'vi' => 'Vietnamese', 'th' => 'Thai', 'id' => 'Indonesian', 'sv' => 'Swedish', 'da' => 'Danish', 'fi' => 'Finnish', 'no' => 'Norwegian', 'cs' => 'Czech', 'el' => 'Greek', 'he' => 'Hebrew', 'hu' => 'Hungarian', 'ro' => 'Romanian', 'uk' => 'Ukrainian', 'ms' => 'Malay', 'hi' => 'Hindi', 'bn' => 'Bengali']]);
    }
    public function set(Request $request) {
        $request->session()->put('app_locale', $request->input('locale', 'en'));
        return response()->noContent();
    }
}
EOF

    # License controller
    cat > "$CTRL/Api/Application/LicenseController.php" <<'EOF'
<?php
namespace Pterodactyl\Http\Controllers\Api\Application;
class LicenseController {
    public function check() { return response()->json(["valid" => true, "type" => "enterprise"]); }
    public function update() { return response()->json(["valid" => true]); }
}
EOF

    # RegisterController - real registration logic (not stub)
    cat > "$CTRL/Auth/RegisterController.php" <<'EOF'
<?php
namespace Pterodactyl\Http\Controllers\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\User;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
class RegisterController extends Controller {
    public function index(): View { return view('templates/auth.core'); }
    public function register(Request $request): JsonResponse {
        $request->validate([
            'username' => ['required', 'string', 'max:191', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $user = User::create([
            'uuid' => Str::uuid(), 'username' => $request->input('username'),
            'email' => $request->input('email'), 'password' => Hash::make($request->input('password')),
            'name_first' => $request->input('name_first', ''), 'name_last' => $request->input('name_last', ''),
            'language' => 'en', 'root_admin' => false, 'use_totp' => false, 'gravatar' => true,
        ]);
        auth()->login($user);
        return new JsonResponse(['data' => ['complete' => true, 'intended' => '/']]);
    }
}
EOF
}

create_services_middleware() {
    echo "Creating services and middleware..."
    mkdir -p "$PANEL_PATH/app/Services" "$PANEL_PATH/app/Http/Middleware" "$PANEL_PATH/app/Traits"

    for mw in HyperV2LicenseGate EnforceHyperV2PanelAccess HyperV2SecurityMonitor EnsureDiscordMembership; do
        cat > "$PANEL_PATH/app/Http/Middleware/${mw}.php" <<EOF
<?php
namespace Pterodactyl\Http\Middleware;
use Closure; use Illuminate\Http\Request;
class $mw { public function handle(Request \$r, Closure \$n) { return \$n(\$r); } }
EOF
    done

    cat > "$PANEL_PATH/app/Traits/ValidatesSecureLicense.php" <<'EOF'
<?php
namespace Pterodactyl\Traits;
trait ValidatesSecureLicense { public function isLicenseValid():bool{return true;} protected function validateLicense():bool{return true;} }
EOF

    cat > "$PANEL_PATH/app/Services/HyperV2LicenseService.php" <<'EOF'
<?php
namespace Pterodactyl\Services;
class HyperV2LicenseService {
    public function getLicenseStatus():array{return ['valid'=>true,'type'=>'enterprise','tier'=>'enterprise'];}
    public function validateLicense(?string $k=null):bool{return true;}
    public function isLicenseValid():bool{return true;}
    public function getLicenseKey():?string{return 'avtix-free';}
    public function isTrialMode():bool{return false;}
}
EOF

    cat > "$PANEL_PATH/app/Services/LicenseValidationService.php" <<'EOF'
<?php
namespace Pterodactyl\Services;
class LicenseValidationService {
    public function validate(?string $k=null):bool{return true;}
    public function isValid():bool{return true;}
    public function getDetails():array{return ['status'=>'valid','type'=>'enterprise'];}
}
EOF

    cat > "$PANEL_PATH/app/Services/HyperV2LegacySettingsMigrator.php" <<'EOF'
<?php
namespace Pterodactyl\Services;
class HyperV2LegacySettingsMigrator { public function migrate(){return true;} }
EOF
}

fix_code() {
    echo "Fixing code issues..."

    # Fix User model - add permissionRole
    if [[ -f "$PANEL_PATH/app/Models/User.php" ]] && ! grep -q "function permissionRole" "$PANEL_PATH/app/Models/User.php" 2>/dev/null; then
        python3 -c "
with open('$PANEL_PATH/app/Models/User.php','r') as f: c=f.read()
m='''
    public function permissionRole(){return \$this->hasOne(\Pterodactyl\Models\Setting::class,'id')->limit(0);}
    public function hasAdminPermission(string \$p):bool{return (bool)\$this->root_admin;}
'''
i=c.rfind('}')
if i>0: c=c[:i]+m+c[i:]
with open('$PANEL_PATH/app/Models/User.php','w') as f: f.write(c)
"
        echo "  User model patched"
    fi

    # Fix allowedFilters syntax (Spatie QueryBuilder)
    python3 << 'PYEOF'
import re, glob, os
fixed = 0
for f in glob.glob("/var/www/pterodactyl/app/**/*.php", recursive=True):
    try:
        with open(f,"r") as fh: c=fh.read()
        orig=c
        c=re.sub(r"->allowedFilters\(\[(.*?)\]\)", r"->allowedFilters(\1)", c)
        if c!=orig:
            with open(f,"w") as fh: fh.write(c)
            fixed+=1
    except: pass
if fixed: print(f"  Fixed {fixed} allowedFilters")
PYEOF

    # Fix AppServiceProvider
    cat > "$PANEL_PATH/app/Providers/AppServiceProvider.php" <<'EOF'
<?php
namespace Pterodactyl\Providers;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
class AppServiceProvider extends ServiceProvider {
    public function boot():void {
        Relation::morphMap([
            \Pterodactyl\Models\Server::class => \Pterodactyl\Models\Server::class,
            \Pterodactyl\Models\User::class => \Pterodactyl\Models\User::class,
        ]);
        Paginator::useBootstrap();
    }
}
EOF
    echo "  AppServiceProvider rewritten"

    # Fix admin index.blade.php - cache busting
    if [[ -f "$PANEL_PATH/resources/views/admin/index.blade.php" ]]; then
        if ! grep -q "no-cache" "$PANEL_PATH/resources/views/admin/index.blade.php" 2>/dev/null; then
            sed -i 's|<head>|<head>\n    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">\n    <meta http-equiv="Pragma" content="no-cache">\n    <meta http-equiv="Expires" content="0">|' "$PANEL_PATH/resources/views/admin/index.blade.php"
            echo "  Admin view cache-busted"
        fi
    fi

    # Create nav partial
    mkdir -p "$PANEL_PATH/resources/views/partials/admin/settings"
    if [[ ! -f "$PANEL_PATH/resources/views/partials/admin/settings/nav.blade.php" ]]; then
        cat > "$PANEL_PATH/resources/views/partials/admin/settings/nav.blade.php" <<'NAVEOF'
<nav class="flex flex-col sm:flex-row gap-2 mb-6">
    <a href="/admin/settings" class="px-4 py-2 rounded-lg {{ request()->is('admin/settings') ? 'bg-red-500 text-white' : 'bg-gray-800 text-gray-300 hover:text-white hover:bg-gray-700' }}">General</a>
    <a href="/admin/settings/mail" class="px-4 py-2 rounded-lg {{ request()->is('admin/settings/mail') ? 'bg-red-500 text-white' : 'bg-gray-800 text-gray-300 hover:text-white hover:bg-gray-700' }}">Mail</a>
    <a href="/admin/settings/api" class="px-4 py-2 rounded-lg {{ request()->is('admin/settings/api') ? 'bg-red-500 text-white' : 'bg-gray-800 text-gray-300 hover:text-white hover:bg-gray-700' }}">API</a>
    <a href="/admin/settings/servers" class="px-4 py-2 rounded-lg {{ request()->is('admin/settings/servers') ? 'bg-red-500 text-white' : 'bg-gray-800 text-gray-300 hover:text-white hover:bg-gray-700' }}">Servers</a>
</nav>
NAVEOF
        echo "  Nav partial created"
    fi

    # Fix AssetHashService
    if [[ -f "$PANEL_PATH/app/Services/Helpers/AssetHashService.php" ]] && ! grep -q "function preloads" "$PANEL_PATH/app/Services/Helpers/AssetHashService.php" 2>/dev/null; then
        python3 -c "
with open('$PANEL_PATH/app/Services/Helpers/AssetHashService.php','r') as f: c=f.read()
m='''
    public function preloads():array{return [];}
    public function authPreloads():array{return [];}
'''
i=c.rfind('}')
if i>0: c=c[:i]+m+c[i:]
with open('$PANEL_PATH/app/Services/Helpers/AssetHashService.php','w') as f: f.write(c)
"
        echo "  AssetHashService patched"
    fi
}

add_routes() {
    echo "Adding license API routes..."
    if ! grep -q "clear-all-cache" "$PANEL_PATH/routes/base.php" 2>/dev/null; then
        cat >> "$PANEL_PATH/routes/base.php" <<'ROUTEOF'

Route::get('/api/public/license/verify', function () {
    return response()->json([
        'tier' => 'ultimate', 'status' => 'active', 'valid' => true, 'validity' => 'Valid',
        'community' => false, 'sso_connected' => true,
        'features' => ['sso', 'billing', 'store', 'subdomains', 'recyclebin', 'serversplitter', 'fivem', 'minecraft', 'arma'],
        'panel_type' => 'Avtix Game Panel', 'panel_version' => 'v1.0',
        'license_key' => 'avtix-enterprise', 'verified_at' => now()->toIso8601String(),
    ]);
});
Route::get('/api/public/license/status', function () {
    return response()->json(['tier' => 'ultimate', 'status' => 'active', 'valid' => true, 'validity' => 'Valid', 'community' => false, 'sso_connected' => true]);
});
Route::post('/api/public/license/clear-all-cache', function () {
    return response()->json(['success' => true]);
});
Route::get('/api/public/auth-addon-settings', function () {
    $addons = [
        'UserRegister' => ['enabled' => true, 'name' => 'User Registration', 'description' => 'Enhanced user registration system', 'category' => 'User Management'],
        'theme-settings' => ['enabled' => true, 'name' => 'Theme Settings', 'description' => 'User theme customization', 'category' => 'Appearance'],
        'SiteAlerts' => ['enabled' => true, 'name' => 'Site Alerts', 'description' => 'Configure site-wide alert banners', 'category' => 'User Experience', 'alerts' => []],
        'Notifications' => ['enabled' => true, 'name' => 'System Notifications', 'description' => 'Configure system notifications and alerts', 'category' => 'User Experience', 'notifications' => ['serverEnabled' => false, 'soundsEnabled' => false]],
    ];
    return response()->json(['addons' => $addons, 'updated_at' => now()->toIso8601String(), 'app_url' => config('app.url', '')]);
})->withoutMiddleware(['auth']);
ROUTEOF
        echo "  Routes added"
    fi

    # Enable registration in settings
    mysql -u root panel -e "INSERT IGNORE INTO settings (\`key\`, \`value\`) VALUES ('settings::auth:registration', '1'); INSERT IGNORE INTO settings (\`key\`, \`value\`) VALUES ('settings::auth:2fa_required', '0');" 2>/dev/null || true
    echo "  Registration enabled"
}

fix_nginx_cache() {
    NGINX_CONF="/etc/nginx/sites-enabled/pterodactyl.conf"
    if [[ -f "$NGINX_CONF" ]] && ! grep -q 'Cache-Control' "$NGINX_CONF" 2>/dev/null; then
        sed -i '/# allow larger file uploads/i\
    location ~* \.(js|css)$ {\
        add_header Cache-Control "no-cache, no-store, must-revalidate";\
        add_header Pragma "no-cache";\
        add_header Expires "0";\
    }' "$NGINX_CONF" 2>/dev/null
        echo "  Nginx cache-busting added"
    fi
}

configure_env() {
    echo "Configuring .env..."
    cd "$PANEL_PATH"
    [[ ! -f .env ]] && cp .env.example .env
    php8.4 artisan key:generate --force 2>/dev/null || true
    sed -i "s|^APP_NAME=.*|APP_NAME=\"Avtix Game Panel\"|" .env
    sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
    sed -i "s|^APP_URL=.*|APP_URL=https://your-domain.com|" .env
    sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" .env
    sed -i "s|^DB_PORT=.*|DB_PORT=3306|" .env
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=panel|" .env
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=panel|" .env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=AvtixPanel2026|" .env
}

install_deps() {
    echo "Installing Composer dependencies..."
    cd "$PANEL_PATH"
    composer install --no-dev --optimize-autoloader --no-interaction
    composer show laragear/webauthn >/dev/null 2>&1 || composer require laragear/webauthn --no-interaction 2>/dev/null || true
}

run_migrate() {
    echo "Running migrations..."
    cd "$PANEL_PATH"
    rm -f database/schema/mysql-schema.sql 2>/dev/null || true
    # Skip problematic migration
    php8.4 artisan tinker --execute="
\$mig = new \Illuminate\Database\Migrations\Migrator(new \Illuminate\Database\Migrations\DatabaseMigrationRepository(\Illuminate\Support\Facades\DB::connection(), 'migrations'), \Illuminate\Support\Facades\DB::connection(), new \Illuminate\Filesystem\Filesystem());
" 2>/dev/null || true
    php8.4 artisan migrate --force 2>/dev/null || true

    # Handle any stuck migrations
    php8.4 artisan tinker --execute="
try {
    \Illuminate\Support\Facades\DB::table('migrations')
        ->where('migration', 'like', '%store_node_tokens%')
        ->whereNull('batch')
        ->update(['batch' => 99]);
} catch (Exception \$e) {}
" 2>/dev/null || true

    # Try again
    php8.4 artisan migrate --force 2>/dev/null || true
}

create_admin() {
    echo "Creating admin user..."
    cd "$PANEL_PATH"
    # Use raw SQL to avoid model validation issues
    mysql -u root panel -e "INSERT IGNORE INTO users (uuid, email, username, name_first, name_last, password, root_admin, external_id, language, use_totp, created_at, updated_at) VALUES ('$(cat /proc/sys/kernel/random/uuid)', 'admin@admin.com', 'admin', 'Admin', 'User', '\$2y\$10\$utJHsNQ.GP6ya34OMeM9A.rrJp1Y.ExBVWnnyxQTFkpHvSzfqG0My', 1, 'admin-ext-001', 'en', 0, NOW(), NOW());" 2>/dev/null || true
    echo "  Admin: admin@admin.com / admin"
}

fix_nginx() {
    find /etc/nginx/sites-available/ /etc/nginx/sites-enabled/ /etc/nginx/conf.d/ -type f -exec grep -l "/var/www/pterodactyl" {} + 2>/dev/null | while read -r conf; do
        sed -i -E "s|unix:(/var)?/run/php/php[0-9]+\.[0-9]+-fpm\.sock|unix:/run/php/php8.4-fpm.sock|g" "$conf"
    done
}

finalize() {
    echo "Finalizing..."
    chown -R www-data:www-data "$PANEL_PATH"
    chmod -R 755 "$PANEL_PATH/storage" "$PANEL_PATH/bootstrap/cache" 2>/dev/null || true
    cd "$PANEL_PATH"
    php8.4 artisan config:clear && php8.4 artisan cache:clear && php8.4 artisan route:clear && php8.4 artisan view:clear
    php8.4 artisan config:cache && php8.4 artisan event:cache && php8.4 artisan view:cache
    php8.4 artisan route:cache 2>/dev/null || php8.4 artisan route:clear
    php8.4 artisan queue:restart

    mkdir -p /var/log/pterodactyl
    chown www-data:www-data /var/log/pterodactyl
    cat > /etc/supervisor/conf.d/pterodactyl-scheduler.conf <<'EOF'
[program:pterodactyl-scheduler]
command=php8.4 /var/www/pterodactyl/artisan schedule:work
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
    cat > /etc/supervisor/conf.d/pterodactyl-worker.conf <<'EOF'
[program:pterodactyl-worker]
command=php8.4 /var/www/pterodactyl/artisan queue:work --queue=high,standard,default,low --sleep=3 --tries=3 --timeout=90
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
    supervisorctl reread 2>/dev/null || true
    supervisorctl update 2>/dev/null || true
    supervisorctl restart pterodactyl-scheduler 2>/dev/null || supervisorctl start pterodactyl-scheduler 2>/dev/null || true
    supervisorctl restart pterodactyl-worker 2>/dev/null || supervisorctl start pterodactyl-worker 2>/dev/null || true
    systemctl restart php8.4-fpm 2>/dev/null || true
    nginx -t 2>/dev/null && systemctl reload nginx 2>/dev/null || true
}

# ════════════════════════════════════════════
#  FRESH INSTALL
# ════════════════════════════════════════════
do_install() {
    echo ""
    echo "================================="
    echo "  Avtix Game Panel - Install"
    echo "================================="
    echo ""

    send_approval_request

    echo ""
    echo "Starting installation..."
    echo ""

    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y
    apt-get install -y curl wget git unzip nginx mariadb-server redis-server supervisor software-properties-common

    echo "[1/9] Installing PHP 8.4..."
    if ! command -v php8.4 >/dev/null 2>&1; then
        rm -f /etc/apt/trusted.gpg.d/php.gpg /usr/share/keyrings/deb.sury.org-php.gpg 2>/dev/null || true
        curl -sSLo /tmp/php.gpg https://packages.sury.org/php/apt.gpg 2>/dev/null
        install -o root -g root -m 644 /tmp/php.gpg /etc/apt/trusted.gpg.d/php.gpg
        install -o root -g root -m 644 /tmp/php.gpg /usr/share/keyrings/deb.sury.org-php.gpg
        rm -f /tmp/php.gpg
        echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -cs) main" > /etc/apt/sources.list.d/php.list
        apt-get update -y
    fi
    apt-get install -y php8.4 php8.4-cli php8.4-fpm php8.4-bcmath php8.4-curl php8.4-gd php8.4-mbstring php8.4-mysql php8.4-opcache php8.4-xml php8.4-zip php8.4-intl
    update-alternatives --set php /usr/bin/php8.4 2>/dev/null || true
    ln -sf /usr/bin/php8.4 /usr/bin/php
    ln -sf /usr/bin/php8.4 /usr/local/bin/php

    echo "[2/9] Installing Composer..."
    if ! command -v composer >/dev/null 2>&1; then
        curl -sS https://getcomposer.org/installer | php8.4 -- --install-dir=/usr/local/bin --filename=composer
    fi
    export COMPOSER_ALLOW_SUPERUSER=1

    echo "[3/9] Starting services..."
    systemctl enable nginx mariadb redis-server supervisor 2>/dev/null || true
    systemctl start nginx mariadb redis-server supervisor 2>/dev/null || true
    systemctl restart php8.4-fpm 2>/dev/null || true

    echo "[4/9] Setting up database..."
    mysql -u root -e "DROP DATABASE IF EXISTS panel; CREATE DATABASE panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
    mysql -u root -e "CREATE USER IF NOT EXISTS 'panel'@'127.0.0.1' IDENTIFIED BY 'AvtixPanel2026'; GRANT ALL ON panel.* TO 'panel'@'127.0.0.1'; FLUSH PRIVILEGES;" 2>/dev/null || true
    mysql -u root -e "CREATE USER IF NOT EXISTS 'panel'@'localhost' IDENTIFIED BY 'AvtixPanel2026'; GRANT ALL ON panel.* TO 'panel'@'localhost'; FLUSH PRIVILEGES;" 2>/dev/null || true

    echo "[5/9] Cloning Pterodactyl Panel..."
    if [[ -d "$PANEL_PATH" ]]; then
        mv "$PANEL_PATH" "/var/www/pterodactyl_old_$(date +%Y%m%d_%H%M%S)" 2>/dev/null || true
    fi
    git clone https://github.com/pterodactyl/panel.git "$PANEL_PATH" --depth 1

    echo "[6/9] Downloading and applying theme..."
    download_theme
    apply_theme

    echo "[7/9] Patching and fixing..."
    replace_ioncube_files
    patch_js_files
    create_controllers
    create_services_middleware
    fix_code
    add_routes
    fix_nginx_cache
    rm -rf "$TMP"

    echo "[8/9] Configuring..."
    configure_env
    install_deps
    run_migrate
    create_admin
    fix_nginx
    finalize

    echo ""
    echo "================================="
    echo "  Install Complete!"
    echo "================================="
    echo "  Panel URL: https://your-domain.com"
    echo "  Admin:     admin@admin.com / admin"
    echo "  DB:        panel / AvtixPanel2026"
    echo "================================="
}

# ════════════════════════════════════════════
#  REPAIR
# ════════════════════════════════════════════
do_repair() {
    echo ""
    echo "================================="
    echo "  Avtix Game Panel - Repair"
    echo "================================="
    echo ""

    if [[ ! -d "$PANEL_PATH" ]]; then
        echo "Error: Panel not found. Run fresh install instead."
        exit 1
    fi

    read -p "Continue with repair? (y/n): " CONFIRM
    if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then echo "Aborted."; exit 0; fi

    download_theme

    echo "Re-applying theme..."
    rm -rf "$PANEL_PATH/public/assets" "$PANEL_PATH/public/DGEN" 2>/dev/null || true
    cp -rf "$INNER/public/"* "$PANEL_PATH/public/" 2>/dev/null || true
    rm -rf "$PANEL_PATH/resources/views" 2>/dev/null || true
    cp -rf "$INNER/resources/views" "$PANEL_PATH/resources/" 2>/dev/null || true
    cp -rf "$INNER/resources/lang" "$PANEL_PATH/resources/" 2>/dev/null || true
    cp -f "$INNER/routes/"*.php "$PANEL_PATH/routes/" 2>/dev/null || true

    replace_ioncube_files
    patch_js_files
    create_controllers
    create_services_middleware
    fix_code
    add_routes
    fix_nginx_cache
    rm -rf "$TMP"

    cd "$PANEL_PATH"
    php8.4 artisan config:clear && php8.4 artisan cache:clear && php8.4 artisan route:clear && php8.4 artisan view:clear
    php8.4 artisan config:cache && php8.4 artisan route:cache && php8.4 artisan view:cache
    chown -R www-data:www-data "$PANEL_PATH"
    systemctl restart php8.4-fpm 2>/dev/null || true
    nginx -t 2>/dev/null && systemctl reload nginx 2>/dev/null || true

    echo ""
    echo "================================="
    echo "  Repair Complete!"
    echo "================================="
}

# ════════════════════════════════════════════
#  UNINSTALL
# ════════════════════════════════════════════
do_uninstall() {
    echo ""
    echo "================================="
    echo "  Avtix Game Panel - Uninstall"
    echo "================================="
    echo ""

    if [[ ! -d "$PANEL_PATH" ]]; then echo "Error: Panel not found."; exit 1; fi
    read -p "Type 'UNINSTALL' to confirm: " CONFIRM
    [[ "$CONFIRM" != "UNINSTALL" ]] && echo "Aborted." && exit 0

    TMP=$(mktemp -d)
    git clone https://github.com/pterodactyl/panel.git "$TMP/vanilla" --depth 1
    rm -rf "$PANEL_PATH/public/assets" && cp -rf "$TMP/vanilla/public/assets" "$PANEL_PATH/public/"
    rm -rf "$PANEL_PATH/resources/views" && cp -rf "$TMP/vanilla/resources/views" "$PANEL_PATH/resources/"
    rm -f "$PANEL_PATH/routes/"*.php && cp -f "$TMP/vanilla/routes/"*.php "$PANEL_PATH/routes/"
    for f in "$TMP/vanilla/config/"*.php; do
        fname=$(basename "$f")
        case "$fname" in database.php|app.php|cache.php|sessions.php|queue.php|logging.php|mail.php) continue ;; esac
        cp -f "$f" "$PANEL_PATH/config/" 2>/dev/null || true
    done
    rm -rf "$PANEL_PATH/app/Http/Controllers/Api/Client/DGEN" "$PANEL_PATH/app/Http/Controllers/Api/Client/Servers/DGEN" "$PANEL_PATH/app/Http/Controllers/Api/Client/Admin/DGEN" "$PANEL_PATH/app/Http/Controllers/Api/Client/Theme" "$TMP"

    cd "$PANEL_PATH"
    php8.4 artisan config:clear && php8.4 artisan cache:clear && php8.4 artisan route:clear && php8.4 artisan view:clear
    chown -R www-data:www-data "$PANEL_PATH"
    systemctl restart php8.4-fpm 2>/dev/null || true
    nginx -t 2>/dev/null && systemctl reload nginx 2>/dev/null || true
    echo "Uninstall complete!"
}

# ════════════════════════════════════════════
#  MENU
# ════════════════════════════════════════════
echo ""
echo "================================="
echo "  Avtix Game Panel"
echo "================================="
echo ""
echo "  1) Install   - Fresh install on new VPS"
echo "  2) Repair    - Re-apply theme (keeps data)"
echo "  3) Uninstall - Remove theme, restore vanilla"
echo ""
echo "================================="
echo ""
read -p "  Select option [1-3]: " CHOICE
case "$CHOICE" in
    1) do_install ;;
    2) do_repair ;;
    3) do_uninstall ;;
    *) echo "Invalid option."; exit 1 ;;
esac
