#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const zlib = require('zlib');

const INPUT = path.join(__dirname, '..', 'install.sh');
const OUTPUT = path.join(__dirname, '..', 'install.sh');

function obfuscate() {
    // Read the original install.sh
    const content = fs.readFileSync(INPUT, 'utf8');
    const hash = crypto.createHash('sha256').update(content).digest('hex');

    // Gzip compress
    const compressed = zlib.gzipSync(Buffer.from(content, 'utf8'), { level: 9 });

    // Base64 encode
    const encoded = compressed.toString('base64');

    // Build a minimal self-extracting stub
    // Nothing readable - just decode and execute
    const stub = `#!/bin/bash
eval "$(echo '${encoded}' | base64 -d | gunzip 2>/dev/null)"`;

    fs.writeFileSync(OUTPUT, stub, 'utf8');

    console.log(`Original:   ${content.split('\n').length} lines`);
    console.log(`Obfuscated: 2 lines`);
    console.log(`Hash:       ${hash.slice(0, 16)}...`);
    console.log('Done.');
}

// Don't re-obfuscate an already obfuscated file
const current = fs.readFileSync(INPUT, 'utf8');
if (current.startsWith('#!/bin/bash\neval')) {
    console.log('Already obfuscated. Skipping.');
    process.exit(0);
}

obfuscate();
