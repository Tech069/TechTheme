<!-- VexyThemes Update Popup -->
<div id="vexy-update-popup" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:99999; backdrop-filter:blur(4px);">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#18181b; border:1px solid rgba(255,255,255,0.1); border-radius:16px; padding:32px; max-width:420px; width:90%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
        <div style="text-align:center; margin-bottom:20px;">
            <div style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,#6366f1,#8b5cf6); margin:0 auto 16px; display:flex; align-items:center; justify-content:center;">
                <svg width="24" height="24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </div>
            <h2 style="font-size:20px; font-weight:700; color:#fafafa; margin-bottom:4px;">
                Update Available
            </h2>
            <p style="font-size:13px; color:#a1a1aa;" id="vexy-update-version"></p>
        </div>

        <div id="vexy-update-changelog" style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:14px; margin-bottom:20px; max-height:160px; overflow-y:auto;">
            <p style="font-size:12px; color:#71717a; margin-bottom:6px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Changelog</p>
            <p style="font-size:13px; color:#d4d4d8; line-height:1.5;" id="vexy-update-changelog-text"></p>
        </div>

        <div id="vexy-update-progress" style="display:none; margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                <span style="font-size:12px; color:#a1a1aa;" id="vexy-update-status">Preparing update...</span>
                <span style="font-size:12px; color:#6366f1;" id="vexy-update-percent">0%</span>
            </div>
            <div style="height:4px; background:rgba(255,255,255,0.06); border-radius:4px; overflow:hidden;">
                <div id="vexy-update-bar" style="height:100%; background:linear-gradient(90deg,#6366f1,#8b5cf6); border-radius:4px; transition:width 0.3s; width:0%;"></div>
            </div>
        </div>

        <div id="vexy-update-error" style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); border-radius:8px; padding:10px 14px; color:#fca5a5; font-size:13px; margin-bottom:16px; display:none;"></div>

        <div id="vexy-update-actions">
            <button id="vexy-update-btn" style="width:100%; padding:12px; border:none; border-radius:10px; font-size:15px; font-weight:600; cursor:pointer; background:linear-gradient(135deg,#6366f1,#8b5cf6); color:white; box-shadow:0 8px 24px -8px rgba(99,102,241,0.5); transition:opacity 0.2s;">
                Update Now
            </button>
            <button id="vexy-update-dismiss" style="width:100%; padding:10px; border:none; border-radius:10px; font-size:13px; cursor:pointer; background:transparent; color:#71717a; margin-top:8px; transition:color 0.2s;">
                Remind me later
            </button>
        </div>

        <div id="vexy-update-success" style="display:none; text-align:center;">
            <div style="width:48px; height:48px; border-radius:50%; background:rgba(34,197,94,0.1); display:flex; align-items:center; justify-content:center; margin:0 auto 12px;">
                <svg width="24" height="24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <p style="font-size:15px; font-weight:600; color:#fafafa; margin-bottom:4px;">Update Complete!</p>
            <p style="font-size:13px; color:#a1a1aa;">Reloading panel...</p>
        </div>
    </div>
</div>

<script>
(function() {
    const API = '{{ $apiUrl ?? "https://vt-panel-api.vercel.app" }}';
    const popup = document.getElementById('vexy-update-popup');
    const btn = document.getElementById('vexy-update-btn');
    const dismiss = document.getElementById('vexy-update-dismiss');
    const progress = document.getElementById('vexy-update-progress');
    const actions = document.getElementById('vexy-update-actions');
    const errorDiv = document.getElementById('vexy-update-error');
    const successDiv = document.getElementById('vexy-update-success');
    const bar = document.getElementById('vexy-update-bar');
    const statusText = document.getElementById('vexy-update-status');
    const percentText = document.getElementById('vexy-update-percent');
    const versionText = document.getElementById('vexy-update-version');
    const changelogText = document.getElementById('vexy-update-changelog-text');
    const changelogBox = document.getElementById('vexy-update-changelog');

    async function checkForUpdate() {
        if (sessionStorage.getItem('vexy_update_dismissed')) return;
        if (localStorage.getItem('vexy_update_dismissed_' + getPanelUrl())) {
            const dismissed = parseInt(localStorage.getItem('vexy_update_dismissed_' + getPanelUrl()));
            if (Date.now() - dismissed < 86400000) return;
        }

        try {
            const res = await fetch('/api/v2/vexythemes/update/check', {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();

            if (data.available) {
                showUpdatePopup(data);
            }
        } catch (e) {
            console.log('VexyThemes update check failed:', e);
        }
    }

    function showUpdatePopup(data) {
        versionText.textContent = 'v' + (data.version || 'unknown') + ' — Released ' + (data.released || '');
        changelogText.textContent = data.changelog || 'No changelog available.';
        changelogBox.style.display = data.changelog ? 'block' : 'none';
        popup.style.display = 'flex';
    }

    btn.addEventListener('click', async function() {
        btn.disabled = true;
        btn.textContent = 'Updating...';
        errorDiv.style.display = 'none';
        progress.style.display = 'block';

        try {
            updateProgress('Checking license...', 10);
            const version = versionText.textContent.replace('v', '').split('—')[0].trim();

            updateProgress('Downloading update...', 30);
            const res = await fetch('/api/v2/vexythemes/update/apply', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ version: version }),
            });
            const data = await res.json();

            if (data.success) {
                updateProgress('Clearing caches...', 90);
                await fetch('/api/v2/vexythemes/update/clear-cache', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                updateProgress('Complete!', 100);
                actions.style.display = 'none';
                progress.style.display = 'none';
                successDiv.style.display = 'block';
                
                setTimeout(() => { window.location.reload(); }, 2000);
            } else {
                showError(data.error || 'Update failed.');
            }
        } catch (e) {
            showError('Connection failed. Please try again.');
        }
    });

    dismiss.addEventListener('click', function() {
        popup.style.display = 'none';
        sessionStorage.setItem('vexy_update_dismissed', '1');
        localStorage.setItem('vexy_update_dismissed_' + getPanelUrl(), Date.now().toString());
    });

    function updateProgress(status, percent) {
        statusText.textContent = status;
        percentText.textContent = percent + '%';
        bar.style.width = percent + '%';
    }

    function showError(msg) {
        errorDiv.textContent = msg;
        errorDiv.style.display = 'block';
        progress.style.display = 'none';
        btn.disabled = false;
        btn.textContent = 'Try Again';
    }

    function getPanelUrl() {
        return window.location.origin;
    }

    setTimeout(checkForUpdate, 5000);
    setInterval(checkForUpdate, 3600000);
})();
</script>
