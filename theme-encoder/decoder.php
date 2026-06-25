<?php
/**
 * AVTIX RUNTIME DECODER
 * 
 * Include this file at the top of your application bootstrap.
 * It sets up the runtime environment for encrypted theme files.
 * 
 * Place this in: public/avtix-decoder.php
 * And add to index.php: require __DIR__ . '/avtix-decoder.php';
 */

if (!defined('AVTIX_RUNTIME')) {
    define('AVTIX_RUNTIME', true);
}

// License configuration — synced with license server
if (!defined('AVTIX_LICENSE_KEY')) {
    define('AVTIX_LICENSE_KEY', 'AVTIX-FREE-THEME-2026');
}

// License server URL
if (!defined('AVTIX_LICENSE_SERVER')) {
    define('AVTIX_LICENSE_SERVER', 'https://YOUR-LICENSE-SERVER.COM/license-server/api.php');
}

// IP validation cache
$_avtix_cache_dir = sys_get_temp_dir() . '/avtix';
if (!is_dir($_avtix_cache_dir)) @mkdir($_avtix_cache_dir, 0755, true);

/**
 * Validate this VPS against the license server.
 * Caches result for 24 hours to avoid repeated checks.
 */
function avtix_validate_license() {
    global $_avtix_cache_dir;
    
    $cache_file = $_avtix_cache_dir . '/license_' . md5($_SERVER['SERVER_ADDR'] ?? 'local');
    $cache_ttl = 86400; // 24 hours
    
    // Check cache
    if (file_exists($cache_file)) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if ($cached && time() - ($cached['time'] ?? 0) < $cache_ttl) {
            return $cached['valid'] ?? false;
        }
    }
    
    // Get server IP
    $ip = avtix_get_server_ip();
    
    // Check with license server
    $valid = false;
    $server_url = AVTIX_LICENSE_SERVER;
    
    if ($server_url && $server_url !== 'https://YOUR-LICENSE-SERVER.COM/license-server/api.php') {
        $url = $server_url . '?check=1&ip=' . urlencode($ip);
        $ctx = stream_context_create([
            'http' => ['timeout' => 5, 'ignore_errors' => true],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $response = @file_get_contents($url, false, $ctx);
        
        if ($response) {
            $data = json_decode($response, true);
            $valid = ($data['allowed'] ?? false) === true;
        }
    } else {
        // No server configured — allow (demo mode)
        $valid = true;
    }
    
    // Cache result
    @file_put_contents($cache_file, json_encode([
        'valid' => $valid,
        'time'  => time(),
        'ip'    => $ip,
    ]));
    
    return $valid;
}

/**
 * Get the server's public IP address
 */
function avtix_get_server_ip() {
    // Try multiple methods
    $methods = [
        function() { return @file_get_contents('https://api.ipify.org?format=json', false, stream_context_create(['http'=>['timeout'=>3]])); },
        function() { return @file_get_contents('https://ifconfig.me/ip', false, stream_context_create(['http'=>['timeout'=>3]])); },
        function() { return $_SERVER['SERVER_ADDR'] ?? null; },
    ];
    
    foreach ($methods as $method) {
        $result = @$method();
        if ($result) {
            if (strpos($result, '{') === 0) {
                $json = json_decode($result, true);
                return $json['ip'] ?? '127.0.0.1';
            }
            return trim($result);
        }
    }
    
    return '127.0.0.1';
}

// Auto-validate on every request (but cache for 24h)
avtix_validate_license();
