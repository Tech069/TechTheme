#!/usr/bin/env node
/**
 * VexyThemes — Discord Webhook Notifier
 * 
 * Sends embedded changelog messages to Discord via webhook.
 * Usage: node build/notify.js "v3.1.0" "Security hardening, HMAC verification added"
 * 
 * Embed style matches DGEN License format (purple theme).
 */

const https = require('https');

const WEBHOOK_URL = process.env.DISCORD_WEBHOOK || 'https://discord.com/api/webhooks/1517552548884774964/96PnkPtHNTAby12lC_zafqP71yYQn7tM9gf8_DGgKXT7bYJnBJ1aJNDWE9Jless6Xff6';

// Don't leak these words in changelogs
const SENSITIVE_TERMS = [
    'secret', 'password', 'token', 'api key', 'apikey', 'private key',
    'hmac', 'upstash', 'redis url', 'redis token', 'webhook',
    'admin pass', 'vexy2026', 'AvtixPanel', 'DB_PASS', 'REDIS_TOKEN',
    'client secret', 'bot token', 'env', '.env'
];

function sanitizeChangelog(text) {
    let result = text;
    for (const term of SENSITIVE_TERMS) {
        const regex = new RegExp(term, 'gi');
        result = result.replace(regex, '[REDACTED]');
    }
    return result;
}

function sendDiscordEmbed(version, changes) {
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-IN', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const timeStr = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });

    const changelogLines = changes.split(/[|\n]/).filter(l => l.trim());
    const sanitized = changelogLines.map(l => sanitizeChangelog(l.trim()));

    const embed = {
        title: '⚡ VexyThemes',
        description: `**Panel ${version}**`,
        color: 0x8b5cf6,
        fields: [
            {
                name: 'Changelog',
                value: '```\n' + sanitized.map(l => `▸ ${l}`).join('\n') + '\n```',
                inline: false
            }
        ],
        footer: {
            text: `VexyThemes • ${dateStr} ${timeStr}`
        },
        timestamp: now.toISOString()
    };

    const payload = JSON.stringify({ embeds: [embed] });

    const url = new URL(WEBHOOK_URL);
    const options = {
        hostname: url.hostname,
        path: url.pathname,
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Content-Length': Buffer.byteLength(payload)
        }
    };

    return new Promise((resolve, reject) => {
        const req = https.request(options, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => {
                if (res.statusCode >= 200 && res.statusCode < 300) {
                    console.log(`✅ Discord notified: ${version}`);
                    resolve(true);
                } else {
                    console.error(`❌ Discord error ${res.statusCode}: ${body}`);
                    resolve(false);
                }
            });
        });
        req.on('error', (e) => {
            console.error(`❌ Discord request failed: ${e.message}`);
            resolve(false);
        });
        req.write(payload);
        req.end();
    });
}

// CLI usage
if (require.main === module) {
    const version = process.argv[2] || 'v3.0.0';
    const changes = process.argv[3] || 'General improvements and bug fixes';
    
    console.log(`📨 Sending Discord notification for ${version}...`);
    sendDiscordEmbed(version, changes).then(() => process.exit());
}

module.exports = { sendDiscordEmbed, sanitizeChangelog };
