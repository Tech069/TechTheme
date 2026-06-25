<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VexyThemes — License Required</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #09090b;
            color: #fafafa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 40px;
            max-width: 440px;
            width: 90%;
            text-align: center;
        }
        .logo {
            width: 48px; height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            margin: 0 auto 24px;
            box-shadow: 0 0 40px -6px rgba(99,102,241,0.5);
        }
        h1 { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        h1 span { background: linear-gradient(135deg, #6366f1, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        p { color: #a1a1aa; font-size: 14px; margin-bottom: 24px; }
        .input-group { position: relative; margin-bottom: 16px; }
        input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #fafafa;
            font-size: 15px;
            font-family: 'SF Mono', 'Fira Code', monospace;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-align: center;
            outline: none;
            transition: border-color 0.2s;
        }
        input:focus { border-color: #6366f1; }
        input::placeholder { color: #52525b; letter-spacing: normal; text-transform: none; }
        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            box-shadow: 0 8px 24px -8px rgba(99,102,241,0.5);
        }
        .btn-primary:hover { opacity: 0.9; }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-secondary {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.1);
            color: #a1a1aa;
            margin-top: 8px;
        }
        .btn-secondary:hover { border-color: rgba(255,255,255,0.2); color: #fafafa; }
        .error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.2);
            border-radius: 8px;
            padding: 10px 14px;
            color: #fca5a5;
            font-size: 13px;
            margin-bottom: 16px;
            display: none;
        }
        .success {
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.2);
            border-radius: 8px;
            padding: 10px 14px;
            color: #86efac;
            font-size: 13px;
            margin-bottom: 16px;
            display: none;
        }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .footer { margin-top: 24px; font-size: 12px; color: #52525b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo"></div>
        <h1>Vexy<span>Themes</span></h1>
        <p>Enter your license key to activate this panel theme.</p>
        
        <div id="error" class="error"></div>
        <div id="success" class="success"></div>
        
        <form id="licenseForm">
            <div class="input-group">
                <input type="text" id="key" placeholder="VEXY-XXXX-XXXX-XXXX-XXXX" maxlength="26" autocomplete="off" spellcheck="false">
            </div>
            <button type="submit" id="submitBtn" class="btn-primary">
                <span id="btnText">Activate License</span>
                <span id="btnSpinner" class="spinner" style="display:none"></span>
            </button>
        </form>
        
        <p class="footer">Don't have a key? Contact us on Discord.</p>
    </div>

    <script>
    const API = '{{ $apiUrl }}';
    const PANEL = '{{ $panelUrl }}';

    // Auto-format key input: VEXY-XXXX-XXXX-XXXX-XXXX
    const keyInput = document.getElementById('key');
    keyInput.addEventListener('input', function(e) {
        let val = e.target.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        let formatted = '';
        for (let i = 0; i < val.length && i < 20; i++) {
            if (i > 0 && i % 4 === 0) formatted += '-';
            formatted += val[i];
        }
        e.target.value = 'VEXY-' + formatted;
        if (e.target.value === 'VEXY-') e.target.value = '';
    });

    document.getElementById('licenseForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const key = keyInput.value.trim();
        if (!key || key.length < 20) {
            showError('Please enter a valid license key.');
            return;
        }
        
        const btn = document.getElementById('submitBtn');
        const txt = document.getElementById('btnText');
        const spin = document.getElementById('btnSpinner');
        
        btn.disabled = true;
        txt.textContent = 'Activating...';
        spin.style.display = 'inline-block';
        hideError();
        hideSuccess();
        
        try {
            // Get server IP
            const ipRes = await fetch('https://api.ipify.org?format=json');
            const ipData = await ipRes.json();
            const ip = ipData.ip;
            
            // Activate license
            const res = await fetch(API + '/api/index', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _endpoint: 'license', action: 'activate', key: key, ip: ip }),
            });
            const data = await res.json();
            
            if (data.success) {
                // Save to panel settings via API call
                const saveRes = await fetch(PANEL + '/api/v2/vexythemes/license', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ action: 'save', key: key }),
                    credentials: 'same-origin',
                });
                
                showSuccess('License activated! Redirecting...');
                setTimeout(() => { window.location.reload(); }, 1500);
            } else {
                showError(data.error || 'Invalid license key.');
            }
        } catch (err) {
            showError('Failed to connect to license server. Try again.');
        } finally {
            btn.disabled = false;
            txt.textContent = 'Activate License';
            spin.style.display = 'none';
        }
    });

    function showError(msg) {
        const el = document.getElementById('error');
        el.textContent = msg;
        el.style.display = 'block';
    }
    function hideError() { document.getElementById('error').style.display = 'none'; }
    function showSuccess(msg) {
        const el = document.getElementById('success');
        el.textContent = msg;
        el.style.display = 'block';
    }
    function hideSuccess() { document.getElementById('success').style.display = 'none'; }
    </script>
</body>
</html>
