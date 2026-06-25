<div class="mb-6 rounded-xl border border-white/10 bg-white/[0.03] p-5">
    <div class="flex items-center gap-3 mb-4">
        <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600"></div>
        <div>
            <h3 class="font-display text-lg font-semibold">VexyThemes License</h3>
            <p class="text-xs text-muted-foreground">Manage your theme license key.</p>
        </div>
    </div>

    <div id="vexy-license-status" class="mb-4">
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            Checking license...
        </div>
    </div>

    <div id="vexy-license-active" style="display:none" class="mb-4 rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-3">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span>
                    <span class="text-sm font-medium text-emerald-300">License Active</span>
                </div>
                <code id="vexy-masked-key" class="mt-1 block text-xs text-emerald-400/70 font-mono"></code>
            </div>
            <button id="vexy-remove-btn" class="rounded-lg border border-red-500/20 bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-300 hover:bg-red-500/20 transition-colors">
                Remove Key
            </button>
        </div>
    </div>

    <div id="vexy-license-inactive" style="display:none" class="mb-4">
        <div class="flex items-center gap-3">
            <input type="text" id="vexy-key-input" placeholder="VEXY-XXXX-XXXX-XXXX-XXXX"
                class="flex-1 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm font-mono text-white placeholder:text-zinc-500 focus:border-indigo-500 focus:outline-none"
                maxlength="26" autocomplete="off" spellcheck="false">
            <button id="vexy-add-btn" class="rounded-lg bg-gradient-to-r from-indigo-500 to-purple-600 px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity">
                Add Key
            </button>
        </div>
        <p class="mt-2 text-xs text-muted-foreground">Enter your VexyThemes license key. 1 key = 1 panel.</p>
    </div>

    <div id="vexy-license-error" style="display:none" class="mb-4 rounded-lg border border-red-500/20 bg-red-500/5 p-3 text-sm text-red-300"></div>
    <div id="vexy-license-success" style="display:none" class="mb-4 rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-3 text-sm text-emerald-300"></div>

    <div class="border-t border-white/5 pt-4 mt-2">
        <a href="https://discord.gg/YOUR_DISCORD_LINK" target="_blank" class="text-xs text-indigo-400 hover:text-indigo-300">
            Need a license key? Join our Discord →
        </a>
    </div>
</div>

<script>
(function() {
    const API = '{{ $apiUrl ?? "https://vt-panel-api.vercel.app" }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function apiCall(action, body) {
        const res = await fetch('/api/v2/vexythemes/license', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ action, ...body }),
        });
        return res.json();
    }

    function showError(msg) {
        document.getElementById('vexy-license-error').textContent = msg;
        document.getElementById('vexy-license-error').style.display = 'block';
        document.getElementById('vexy-license-success').style.display = 'none';
    }
    function showSuccess(msg) {
        document.getElementById('vexy-license-success').textContent = msg;
        document.getElementById('vexy-license-success').style.display = 'block';
        document.getElementById('vexy-license-error').style.display = 'none';
    }

    async function checkStatus() {
        const data = await apiCall('status');
        document.getElementById('vexy-license-status').style.display = 'none';
        if (data.has_key && data.valid) {
            document.getElementById('vexy-license-active').style.display = 'block';
            document.getElementById('vexy-license-inactive').style.display = 'none';
            document.getElementById('vexy-masked-key').textContent = data.masked_key;
        } else {
            document.getElementById('vexy-license-active').style.display = 'none';
            document.getElementById('vexy-license-inactive').style.display = 'block';
        }
    }

    // Auto-format key
    const keyInput = document.getElementById('vexy-key-input');
    if (keyInput) {
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
    }

    // Add key
    document.getElementById('vexy-add-btn')?.addEventListener('click', async function() {
        const key = keyInput.value.trim();
        if (!key || key.length < 20) { showError('Enter a valid license key.'); return; }
        
        this.disabled = true;
        this.textContent = 'Activating...';
        showError(''); showSuccess('');
        
        const data = await apiCall('save', { key });
        
        if (data.success) {
            showSuccess('License activated!');
            setTimeout(() => location.reload(), 1000);
        } else {
            showError(data.error || 'Failed to activate license.');
        }
        
        this.disabled = false;
        this.textContent = 'Add Key';
    });

    // Remove key
    document.getElementById('vexy-remove-btn')?.addEventListener('click', async function() {
        if (!confirm('Remove this license key? The theme will stop working until you add a new key.')) return;
        
        this.disabled = true;
        this.textContent = 'Removing...';
        
        const data = await apiCall('remove');
        
        if (data.success) {
            showSuccess('License removed. Reloading...');
            setTimeout(() => location.reload(), 1000);
        } else {
            showError(data.error || 'Failed to remove license.');
            this.disabled = false;
            this.textContent = 'Remove Key';
        }
    });

    checkStatus();
})();
</script>
