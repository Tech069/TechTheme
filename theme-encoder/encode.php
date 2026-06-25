<?php
/**
 * AVTIX THEME ENCODER
 * 
 * Encrypts PHP files so they can't be read or modified.
 * Usage: php encode.php <input_dir> <output_dir> <encryption_key>
 * 
 * The encoded files can ONLY run with the Avtix decoder (decoder.php).
 * This prevents:
 *   - Viewing source code
 *   - Modifying theme files
 *   - Removing license checks
 */

if ($argc < 4) {
    echo "Usage: php encode.php <input_dir> <output_dir> <key>\n";
    echo "  input_dir  — Path to theme files to encrypt\n";
    echo "  output_dir — Path for encoded output\n";
    echo "  key        — Encryption key (same as license server key)\n";
    exit(1);
}

$inputDir  = $argv[1];
$outputDir = $argv[2];
$key       = $argv[3];

if (!is_dir($inputDir)) {
    echo "ERROR: Input directory '$inputDir' not found\n";
    exit(1);
}

echo "=== AVTIX THEME ENCODER ===\n";
echo "Input:  $inputDir\n";
echo "Output: $outputDir\n\n";

// Create output directory
if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);

$encoded = 0;
$skipped = 0;

// Recursively process all files
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($inputDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    $relativePath = substr($file->getPathname(), strlen($inputDir) + 1);
    $relativePath = str_replace('\\', '/', $relativePath); // Windows compat
    $destPath = $outputDir . '/' . $relativePath;
    
    // Create subdirectories
    $destDir = dirname($destPath);
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    
    if ($file->getExtension() === 'php') {
        // ENCODE PHP FILES
        $source = file_get_contents($file->getPathname());
        
        if (empty(trim($source))) {
            // Skip empty files
            copy($file->getPathname(), $destPath);
            $skipped++;
            continue;
        }
        
        // Skip if already encoded
        if (strpos($source, 'AVTIX_ENCRYPTED') !== false) {
            copy($file->getPathname(), $destPath);
            $skipped++;
            continue;
        }
        
        // Skip the decoder itself
        if (basename($file) === 'decoder.php') {
            copy($file->getPathname(), $destPath);
            $skipped++;
            continue;
        }
        
        // Encode the PHP file
        $encoded_source = encodePHP($source, $key);
        file_put_contents($destPath, $encoded_source);
        $encoded++;
        echo "  ENCODED: $relativePath\n";
        
    } elseif (basename($file) === 'decoder.php') {
        // Copy decoder as-is
        copy($file->getPathname(), $destPath);
        $skipped++;
    } else {
        // Copy non-PHP files as-is
        copy($file->getPathname(), $destPath);
        $skipped++;
    }
}

echo "\n=== DONE ===\n";
echo "Encoded: $encoded files\n";
echo "Skipped: $skipped files (non-PHP, empty, or already encoded)\n";

// ============================================================
// ENCODING FUNCTIONS
// ============================================================

function encodePHP($source, $key) {
    // Step 1: Compress
    $compressed = gzcompress($source, 9);
    
    // Step 2: Encrypt with AES-256-CBC
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($compressed, 'aes-256-cbc', hash('sha256', $key, true), 0, $iv);
    
    // Step 3: Pack into a self-contained loader
    $encoded = '<?php
/**
 * @AVTIX_ENCRYPTED
 * This file is protected by Avtix Theme Encoder.
 * Unauthorized modification or distribution is prohibited.
 * License: ' . AVTIX_LICENSE_KEY . '
 */
if (!defined("AVTIX_RUNTIME")) {
    http_response_code(403);
    echo "Avtix: Runtime loader required. Do not access this file directly.";
    exit(1);
}
$_av = \'' . base64_encode($iv) . '\';
$_ed = \'' . $encrypted . '\';
$_dk = hash("sha256", AVTIX_LICENSE_KEY, true);
$_dc = openssl_decrypt($_ed, "aes-256-cbc", $_dk, 0, base64_decode($_av));
$_so = gzuncompress($_dc);
if ($_so === false) { http_response_code(500); echo "Avtix: Decryption failed."; exit(1); }
eval($_so);
';
    
    return $encoded;
}
