<?php
/**
 * AVTIX LICENSE SERVER
 * Host this on any PHP server. Manages IP whitelists for theme installations.
 * 
 * Endpoints:
 *   POST /api.php          — License operations (JSON body)
 *   GET  /api.php?check=1  — Quick IP check (used by install.sh)
 * 
 * Setup:
 *   1. Upload this folder to your PHP server
 *   2. Set $ADMIN_KEY below to a secret password
 *   3. Open admin.html to manage licenses
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ============================================================
// CONFIGURATION — CHANGE THIS!
// ============================================================
$ADMIN_KEY    = 'AVTIX-ADMIN-2026';          // Admin password for management
$LICENSE_KEY  = 'AVTIX-FREE-THEME-2026';     // Default license key
$DATA_DIR     = __DIR__ . '/data';

// ============================================================
// DATA LAYER
// ============================================================
if (!is_dir($DATA_DIR)) mkdir($DATA_DIR, 0755, true);
$DB_FILE = $DATA_DIR . '/licenses.json';

function loadDB() {
    global $DB_FILE;
    if (!file_exists($DB_FILE)) {
        $default = [
            'licenses' => [],
            'ips'      => [],
            'meta'     => ['created' => time(), 'version' => '2.0']
        ];
        file_put_contents($DB_FILE, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }
    return json_decode(file_get_contents($DB_FILE), true);
}

function saveDB($db) {
    global $DB_FILE;
    file_put_contents($DB_FILE, json_encode($db, JSON_PRETTY_PRINT));
}

// ============================================================
// API ROUTING
// ============================================================
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? $_GET['action'] ?? $_GET['do'] ?? '';
$adminKey = $input['admin_key'] ?? $_GET['key'] ?? '';

// --- Quick check endpoint for install.sh ---
// GET /api.php?check=1 (caller adds ?ip=X.X.X.X)
if (isset($_GET['check'])) {
    $ip = $_GET['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = trim($ip);
    $db = loadDB();
    
    $allowed = false;
    // Check if IP is in whitelist
    if (isset($db['ips'][$ip]) && $db['ips'][$ip]['active']) {
        $allowed = true;
    }
    // Check wildcard
    if (isset($db['ips']['*']) && $db['ips']['*']['active']) {
        $allowed = true;
    }
    // Check if any active license key is valid (IP registered under a key)
    foreach ($db['licenses'] as $key => $lic) {
        if ($lic['active'] && ($lic['ip'] === $ip || empty($lic['ip']))) {
            $allowed = true;
            break;
        }
    }
    
    echo json_encode([
        'allowed' => $allowed,
        'ip'      => $ip,
        'message' => $allowed ? 'IP authorized' : 'IP not authorized',
    ]);
    exit;
}

// --- All other actions require admin key ---
if ($adminKey !== $ADMIN_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'message' => 'Invalid admin key']);
    exit;
}

switch ($action) {
    // Add IP to whitelist
    case 'add_ip':
        $ip = $input['ip'] ?? '';
        $label = $input['label'] ?? '';
        if (empty($ip)) { echo json_encode(['error' => 'IP required']); exit; }
        $db = loadDB();
        $db['ips'][$ip] = [
            'active'  => true,
            'label'   => $label,
            'added'   => date('Y-m-d H:i:s'),
            'added_by'=> 'admin',
        ];
        saveDB($db);
        echo json_encode(['success' => true, 'message' => "IP $ip added"]);
        break;
    
    // Remove IP
    case 'remove_ip':
        $ip = $input['ip'] ?? '';
        $db = loadDB();
        if (isset($db['ips'][$ip])) {
            unset($db['ips'][$ip]);
            saveDB($db);
            echo json_encode(['success' => true, 'message' => "IP $ip removed"]);
        } else {
            echo json_encode(['error' => 'IP not found']);
        }
        break;
    
    // Toggle IP
    case 'toggle_ip':
        $ip = $input['ip'] ?? '';
        $db = loadDB();
        if (isset($db['ips'][$ip])) {
            $db['ips'][$ip]['active'] = !$db['ips'][$ip]['active'];
            saveDB($db);
            echo json_encode(['success' => true, 'active' => $db['ips'][$ip]['active']]);
        } else {
            echo json_encode(['error' => 'IP not found']);
        }
        break;
    
    // List all IPs
    case 'list_ips':
        $db = loadDB();
        echo json_encode(['ips' => $db['ips'] ?? []]);
        break;
    
    // Create license key
    case 'create_license':
        $key = $input['key'] ?? $LICENSE_KEY;
        $ip  = $input['ip'] ?? '';
        $db  = loadDB();
        $db['licenses'][$key] = [
            'active'  => true,
            'ip'      => $ip,
            'created' => date('Y-m-d H:i:s'),
            'expires' => $input['expires'] ?? null,
            'holder'  => $input['holder'] ?? '',
        ];
        saveDB($db);
        echo json_encode(['success' => true, 'key' => $key]);
        break;
    
    // Deactivate license
    case 'deactivate':
        $key = $input['key'] ?? '';
        $db  = loadDB();
        if (isset($db['licenses'][$key])) {
            $db['licenses'][$key]['active'] = false;
            saveDB($db);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'License not found']);
        }
        break;
    
    // Bulk add IPs (from file, one per line)
    case 'bulk_add':
        $ips = $input['ips'] ?? [];
        $db  = loadDB();
        $added = 0;
        foreach ($ips as $ip) {
            $ip = trim($ip);
            if (empty($ip)) continue;
            if (!isset($db['ips'][$ip])) {
                $db['ips'][$ip] = [
                    'active'  => true,
                    'label'   => 'bulk',
                    'added'   => date('Y-m-d H:i:s'),
                    'added_by'=> 'admin',
                ];
                $added++;
            }
        }
        saveDB($db);
        echo json_encode(['success' => true, 'added' => $added]);
        break;
    
    // Stats
    case 'stats':
        $db = loadDB();
        $activeIps = 0;
        foreach ($db['ips'] as $ip => $data) {
            if ($data['active']) $activeIps++;
        }
        echo json_encode([
            'total_ips'      => count($db['ips'] ?? []),
            'active_ips'     => $activeIps,
            'total_licenses' => count($db['licenses'] ?? []),
            'version'        => $db['meta']['version'] ?? '2.0',
        ]);
        break;
    
    // Update admin key
    case 'change_key':
        // Only from CLI or same-server
        echo json_encode(['error' => 'Change $ADMIN_KEY in api.php directly']);
        break;
    
    default:
        echo json_encode([
            'error'   => 'Unknown action',
            'actions' => ['add_ip','remove_ip','toggle_ip','list_ips','create_license','deactivate','bulk_add','stats'],
        ]);
}
