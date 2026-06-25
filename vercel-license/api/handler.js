const crypto = require('crypto');

// === CONFIGURATION ===
const REDIS_URL = process.env.UPSTASH_REDIS_REST_URL;
const REDIS_TOKEN = process.env.UPSTASH_REDIS_REST_TOKEN;
const ADMIN_USER = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASS = process.env.ADMIN_PASSWORD || 'vexy2026';
const HMAC_SECRET = process.env.HMAC_SECRET || crypto.randomBytes(32).toString('hex');
const DISCORD_WEBHOOK = 'https://discord.com/api/webhooks/1517860870921654392/It4Gq3ZRCZ2GiNRmPSaik7V4yxMLRiYEsOQ07wp5dy8033ibSMwkQe7auxI7b99Mb9bq';
const API_BASE = 'https://vt-panel-api.vercel.app';

// === RATE LIMITING ===
const rateLimit = new Map();
function checkRateLimit(ip, max = 30, window = 60000) {
    const now = Date.now();
    const entry = rateLimit.get(ip) || { count: 0, reset: now + window };
    if (now > entry.reset) { entry.count = 0; entry.reset = now + window; }
    entry.count++;
    rateLimit.set(ip, entry);
    return entry.count <= max;
}

// === REDIS ===
async function redisCmd(...args) {
    const res = await fetch(REDIS_URL, {
        method: 'POST',
        headers: { Authorization: `Bearer ${REDIS_TOKEN}`, 'Content-Type': 'application/json' },
        body: JSON.stringify(args),
    });
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    return data.result;
}

// === KEY GENERATION ===
function genKey() {
    const c = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let k = 'VEXY-';
    for (let i = 0; i < 4; i++) {
        let s = '';
        for (let j = 0; j < 4; j++) s += c[Math.floor(Math.random() * c.length)];
        k += s + (i < 3 ? '-' : '');
    }
    return k;
}

function genSession() { return crypto.randomBytes(24).toString('hex'); }

// === HMAC VERIFICATION ===
function verifyKeyFormat(key) {
    if (!key || typeof key !== 'string') return false;
    if (!key.startsWith('VEXY-')) return false;
    const parts = key.split('-');
    if (parts.length !== 5) return false;
    for (const p of parts) {
        if (p.length !== 4 || !/^[A-Z0-9]{4}$/.test(p)) return false;
    }
    return true;
}

function generateKeyChecksum(key) {
    return crypto.createHmac('sha256', HMAC_SECRET).update(key).digest('hex');
}

// === HTML PAGE ===
function htmlPage(title, body) {
    return `<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>${title} — VexyThemes</title>
<style>*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0c0a09;font-family:system-ui,-apple-system,sans-serif;color:#fafafa}
.card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:48px 40px;max-width:440px;width:90%;text-align:center}
.icon{width:72px;height:72px;border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:36px;margin:0 auto 24px}
.icon.approved{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.2)}
.icon.denied{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.2)}
.icon.pending{background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.2)}
h1{font-size:22px;font-weight:700;margin-bottom:8px;letter-spacing:-.02em}
p{font-size:14px;color:#a1a1aa;line-height:1.6}
.ip{font-family:'JetBrains Mono',monospace;font-size:13px;color:#fbbf24;background:rgba(251,191,36,.08);padding:6px 14px;border-radius:8px;display:inline-block;margin:16px 0;border:1px solid rgba(251,191,36,.15)}
.badge{display:inline-block;font-size:11px;font-weight:700;padding:4px 12px;border-radius:8px;text-transform:uppercase;letter-spacing:.5px;margin-top:16px}
.badge.approved{background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.2)}
.badge.denied{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.2)}
.badge.already{background:rgba(99,102,241,.15);color:#818cf8;border:1px solid rgba(99,102,241,.2)}
.brand{margin-top:24px;font-size:12px;color:#3f3f46}</style></head>
<body><div class="card">${body}</div></body></html>`;
}

// === SEND FOLLOW-UP WEBHOOK ===
async function sendFollowup(title, description, color, ip, hostname) {
    try {
        await fetch(DISCORD_WEBHOOK, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                embeds: [{
                    title,
                    description,
                    color,
                    fields: [
                        { name: 'IP', value: '`' + ip + '`', inline: true },
                        { name: 'Hostname', value: '`' + (hostname || 'unknown') + '`', inline: true },
                    ],
                    timestamp: new Date().toISOString(),
                }],
            }),
        });
    } catch (e) { /* continue */ }
}

// === CLEANUP OLD RATE LIMITS ===
setInterval(() => {
    const now = Date.now();
    for (const [ip, entry] of rateLimit) {
        if (now > entry.reset) rateLimit.delete(ip);
    }
}, 60000);

// === MAIN HANDLER ===
module.exports = async (req, res) => {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type,Authorization,X-Vxt-Sig');
    res.setHeader('X-Content-Type-Options', 'nosniff');
    res.setHeader('X-Frame-Options', 'DENY');
    if (req.method === 'OPTIONS') return res.status(200).end();

    const now = new Date().toISOString();
    const clientIp = req.headers['x-forwarded-for'] || req.headers['x-real-ip'] || 'unknown';

    // Rate limit
    if (!checkRateLimit(clientIp)) {
        return res.status(429).json({ error: 'Rate limit exceeded' });
    }

    try {
        // === GET: Approve/Deny/Dashboard link buttons ===
        if (req.method === 'GET') {
            const url = req.url || '';
            const approveMatch = url.match(/\/api\/approve\/([a-f0-9]{16})/);
            const denyMatch = url.match(/\/api\/deny\/([a-f0-9]{16})/);

            // GET /api/approve/:id
            if (approveMatch) {
                const requestId = approveMatch[1];
                const raw = await redisCmd('GET', 'install:' + requestId);
                const request = typeof raw === 'string' ? JSON.parse(raw) : raw;
                if (!request) {
                    return res.status(404).send(htmlPage('Not Found', `
                        <div class="icon denied">✕</div>
                        <h1>Request Not Found</h1>
                        <p>This install request doesn't exist or has been cleaned up.</p>
                    `));
                }

                if (request.status !== 'pending') {
                    return res.send(htmlPage('Already Decided', `
                        <div class="icon already">ℹ</div>
                        <h1>Already ${request.status === 'approved' ? 'Approved' : 'Denied'}</h1>
                        <p>This install request was already ${request.status}.</p>
                        <div class="ip">${request.ip || 'unknown'}</div>
                        <span class="badge already">Already ${request.status}</span>
                        <div class="brand">VexyThemes</div>
                    `));
                }

                // Approve it
                request.status = 'approved';
                request.decided = now;
                request.decidedBy = 'discord-link';
                await redisCmd('SET', 'install:' + requestId, JSON.stringify(request));

                // Send follow-up webhook
                await sendFollowup(
                    '✅ Install Approved',
                    `Install request approved via Discord button.`,
                    0x4ade80,
                    request.ip,
                    request.hostname
                );

                return res.send(htmlPage('Approved', `
                    <div class="icon approved">✓</div>
                    <h1>Install Approved</h1>
                    <p>The install request has been approved. The installer will continue automatically.</p>
                    <div class="ip">${request.ip || 'unknown'}</div>
                    <span class="badge approved">Approved</span>
                    <div class="brand">VexyThemes</div>
                `));
            }

            // GET /api/deny/:id
            if (denyMatch) {
                const requestId = denyMatch[1];
                const raw = await redisCmd('GET', 'install:' + requestId);
                const request = typeof raw === 'string' ? JSON.parse(raw) : raw;
                if (!request) {
                    return res.status(404).send(htmlPage('Not Found', `
                        <div class="icon denied">✕</div>
                        <h1>Request Not Found</h1>
                        <p>This install request doesn't exist or has been cleaned up.</p>
                    `));
                }

                if (request.status !== 'pending') {
                    return res.send(htmlPage('Already Decided', `
                        <div class="icon already">ℹ</div>
                        <h1>Already ${request.status === 'approved' ? 'Approved' : 'Denied'}</h1>
                        <p>This install request was already ${request.status}.</p>
                        <div class="ip">${request.ip || 'unknown'}</div>
                        <span class="badge already">Already ${request.status}</span>
                        <div class="brand">VexyThemes</div>
                    `));
                }

                // Deny it
                request.status = 'denied';
                request.decided = now;
                request.decidedBy = 'discord-link';
                await redisCmd('SET', 'install:' + requestId, JSON.stringify(request));

                // Send follow-up webhook
                await sendFollowup(
                    '❌ Install Denied',
                    `Install request denied via Discord button.`,
                    0xf87171,
                    request.ip,
                    request.hostname
                );

                return res.send(htmlPage('Denied', `
                    <div class="icon denied">✕</div>
                    <h1>Install Denied</h1>
                    <p>The install request has been denied. The installer will abort.</p>
                    <div class="ip">${request.ip || 'unknown'}</div>
                    <span class="badge denied">Denied</span>
                    <div class="brand">VexyThemes</div>
                `));
            }

            // GET /dashboard — redirect to admin panel
            if (url.startsWith('/dashboard') || url === '/') {
                return res.status(302).setHeader('Location', '/index.html').end();
            }
        }

        // === POST: API endpoints ===
        let body = {};
        if (req.method === 'POST') {
            body = req.body || {};
            if (typeof body === 'string') { try { body = JSON.parse(body); } catch(e) { body = {}; } }
        }

        const path = (body._endpoint || 'auth').replace(/^\//, '');
        const a = body.action || '';

        // === AUTH ===
        if (path === 'auth') {
            if (a === 'login') {
                const username = (body.username || '').trim().toLowerCase();
                const password = (body.password || '').trim();
                if (username === ADMIN_USER && password === ADMIN_PASS) {
                    const session = genSession();
                    await redisCmd('SET', 'sess:' + session, JSON.stringify({ username: ADMIN_USER, role: 'admin', created: now, ip: clientIp }));
                    return res.json({ success: true, session, username: ADMIN_USER, role: 'admin' });
                }
                return res.json({ error: 'Invalid credentials' });
            }
            if (a === 'me') {
                const raw = await redisCmd('GET', 'sess:' + (body.session || ''));
                const sess = typeof raw === 'string' ? JSON.parse(raw) : raw;
                if (!sess) return res.json({ error: 'Not logged in' });
                return res.json({ username: sess.username, role: sess.role });
            }
            return res.json({ error: 'Unknown auth action' });
        }

        // === ADMIN (requires session) ===
        if (path === 'admin') {
            const raw = await redisCmd('GET', 'sess:' + (body.session || ''));
            const sess = typeof raw === 'string' ? JSON.parse(raw) : raw;
            if (!sess || sess.role !== 'admin') return res.status(401).json({ error: 'Admin only' });

            if (a === 'stats') {
                const keys = await redisCmd('KEYS', 'lic:*') || [];
                let total = 0, active = 0, assigned = 0, revoked = 0;
                for (const k of keys) {
                    const rawLic = await redisCmd('GET', k);
                    const lic = typeof rawLic === 'string' ? JSON.parse(rawLic) : rawLic;
                    if (!lic || !lic.key) { await redisCmd('DEL', k); continue; }
                    total++;
                    if (lic.revoked) revoked++;
                    else if (lic.activated_ip) assigned++;
                    else active++;
                }
                return res.json({ total, active, assigned, revoked });
            }

            if (a === 'list_licenses') {
                const keys = await redisCmd('KEYS', 'lic:*') || [];
                const licenses = [];
                let total = 0, active = 0, assigned = 0, revoked = 0;
                for (const k of keys) {
                    const rawLic = await redisCmd('GET', k);
                    const lic = typeof rawLic === 'string' ? JSON.parse(rawLic) : rawLic;
                    if (!lic || !lic.key) { await redisCmd('DEL', k); continue; }
                    licenses.push(lic);
                    total++;
                    if (lic.revoked) revoked++;
                    else if (lic.activated_ip) assigned++;
                    else active++;
                }
                licenses.sort((x, y) => (y.created || '').localeCompare(x.created || ''));
                return res.json({ licenses, stats: { total, active, assigned, revoked } });
            }

            if (a === 'create_license') {
                const key = genKey();
                const expires = body.expires || null;
                const checksum = generateKeyChecksum(key);
                const license = { key, checksum, expires, revoked: false, created: now, activated_ip: null, activated_at: null, tag: body.tag || null, discord_id: null, discord_dm_sent: false };
                await redisCmd('SET', 'lic:' + key, JSON.stringify(license));
                return res.json({ success: true, key, expires });
            }

            if (a === 'revoke_license') {
                const key = body.key;
                if (!key) return res.json({ error: 'Key required' });
                if (!verifyKeyFormat(key)) return res.json({ error: 'Invalid key format' });
                const rawLic = await redisCmd('GET', 'lic:' + key);
                const lic = typeof rawLic === 'string' ? JSON.parse(rawLic) : rawLic;
                if (!lic) return res.json({ error: 'License not found' });
                lic.revoked = true;
                lic.activated_ip = null;
                lic.activated_at = null;
                await redisCmd('SET', 'lic:' + key, JSON.stringify(lic));
                return res.json({ success: true });
            }

            if (a === 'unrevoke_license') {
                const key = body.key;
                if (!key) return res.json({ error: 'Key required' });
                if (!verifyKeyFormat(key)) return res.json({ error: 'Invalid key format' });
                const rawLic = await redisCmd('GET', 'lic:' + key);
                const lic = typeof rawLic === 'string' ? JSON.parse(rawLic) : rawLic;
                if (!lic) return res.json({ error: 'License not found' });
                lic.revoked = false;
                await redisCmd('SET', 'lic:' + key, JSON.stringify(lic));
                return res.json({ success: true });
            }

            if (a === 'delete_license') {
                const key = body.key;
                if (!key) return res.json({ error: 'Key required' });
                if (!verifyKeyFormat(key)) return res.json({ error: 'Invalid key format' });
                await redisCmd('DEL', 'lic:' + key);
                return res.json({ success: true });
            }

            if (a === 'delete_install') {
                const requestId = body.requestId;
                if (!requestId) return res.json({ error: 'Request ID required' });
                await redisCmd('DEL', 'install:' + requestId);
                return res.json({ success: true });
            }

            return res.json({ error: 'Unknown admin action' });
        }

        // === LICENSE (public - validate/activate/deactivate) ===
        if (path === 'license') {
            if (a === 'validate') {
                const key = body.key;
                const ip = body.ip;
                if (!key) return res.json({ valid: false, error: 'Key required' });
                if (!verifyKeyFormat(key)) return res.json({ valid: false, error: 'Invalid key format' });
                const rawLic = await redisCmd('GET', 'lic:' + key);
                const lic = typeof rawLic === 'string' ? JSON.parse(rawLic) : rawLic;
                if (!lic) return res.json({ valid: false, error: 'Invalid key' });
                if (lic.revoked) return res.json({ valid: false, error: 'Key revoked' });
                if (lic.expires && new Date(lic.expires) < new Date()) return res.json({ valid: false, error: 'Key expired' });
                if (lic.activated_ip && lic.activated_ip !== ip) return res.json({ valid: false, error: 'Key active on different panel' });
                return res.json({ valid: true, checksum: lic.checksum || null });
            }

            if (a === 'activate') {
                const key = body.key;
                const ip = body.ip;
                if (!key || !ip) return res.json({ success: false, error: 'Key and IP required' });
                if (!verifyKeyFormat(key)) return res.json({ success: false, error: 'Invalid key format' });
                const rawLic = await redisCmd('GET', 'lic:' + key);
                const lic = typeof rawLic === 'string' ? JSON.parse(rawLic) : rawLic;
                if (!lic) return res.json({ success: false, error: 'Invalid key' });
                if (lic.revoked) return res.json({ success: false, error: 'Key revoked' });
                if (lic.expires && new Date(lic.expires) < new Date()) return res.json({ success: false, error: 'Key expired' });
                if (!lic.activated_ip || lic.activated_ip === ip) {
                    lic.activated_ip = ip;
                    lic.activated_at = lic.activated_at || now;
                    await redisCmd('SET', 'lic:' + key, JSON.stringify(lic));
                    return res.json({ success: true, key, checksum: lic.checksum || generateKeyChecksum(key) });
                }
                return res.json({ success: false, error: 'Key already active on another panel. Remove it first.' });
            }

            if (a === 'deactivate') {
                const key = body.key;
                const ip = body.ip;
                if (!key) return res.json({ error: 'Key required' });
                if (!verifyKeyFormat(key)) return res.json({ error: 'Invalid key format' });
                const rawLic = await redisCmd('GET', 'lic:' + key);
                const lic = typeof rawLic === 'string' ? JSON.parse(rawLic) : rawLic;
                if (!lic) return res.json({ success: false, error: 'Invalid key' });
                if (lic.activated_ip === ip) {
                    lic.activated_ip = null;
                    lic.activated_at = null;
                    await redisCmd('SET', 'lic:' + key, JSON.stringify(lic));
                    return res.json({ success: true });
                }
                return res.json({ success: false, error: 'Not activated on this IP' });
            }

            return res.json({ error: 'Unknown license action' });
        }

        // === INSTALL APPROVAL ===
        if (path === 'install') {
            if (a === 'request') {
                const vpsIp = body.ip || clientIp;
                const vpsHostname = body.hostname || 'unknown';
                const vpsOs = body.os || 'unknown';
                const requestId = crypto.randomBytes(8).toString('hex');

                const request = {
                    id: requestId,
                    ip: vpsIp,
                    hostname: vpsHostname,
                    os: vpsOs,
                    status: 'pending',
                    created: now,
                    decided: null,
                };
                await redisCmd('SET', 'install:' + requestId, JSON.stringify(request));

                // Send Discord webhook with Approve/Deny link buttons
                try {
                    await fetch(DISCORD_WEBHOOK, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            embeds: [{
                                title: '🖥️ New Install Request',
                                description: `Someone is trying to install the Avtix theme.\nClick **Approve** or **Deny** below.`,
                                color: 0xfbbf24,
                                fields: [
                                    { name: 'IP Address', value: '`' + vpsIp + '`', inline: true },
                                    { name: 'Hostname', value: '`' + vpsHostname + '`', inline: true },
                                    { name: 'OS', value: '`' + vpsOs + '`', inline: true },
                                    { name: 'Request ID', value: '`' + requestId + '`', inline: false },
                                ],
                                timestamp: now,
                            }],
                            components: [{
                                type: 1,
                                components: [
                                    {
                                        type: 2,
                                        style: 3,
                                        label: 'Approve',
                                        url: API_BASE + '/api/approve/' + requestId,
                                    },
                                    {
                                        type: 2,
                                        style: 4,
                                        label: 'Deny',
                                        url: API_BASE + '/api/deny/' + requestId,
                                    },
                                ],
                            }],
                        }),
                    });
                } catch (e) { /* webhook failed, continue */ }

                return res.json({ requestId, status: 'pending' });
            }

            if (a === 'check') {
                const requestId = body.requestId;
                if (!requestId) return res.json({ status: 'error', message: 'No request ID' });
                const raw = await redisCmd('GET', 'install:' + requestId);
                const request = typeof raw === 'string' ? JSON.parse(raw) : raw;
                if (!request) return res.json({ status: 'error', message: 'Request not found' });
                return res.json({ status: request.status });
            }

            return res.json({ error: 'Unknown install action' });
        }

        // === CHECK (install.sh pre-check, no key required) ===
        if (path === 'check') {
            return res.json({ allowed: true, server: 'vexythemes', version: '3.0.0' });
        }

        // === UPDATE (license required) ===
        if (path === 'update') {
            if (a === 'check') {
                const key = body.key;
                if (!key) return res.json({ available: false, error: 'Key required' });
                if (!verifyKeyFormat(key)) return res.json({ available: false, error: 'Invalid key format' });
                const rawLic = await redisCmd('GET', 'lic:' + key);
                const lic = typeof rawLic === 'string' ? JSON.parse(rawLic) : rawLic;
                if (!lic || lic.revoked) return res.json({ available: false, error: 'Invalid license' });

                const currentVersion = body.current_version || '0.0.0';
                const rawLatest = await redisCmd('GET', 'update:latest');
                const latest = typeof rawLatest === 'string' ? JSON.parse(rawLatest) : rawLatest;

                if (!latest) return res.json({ available: false, message: 'No updates available' });

                if (latest.version === currentVersion) {
                    return res.json({ available: false, message: 'Up to date' });
                }

                return res.json({
                    available: true,
                    version: latest.version,
                    released: latest.released || '',
                    changelog: latest.changelog || '',
                    checksum: latest.checksum || '',
                    size: latest.size || 0,
                });
            }

            if (a === 'download') {
                const key = body.key;
                const version = body.version;
                if (!key || !version) return res.json({ success: false, error: 'Key and version required' });
                if (!verifyKeyFormat(key)) return res.json({ success: false, error: 'Invalid key format' });
                const rawLic = await redisCmd('GET', 'lic:' + key);
                const lic = typeof rawLic === 'string' ? JSON.parse(rawLic) : rawLic;
                if (!lic || lic.revoked) return res.json({ success: false, error: 'Invalid license' });

                const rawLatest = await redisCmd('GET', 'update:latest');
                const latest = typeof rawLatest === 'string' ? JSON.parse(rawLatest) : rawLatest;
                if (!latest || latest.version !== version) {
                    return res.json({ success: false, error: 'Version mismatch' });
                }

                const rawZip = await redisCmd('GET', 'update:zip:' + version);
                if (!rawZip) return res.json({ success: false, error: 'Update file not found' });

                return res.json({
                    success: true,
                    version: latest.version,
                    zip_data: rawZip,
                    checksum: latest.checksum || '',
                });
            }

            return res.json({ error: 'Unknown update action' });
        }

        // === ADMIN UPDATE MANAGEMENT (requires session) ===
        if (path === 'admin' && (a === 'set_version' || a === 'upload_update')) {
            const raw = await redisCmd('GET', 'sess:' + (body.session || ''));
            const sess = typeof raw === 'string' ? JSON.parse(raw) : raw;
            if (!sess || sess.role !== 'admin') return res.status(401).json({ error: 'Admin only' });

            if (a === 'set_version') {
                const versionData = {
                    version: body.version,
                    released: body.released || now,
                    changelog: body.changelog || '',
                    checksum: body.checksum || '',
                    size: body.size || 0,
                };
                await redisCmd('SET', 'update:latest', JSON.stringify(versionData));
                return res.json({ success: true, version: body.version });
            }

            if (a === 'upload_update') {
                const version = body.version;
                const zipData = body.zip_data;
                if (!version || !zipData) return res.json({ success: false, error: 'Version and zip_data required' });
                await redisCmd('SET', 'update:zip:' + version, zipData);
                return res.json({ success: true, message: 'Update uploaded' });
            }
        }

        return res.status(404).json({ error: 'Not found' });
    } catch (e) {
        return res.status(500).json({ error: e.message || String(e) });
    }
};
