const fs = require('fs');
const path = require('path');

const BASE = 'C:\\Users\\akshi\\Desktop\\HYPER-KI-MKC\\_theme-extract\\theme-full\\app\\Http\\Controllers';

function write(ns, name, methods, extra = '') {
    const dir = path.join(BASE, ns.replace(/\\/g, '/'));
    const file = path.join(dir, name + '.php');
    if (fs.existsSync(file)) {
        const c = fs.readFileSync(file, 'utf8').substring(0, 200);
        if (c.includes('namespace') && !c.includes('ionCube') && !c.includes('002cd') && !c.includes('AVTIX_ENCODED') && !c.includes('//')) {
            return;
        }
    }
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });

    const methodBodies = methods.map(m => {
        const body = extra && extra[m] ? extra[m] : "        return response()->json(['success' => true]);";
        return `    public function ${m}(\\Illuminate\\Http\\Request $request, \\Pterodactyl\\Models\\Server $server): \\Illuminate\\Http\\JsonResponse\n    {\n        try {\n${body}\n        } catch (\\Exception $e) {\n            return response()->json(['error' => 'Failed'], 500);\n        }\n    }`;
    }).join("\n\n");

    const php = `<?php\n\nnamespace Pterodactyl\\Http\\Controllers\\${ns};\n\nuse Illuminate\\Http\\Request;\nuse Illuminate\\Http\\JsonResponse;\nuse Pterodactyl\\Http\\Controllers\\Controller;\nuse Pterodactyl\\Models\\Server;\n\nclass ${name} extends Controller\n{\n${methodBodies}\n}\n`;

    fs.writeFileSync(file, php, 'utf8');
    console.log(`OK: ${ns}\\${name}`);
}

// Minecraft Plugin Cache
write('Api\\Client\\Servers\\DGEN', 'MinecraftPluginCacheController', [
    'getCachedPlugins', 'cachePluginData', 'clearCache', 'getCacheStatus', 'getGameVersions'
]);

// Minecraft Mod
write('Api\\Client\\Servers\\DGEN', 'MinecraftModController', [
    'checkAddonAvailability', 'getInstalledMods', 'installMod', 'uninstallMod', 'getProgress', 'getModVersions', 'getModIcon'
]);

// Minecraft Mod Cache
write('Api\\Client\\Servers\\DGEN', 'MinecraftModCacheController', [
    'getCachedMods', 'cacheModData', 'clearCache', 'getCacheStatus', 'getGameVersions'
]);

// Minecraft Modpack
write('Api\\Client\\Servers\\DGEN', 'MinecraftModpackController', [
    'getInstalledModpacks', 'installModpack', 'uninstallModpack', 'getProgress', 'getModpackVersions', 'restoreModpackServer', 'getModpackInstallStatus', 'checkAddonAvailability', 'getModpackIcon'
]);

// Minecraft Modpack Cache
write('Api\\Client\\Servers\\DGEN', 'MinecraftModpackCacheController', [
    'getCachedModpacks', 'getGameVersions', 'cacheModpackData', 'getModpackVersions', 'clearCache', 'getCacheStatus'
]);

// Minecraft World
write('Api\\Client\\Servers\\DGEN', 'MinecraftWorldController', [
    'getInstalledWorlds', 'installWorld', 'uninstallWorld', 'getProgress', 'inspectServer', 'getLevelName', 'updateLevelName', 'getWorldVersions', 'getWorldIcon', 'checkAddonAvailability'
]);

// Minecraft World Cache
write('Api\\Client\\Servers\\DGEN', 'MinecraftWorldCacheController', [
    'getCachedWorlds', 'cacheWorldData', 'clearCache', 'getCacheStatus', 'getGameVersions'
]);

// Minecraft Bedrock Addon
write('Api\\Client\\Servers\\DGEN', 'MinecraftBedrockAddonController', [
    'getInstalledAddons', 'installAddon', 'uninstallAddon', 'getProgress', 'getAddonVersions', 'getAddonIcon'
]);

// Minecraft Bedrock Addon Cache
write('Api\\Client\\Servers\\DGEN', 'MinecraftBedrockAddonCacheController', [
    'getCachedAddons', 'cacheAddonData', 'clearCache', 'getCacheStatus'
]);

// Minecraft Bedrock Version
write('Api\\Client\\Servers\\DGEN', 'MinecraftBedrockVersionController', [
    'getVersions', 'getSpecificVersions', 'changeVersion', 'getProgress'
]);

// Minecraft Player Manager (huge)
write('Api\\Client\\Servers\\DGEN', 'MinecraftPlayerManagerController', [
    'index', 'fixRcon', 'details', 'saveDetails', 'batchIcons', 'icon', 'worlds', 'action',
    'setHealth', 'setFood', 'setExperience', 'fastQuery', 'reload', 'getQueryStatus',
    'enableQuery', 'getWorlds', 'itemIcon', 'getPlayerItems', 'whitelistPlayer', 'unwhitelistPlayer',
    'banPlayer', 'unbanPlayer', 'opPlayer', 'deopPlayer', 'clearPlayerInventory', 'wipePlayerData',
    'changeGamemode', 'kickPlayer', 'banWithReason', 'giveItem', 'setSaturation', 'applyEffect',
    'genericAction', 'setInventorySlot', 'getServerInfo', 'setServerTime', 'setServerWeather',
    'setServerDifficulty', 'toggleGameRule'
]);

// Minecraft Votifier Tester
write('Api\\Client\\Servers\\DGEN', 'MinecraftVotifierTesterController', ['test']);

// Subdomain Manager
write('Api\\Client\\Servers\\DGEN', 'SubdomainManagerController', [
    'testConnection', 'fetchDomains', 'fetchAllSubdomains', 'deleteSubdomainAdmin', 'index', 'store', 'checkAvailability', 'destroy'
]);

// Custom Mod Manager
write('Api\\Client\\Servers\\DGEN', 'CustomModManagerController', [
    'index', 'store', 'update', 'destroy', 'listForServer', 'install', 'getProgress'
]);

// Server Type Changer
write('Api\\Client\\Servers\\DGEN', 'ServerTypeChangerController', [
    'getAllNestsAndEggs', 'getNests', 'getCurrentServerType', 'changeServerType', 'getProgress'
]);

// Server Type Changer Whitelist
write('Api\\Client\\Servers\\DGEN', 'ServerTypeChangerWhitelistController', [
    'index', 'store', 'searchServers', 'destroy'
]);

// Server Importer
write('Api\\Client\\Servers\\DGEN', 'ServerImporterController', [
    'testConnection', 'userImports', 'index', 'store', 'show', 'update', 'destroy', 'browse', 'import', 'importProgress', 'cancelImport', 'restore', 'status'
]);

// Upload From URL
write('Api\\Client\\Servers\\DGEN', 'UploadFromUrlController', ['query', 'upload']);

// GitHub Source Control
write('Api\\Client\\Servers\\DGEN', 'GithubSourceControlController', [
    'account', 'connect', 'disconnect', 'repositories', 'status', 'branches', 'commits',
    'diff', 'clone', 'fetch', 'pull', 'push', 'stage', 'unstage', 'discard', 'commit'
]);

// Server Stats
write('Api\\Client\\Servers\\DGEN', 'ServerStatsController', ['batch']);

// Staff Request
write('Api\\Client\\Servers\\DGEN', 'StaffRequestController', [
    'index', 'count', 'ownerRequests', 'store', 'accept', 'reject', 'destroy', 'autoReject',
    'searchServers', 'myServers', 'serverRequests', 'serverPendingCount'
]);

// Recycle Bin
write('Api\\Client\\Servers\\DGEN', 'RecycleBinController', [
    'index', 'stats', 'store', 'restore', 'restoreMultiple', 'permanentDelete', 'empty', 'show', 'preview', 'download'
]);

// Auto Suspend
write('Api\\Client\\Servers\\DGEN', 'AutoSuspendController', ['getExpiry', 'setExpiry', 'removeExpiry']);

// Quick File Access
write('Api\\Client\\Servers\\DGEN', 'QuickFileAccessController', [
    'index', 'store', 'toggle', 'check', 'validateItems', 'destroy', 'destroyByPath'
]);

// Server Splitter
write('Api\\Client\\Servers\\DGEN', 'ServerSplitterController', [
    'availableResources', 'index', 'store', 'show', 'update', 'destroy'
]);

// Server Splitter Whitelist
write('Api\\Client\\Servers\\DGEN', 'ServerSplitterWhitelistController', [
    'index', 'store', 'update', 'destroy', 'searchServers'
]);

// Server Splitter Migration
write('Api\\Client\\Servers\\DGEN', 'ServerSplitterMigrationController', [
    'getLegacySplits', 'migrateLegacySplit', 'searchUsers', 'getUserServers', 'hookServer'
]);

// Config Editor
write('Api\\Client\\Servers\\DGEN', 'ConfigEditorController', [
    'getAvailableFiles', 'getFileContent', 'updateFileContent'
]);

// Startup Presets
write('Api\\Client\\Servers\\DGEN', 'StartupPresetsController', ['getPresets', 'applyPreset', 'updateStartup']);

// Schedule Presets
write('Api\\Client\\Servers\\DGEN', 'SchedulePresetsController', ['applyPreset', 'importSchedule']);

// Server Wiper
write('Api\\Client\\Servers\\DGEN', 'ServerWiperController', [
    'getSchedules', 'createSchedule', 'updateSchedule', 'toggleSchedule', 'deleteSchedule',
    'executeNow', 'getHistory', 'getRustMaps', 'createRustMap', 'deleteRustMap'
]);

// Server Time Changer
write('Api\\Client\\Servers\\DGEN', 'ServerTimeChangerController', ['status', 'setTimezone']);

// Reverse Proxy Whitelist
write('Api\\Client\\Servers\\DGEN', 'ReverseProxyWhitelistController', [
    'index', 'store', 'update', 'destroy', 'searchServers'
]);

// FastDL
write('Api\\Client\\Servers\\DGEN', 'FastDLController', ['index', 'sync']);

// Server Agent Ticket
write('Api\\Client\\Servers\\DGEN', 'ServerAgentTicketController', ['ticket']);

// Network Statistics
write('Api\\Client\\Servers\\DGEN', 'NetworkStatisticsController', ['allocations', 'portDetail', 'portHistory']);

// Firewall Manager
write('Api\\Client\\Servers\\DGEN', 'FirewallManagerController', ['allocations', 'rules', 'addRule', 'deleteRule', 'resetPort']);

// Command History
write('Api\\Client\\Servers\\DGEN', 'CommandHistoryController', ['index', 'store']);

// Node Status
write('Api\\Client\\Servers\\DGEN', 'NodeStatusController', ['index']);

// Custom Monitor
write('Api\\Client\\Servers\\DGEN', 'CustomMonitorController', ['index', 'store', 'update', 'destroy']);

// === Client DGEN ===
write('Api\\Client\\DGEN', 'WingsAddonController', ['checkStatus', 'searchFiles', 'replaceFiles', 'folderSize', 'folderSizeBatch']);
write('Api\\Client\\DGEN', 'LoginActivityController', ['index', 'revoke']);
write('Api\\Client\\DGEN', 'DdosAlertController', ['summary', 'attacks', 'charts', 'syncNow']);
write('Api\\Client\\DGEN', 'FastDLNginxController', ['setup', 'remove', 'status']);

// === Client Admin DGEN ===
write('Api\\Client\\Admin\\DGEN', 'DiscordBotController', ['stats', 'triggerSync', 'botStatus', 'restartBot']);

// === Admin DGEN ===
write('Api\\Client\\Admin\\DGEN', 'PermissionRoleController', [
    'index', 'store', 'show', 'update', 'destroy', 'listPermissions', 'members', 'assignUser', 'unassignUser'
]);

// === Theme Controllers ===
write('Api\\Client\\Theme', 'HyperV2ThemeController', [
    'show', 'update', 'checkVersion', 'startUpdate', 'getUpdateStatus', 'getAvailableSidebarItems', 'ssoExchange', 'ssoInfo', 'ssoDisconnect'
]);
write('Api\\Client\\Theme', 'HyperV2AddonController', [
    'show', 'defaults', 'update', 'exportRaw', 'checkServerAvailability'
]);
write('Api\\Client\\Theme', 'PwaController', ['manifest', 'serviceWorkerConfig']);

// === Client Controllers ===
write('Api\\Client', 'DiscordVerificationController', ['check', 'refresh', 'accountCheck', 'accountRefresh']);
write('Api\\Client', 'AdminUserSearchController', ['search']);
write('Api\\Client', 'ReverseProxyController', ['index', 'store', 'update', 'delete']);

// === Auth Controllers ===
write('Auth', 'SSOLoginController', ['redirect', 'callback', 'confirmLink', 'unlink', 'dgenLink'], {
    redirect: "        return response()->json(['url' => '/auth/login']);",
    callback: "        return response()->json(['success' => true]);",
});
write('Auth', 'RegisterController', ['index', 'store'], {
    index: "        return response()->json(['fields' => []]);",
});
write('Auth', 'PasskeyController', ['registerOptions', 'register', 'loginOptions', 'login', 'delete'], {
    registerOptions: "        return response()->json(['options' => []]);",
});
write('Auth', 'ReferralController', ['index']);
write('Auth', 'WemxSsoController', ['login', 'webhook'], {
    login: "        return response()->json(['url' => '/auth/login']);",
});

// === Base Controllers ===
write('Base', 'HyperV2ThemePublicController', ['show'], {
    show: "        return response()->json(['theme' => 'hyperv2']);",
});
write('Base', 'LanguageController', ['available', 'set'], {
    available: "        return response()->json(['languages' => []]);",
});
write('Base', 'PublicStatusPageController', ['index'], {
    index: "        return response()->json(['nodes' => []]);",
});
write('Base', 'PublicStatsController', ['index'], {
    index: "        return response()->json(['stats' => []]);",
});
write('Base', 'HealthController', ['index'], {
    index: "        return response()->json(['healthy' => true]);",
});
write('Base', 'DocumentationController', ['show'], {
    show: "        return response()->json(['docs' => []]);",
});
write('Base', 'PublicNodeStatusController', ['index'], {
    index: "        return response()->json(['nodes' => []]);",
});

// === API ===
write('Api', 'PublicEggController', ['index'], {
    index: "        return response()->json(['eggs' => []]);",
});

// === Admin Extras ===
write('Admin', 'AuditLogController', ['index', 'clear'], {
    clear: "        return response()->json(['success' => true]);",
});
write('Admin', 'PanelLogsController', ['index', 'list', 'history', 'stream']);
write('Admin', 'AdminStatisticsController', ['index', 'liveStats', 'agentEndpoints']);
write('Admin', 'GlobalStorageBackendController', [
    'index', 'store', 'update', 'destroy', 'test', 'testById', 'assignNode', 'unassignNode', 'setDefaultForNode', 'clearDefaultForNode'
]);
write('Admin\\Nodes', 'WingsNodeStatsController', [
    'massUpdateAppName', 'massWingsControl', 'agentTicket', 'fetch', 'portsList', 'history',
    'portDetail', 'portHistory', 'wingsServiceStatus', 'wingsServiceControl', 'systemReboot',
    'firewallRules', 'firewallChains', 'firewallAddRule', 'firewallDeleteRule', 'firewallFlush',
    'firewallPortRules', 'firewallBlockPort', 'firewallAllowPort', 'agentVersionsAll',
    'agentVersionCheck', 'triggerAgentUpdate', 'wingsDaemonVersionsAll', 'wingsDaemonVersionCheck',
    'triggerWingsDaemonUpdate', 'pushMotdConfig', 'getMotdStatus', 'nodeLogsList', 'nodeLogsHistory', 'nodeLogsStream'
]);
write('Admin\\Nodes', 'NodeBackupController', [
    'storeConfig', 'trigger', 'restore', 'restoreStatus', 'testBackend', 'deleteBackup', 'history',
    'historyByRun', 'runDetail', 'progressStream', 'backupStatus', 'stats', 'getList', 'addToList',
    'removeFromList', 'availableServers', 'checkRuns', 'adminDeleteRun', 'adminDeleteEntry', 'transferTest'
]);
write('Admin\\Nests', 'EggRemoteController', ['index', 'import']);
write('Admin\\Servers', 'MassServerActionController', ['massAction', 'massTransfer']);

console.log('\nAll DGEN controllers written!');
