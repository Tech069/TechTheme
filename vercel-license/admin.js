const API = 'https://vt-panel-api.vercel.app/api/handler';
let session = null;

async function api(endpoint, body) {
  const res = await fetch(API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ...body, _endpoint: endpoint }),
  });
  return res.json();
}

async function login() {
  const data = await api('auth', { action: 'login', username: 'admin', password: 'vexy2026' });
  if (data.success) {
    session = data.session;
    console.log('Logged in.');
    return true;
  }
  console.log('Login failed:', data.error);
  return false;
}

const cmd = process.argv[2];
const arg1 = process.argv[3];

(async () => {
  if (!await login()) return;

  if (cmd === 'create') {
    const expires = arg1 || null;
    const data = await api('admin', { action: 'create_license', session, expires });
    if (data.success) {
      console.log('License key:', data.key);
      if (expires) console.log('Expires:', expires);
    } else {
      console.log('Error:', data.error);
    }
  }

  else if (cmd === 'list') {
    const data = await api('admin', { action: 'list_licenses', session });
    if (!data.licenses || data.licenses.length === 0) {
      console.log('No licenses.');
      return;
    }
    for (const l of data.licenses) {
      const status = l.revoked ? 'REVOKED' : l.activated_ip ? `ACTIVE (${l.activated_ip})` : 'UNUSED';
      const exp = l.expires ? ` exp:${l.expires}` : '';
      console.log(`${l.key}  ${status}${exp}`);
    }
  }

  else if (cmd === 'stats') {
    const data = await api('admin', { action: 'stats', session });
    console.log(`Total: ${data.total} | Unused: ${data.active} | In Use: ${data.assigned} | Revoked: ${data.revoked}`);
  }

  else if (cmd === 'revoke') {
    if (!arg1) { console.log('Usage: node admin.js revoke VEXY-XXXX-XXXX-XXXX-XXXX'); return; }
    const data = await api('admin', { action: 'revoke_license', session, key: arg1 });
    console.log(data.success ? 'Revoked.' : data.error);
  }

  else if (cmd === 'delete') {
    if (!arg1) { console.log('Usage: node admin.js delete VEXY-XXXX-XXXX-XXXX-XXXX'); return; }
    const data = await api('admin', { action: 'delete_license', session, key: arg1 });
    console.log(data.success ? 'Deleted.' : data.error);
  }

  else {
    console.log(`
VexyThemes Admin CLI

  node admin.js create [YYYY-MM-DD]   Create a license key
  node admin.js list                   List all keys
  node admin.js stats                  Show stats
  node admin.js revoke VEXY-XXXX-...  Revoke a key
  node admin.js delete VEXY-XXXX-...  Delete a key
    `);
  }
})();
