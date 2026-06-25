#!/usr/bin/env node
/**
 * PHP file encoder - encodes PHP files so source is hidden
 * but PHP can still execute them via eval()
 * 
 * Usage: node build/encode-php.js <directory>
 */

const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const TARGET = process.argv[2] || path.join(__dirname, '..', '_theme-extract', 'theme-full', 'resources', 'views');

function encodeFile(filePath) {
    const content = fs.readFileSync(filePath, 'utf8');
    
    // Skip already encoded files
    if (content.includes('AVTIX_ENCODED') || content.includes('//002cd')) return false;
    
    // Skip empty files
    if (content.trim().length === 0) return false;

    // Gzip compress
    const compressed = zlib.gzipSync(Buffer.from(content, 'utf8'), { level: 9 });
    
    // Base64 encode
    const encoded = compressed.toString('base64');
    
    // Build decoder wrapper
    const ext = path.extname(filePath);
    
    if (ext === '.blade.php') {
        // For blade templates: use eval to decode at runtime
        const wrapper = `<?php
// AVTIX_ENCODED - DO NOT EDIT
if(!function_exists('_avtix_decode')){
    function _avtix_decode($d){return gzdecode(base64_decode($d));}
}
eval(_avtix_decode('${encoded}'));
`;
        fs.writeFileSync(filePath, wrapper, 'utf8');
        return true;
    } else {
        // For regular PHP files: use eval
        const wrapper = `<?php
// AVTIX_ENCODED - DO NOT EDIT
eval(gzdecode(base64_decode('${encoded}')));
`;
        fs.writeFileSync(filePath, wrapper, 'utf8');
        return true;
    }
}

function walkDir(dir) {
    let count = 0;
    const files = fs.readdirSync(dir);
    
    for (const file of files) {
        const fullPath = path.join(dir, file);
        const stat = fs.statSync(fullPath);
        
        if (stat.isDirectory()) {
            count += walkDir(fullPath);
        } else if (file.endsWith('.php')) {
            if (encodeFile(fullPath)) {
                console.log(`Encoded: ${path.relative(path.join(__dirname, '..'), fullPath)}`);
                count++;
            }
        }
    }
    return count;
}

// Don't encode if already encoded
const sample = fs.readdirSync(TARGET);
const firstPhp = sample.find(f => f.endsWith('.php'));
if (firstPhp) {
    const content = fs.readFileSync(path.join(TARGET, firstPhp), 'utf8');
    if (content.includes('AVTIX_ENCODED')) {
        console.log('Already encoded. Skipping.');
        process.exit(0);
    }
}

const count = walkDir(TARGET);
console.log(`\nEncoded ${count} files.`);
