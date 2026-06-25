#!/usr/bin/env node
/**
 * VexyThemes Advanced PHP Obfuscator
 * 
 * Multi-layer obfuscation for PHP code:
 * - String packing via hex encoding
 * - Variable name mangling
 * - Dead code injection
 * - Control flow obfuscation
 * - Function call wrapping
 */

const crypto = require('crypto');

// Random variable name generator
const VAR_CHARS = 'abcdefghijklmnopqrstuvwxyz';
function randomVar() {
    let v = '_vt_';
    for (let i = 0; i < 8; i++) v += VAR_CHARS[Math.floor(Math.random() * 26)];
    return v;
}

// Encode string as hex-packed PHP expression
function packString(str) {
    const hex = Buffer.from(str, 'utf8').toString('hex');
    const bytes = hex.match(/.{2}/g);
    // Mix of chr() calls and hex concatenation
    const chunks = [];
    for (let i = 0; i < bytes.length; i += 4) {
        const chunk = bytes.slice(i, i + 4);
        if (chunk.length === 1) {
            chunks.push(`chr(0x${chunk[0]})`);
        } else if (chunk.length === 2) {
            chunks.push(`chr(0x${chunk[0]}).chr(0x${chunk[1]})`);
        } else if (chunk.length === 3) {
            chunks.push(`chr(0x${chunk[0]}).chr(0x${chunk[1]}).chr(0x${chunk[2]})`);
        } else {
            chunks.push(`chr(0x${chunk[0]}).chr(0x${chunk[1]}).chr(0x${chunk[2]}).chr(0x${chunk[3]})`);
        }
    }
    return chunks.join('.');
}

// Generate dead code (functions that look real but do nothing)
function deadCode() {
    const funcs = [];
    for (let i = 0; i < 3; i++) {
        const name = randomVar();
        funcs.push(`function ${name}(){$x=${Math.floor(Math.random()*999)};return $x%2===0?true:false;}`);
    }
    return funcs.join('');
}

// Obfuscate a PHP code block
function obfuscatePhpBlock(code) {
    let result = code;

    // 1. Pack sensitive strings
    const sensitivePatterns = [
        { pattern: /'vexythemes_license_key'/g, replacement: () => `'${packString('vexythemes_license_key')}'` },
        { pattern: /'vexythemes_license_cache'/g, replacement: () => `'${packString('vexythemes_license_cache')}'` },
        { pattern: /'settings::app:theme:hyperv2'/g, replacement: () => `'${packString('settings::app:theme:hyperv2')}'` },
        { pattern: /'settings::app:addons:hyperv2'/g, replacement: () => `'${packString('settings::app:addons:hyperv2')}'` },
        { pattern: /'vexythemes-license'/g, replacement: () => `'${packString('vexythemes-license')}'` },
    ];

    for (const { pattern, replacement } of sensitivePatterns) {
        result = result.replace(pattern, replacement);
    }

    // 2. Obfuscate function definitions with random names for internal vars
    result = result.replace(/(\$[a-z_]+)\s*=\s*\$request->input\(/g, (match, varName) => {
        const newVar = randomVar();
        return `${newVar}=$request->input(`;
    });

    // 3. Add dead code at the end of functions
    result = result.replace(/return\s+(response\(\)->json\([^)]+\));/g, (match, ret) => {
        if (Math.random() > 0.7) {
            const deadVar = randomVar();
            return `${deadVar}=${Math.floor(Math.random()*9999)};${ret}`;
        }
        return match;
    });

    // 4. Wrap numeric literals
    result = result.replace(/\b(86400)\b/g, (match) => {
        const a = Math.floor(Math.random() * 100);
        return `${a}+${86400-a}`;
    });

    // 5. Obfuscate HTTP timeout values
    result = result.replace(/'timeout'\s*=>\s*(\d+)/g, (match, val) => {
        const jitter = Math.floor(Math.random() * 2);
        return `'timeout'=>${parseInt(val)+jitter}`;
    });

    return result;
}

// Advanced: Generate encrypted PHP loader
function generateEncryptedLoader(code) {
    const key = crypto.randomBytes(32).toString('hex');
    const iv = crypto.randomBytes(16);
    
    // Simple XOR + base64 obfuscation (not real encryption, but makes it unreadable)
    const encoded = Buffer.from(code, 'utf8');
    const keyBuf = Buffer.from(key, 'hex');
    const xored = Buffer.alloc(encoded.length);
    for (let i = 0; i < encoded.length; i++) {
        xored[i] = encoded[i] ^ keyBuf[i % keyBuf.length];
    }
    
    const b64 = xored.toString('base64');
    
    // Generate PHP decoder
    const decoder = `<?php
// VexyThemes Encrypted Module
// Build: ${new Date().toISOString().substring(0, 10)}
function _vxt_decode($data, $key) {
    $k = hex2bin($key);
    $d = base64_decode($data);
    $r = '';
    for ($i = 0; $i < strlen($d); $i++) {
        $r .= chr(ord($d[$i]) ^ ord($k[$i % strlen($k)]));
    }
    return $r;
}
eval(_vxt_decode('${b64}', '${key}'));
`;
    return decoder;
}

module.exports = { obfuscatePhpBlock, packString, deadCode, generateEncryptedLoader, randomVar };
